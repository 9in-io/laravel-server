<?php

namespace Nine\Laravel\Server\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Nine\Laravel\Server\ServerServiceProvider;
use Nine\Signature;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const SECRET = 'adapter_secret_test';

    protected function getPackageProviders($app)
    {
        return [ServerServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('nine-server.secret', self::SECRET);
        $app['config']->set('nine-server.user.model', User::class);
        $app['config']->set('nine-server.user.columns.blocked', 'blocked_at');
        $app['config']->set('nine-server.otp.vars.brand', 'Acme');
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'name'              => 'Jane Doe',
            'username'          => 'jane',
            'email'             => 'Jane@Acme.com',
            'email_verified_at' => now(),
            'phone'             => '+447700900000',
            'password'          => Hash::make('hunter2'),
            'metadata'          => ['plan' => 'pro'],
        ], $attributes));
    }

    /**
     * Calls the adapter like 9in does: signed POST with a request id.
     *
     * @param array<string, mixed> $body
     */
    protected function adapterCall(string $endpoint, array $body, ?string $secret = self::SECRET, string $requestId = 'req_test'): TestResponse
    {
        $path    = '/nine/' . $endpoint;
        $raw     = json_encode($body);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_9IN_REQUEST_ID' => $requestId];
        if ($secret !== null) {
            $headers['HTTP_9IN_SIGNATURE'] = Signature::sign($secret, 'POST', $path, $raw);
        }
        return $this->call('POST', $path, [], [], [], $headers, $raw);
    }

    protected function assertSignedResponse(TestResponse $response, string $endpoint, string $requestId = 'req_test'): void
    {
        $this->assertTrue(
            Signature::verify(self::SECRET, $response->headers->get('9in-signature'), 'POST', '/nine/' . $endpoint . '#' . $requestId, (string) $response->getContent()),
            'adapter responses must be signed over path#request-id'
        );
    }
}

class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'email_verified_at' => 'datetime', 'phone_verified_at' => 'datetime', 'blocked_at' => 'datetime'];
}
