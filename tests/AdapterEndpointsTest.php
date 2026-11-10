<?php

namespace Nine\Laravel\Server\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Nine\Laravel\Events\EventReceived;
use Nine\Laravel\Events\OtpRequested;
use Nine\Laravel\Events\PasswordChanged;
use Nine\Laravel\Server\Notifications\OtpNotification;

class AdapterEndpointsTest extends TestCase
{
    public function testAuthenticatesWithEmailCaseInsensitively(): void
    {
        $user     = $this->makeUser();
        $response = $this->adapterCall('authenticate', ['identifier' => ['type' => 'email', 'value' => 'jane@acme.com'], 'password' => 'hunter2']);

        $response->assertStatus(200);
        $this->assertSignedResponse($response, 'authenticate');
        $this->assertSame([
            'id'                    => (string) $user->id,
            'email'                 => 'Jane@Acme.com',
            'email_verified'        => true,
            'phone_number'          => '+447700900000',
            'phone_number_verified' => false,
            'username'              => 'jane',
            'name'                  => 'Jane Doe',
            'given_name'            => null,
            'family_name'           => null,
            'picture'               => null,
            'locale'                => null,
            'zoneinfo'              => null,
            'birthdate'             => null,
            'blocked'               => false,
            'metadata'              => ['plan' => 'pro'],
        ], $response->json('user'));
    }

    public function testWrongPasswordUnknownUserAndDisabledIdentifiers(): void
    {
        $this->makeUser();
        $wrong = $this->adapterCall('authenticate', ['identifier' => ['type' => 'username', 'value' => 'jane'], 'password' => 'nope']);
        $wrong->assertStatus(401)->assertExactJson(['error' => 'invalid_credentials']);
        $this->assertSignedResponse($wrong, 'authenticate');

        $this->adapterCall('authenticate', ['identifier' => ['type' => 'email', 'value' => 'nobody@acme.com'], 'password' => 'hunter2'])->assertStatus(401);

        config(['nine-server.user.identifiers' => ['email']]);
        $this->adapterCall('authenticate', ['identifier' => ['type' => 'username', 'value' => 'jane'], 'password' => 'hunter2'])->assertStatus(401);
    }

    public function testBlockedUsers(): void
    {
        $this->makeUser(['blocked_at' => now()]);
        $this->adapterCall('authenticate', ['identifier' => ['type' => 'phone', 'value' => '+447700900000'], 'password' => 'hunter2'])
            ->assertStatus(403)
            ->assertExactJson(['error' => 'blocked']);
    }

    public function testRehashesOutdatedPasswords(): void
    {
        $user = $this->makeUser(['password' => Hash::make('hunter2', ['rounds' => 4])]);
        $this->adapterCall('authenticate', ['identifier' => ['type' => 'email', 'value' => 'jane@acme.com'], 'password' => 'hunter2'])->assertStatus(200);
        $this->assertFalse(Hash::needsRehash($user->fresh()->password));
    }

    public function testFindsUsers(): void
    {
        $user = $this->makeUser();
        $this->adapterCall('user', ['id' => (string) $user->id])->assertStatus(200)->assertJsonPath('user.username', 'jane');
        $this->adapterCall('user', ['identifier' => ['type' => 'username', 'value' => 'JANE']])->assertStatus(200)->assertJsonPath('user.id', (string) $user->id);

        $missing = $this->adapterCall('user', ['id' => '999']);
        $missing->assertStatus(404)->assertExactJson(['error' => 'not_found']);
        $this->assertSignedResponse($missing, 'user');
    }

    public function testRejectsUnsignedAndForgedRequests(): void
    {
        $this->makeUser();
        $this->adapterCall('user', ['id' => '1'], null)->assertStatus(401)->assertExactJson(['error' => 'invalid_signature']);
        $this->adapterCall('user', ['id' => '1'], 'wrong-secret')->assertStatus(401);
    }

    public function testAcceptsThePreviousSecret(): void
    {
        config(['nine-server.previous_secret' => 'old-secret']);
        $this->app->forgetInstance(\Nine\Adapter\AdapterHandler::class);
        $user = $this->makeUser();
        $this->adapterCall('user', ['id' => (string) $user->id], 'old-secret')->assertStatus(200);
    }

    public function testOtpEventsAreDeliveredAndDispatched(): void
    {
        Notification::fake();
        Event::fake([OtpRequested::class, EventReceived::class]);

        $response = $this->adapterCall('event', [
            'id'         => 'evt_1',
            'event'      => 'otp.requested',
            'created_at' => '2026-11-09T10:00:00.000Z',
            'data'       => ['user_id' => '1', 'channel' => 'email', 'to' => 'jane@acme.com', 'code' => '424242', 'purpose' => 'login', 'expires_at' => '2026-11-09T10:05:00.000Z'],
        ]);

        $response->assertStatus(204);
        $this->assertSame('', $response->getContent());
        $this->assertSignedResponse($response, 'event');

        Notification::assertSentOnDemand(OtpNotification::class, function (OtpNotification $notification, array $channels, $notifiable) {
            return $channels === ['mail'] && $notifiable->routes['mail'] === 'jane@acme.com' && $notification->data['code'] === '424242';
        });
        Event::assertDispatched(OtpRequested::class, function (OtpRequested $event) {
            return $event->fromAdapter() && $event->code() === '424242' && $event->userId() === '1'
                && $event->expiresAt()?->format(DATE_ATOM) === '2026-11-09T10:05:00+00:00';
        });
        Event::assertDispatched(EventReceived::class);
    }

    public function testSmsCodesRenderTheSmsView(): void
    {
        $notification = new OtpNotification(['code' => '123456', 'purpose' => 'password_reset', 'channel' => 'sms'], ['brand' => 'Acme'], 'vonage');
        $this->assertSame('123456 is your Acme password reset code.', $notification->smsText());
        $this->assertSame(['vonage'], $notification->via(null));
    }

    public function testOtpDeliveryCanBeTurnedOff(): void
    {
        Notification::fake();
        Event::fake([PasswordChanged::class]);
        config(['nine-server.otp.send' => false]);

        $this->adapterCall('event', ['id' => 'e', 'event' => 'otp.requested', 'created_at' => '2026-11-09T10:00:00.000Z', 'data' => ['channel' => 'email', 'to' => 'x@acme.com', 'code' => '1']])->assertStatus(204);
        $this->adapterCall('event', ['id' => 'e2', 'event' => 'user.password_changed', 'created_at' => '2026-11-09T10:00:00.000Z', 'data' => ['user_id' => '1', 'fields' => []]])->assertStatus(204);

        Notification::assertNothingSent();
        Event::assertDispatched(PasswordChanged::class);
    }
}
