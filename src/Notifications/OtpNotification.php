<?php

namespace Nine\Laravel\Server\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivers a one-time code from an `otp.requested` adapter event. Views:
 * `nine::otp.email` and `nine::otp.sms` (publish with --tag=nine:views to customise).
 */
class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> the otp.requested data: user_id, channel, to, code, purpose, expires_at (ISO 8601) */
    public $data;

    /** @var array<string, mixed> */
    public $vars;

    /** @var string notification channel (mail, vonage, nexmo, a custom channel…) */
    public $channel;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $vars
     */
    public function __construct(array $data, array $vars, string $channel)
    {
        $this->data    = $data;
        $this->vars    = $vars;
        $this->channel = $channel;
    }

    public function via($notifiable)
    {
        return [$this->channel];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject($this->purposeLabel() . ' code')
            ->view('nine::otp.email', $this->viewData());
    }

    public function toVonage($notifiable)
    {
        return $this->smsMessage('Illuminate\Notifications\Messages\VonageMessage');
    }

    public function toNexmo($notifiable)
    {
        return $this->smsMessage('Illuminate\Notifications\Messages\NexmoMessage');
    }

    /** Plain text for custom SMS channels. */
    public function toSms($notifiable): string
    {
        return $this->smsText();
    }

    public function smsText(): string
    {
        return trim(view('nine::otp.sms', $this->viewData())->render());
    }

    protected function smsMessage(string $class)
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("{$class} is not installed; install the Vonage notification channel or set NINE_OTP_SMS_CHANNEL.");
        }
        return (new $class())->content($this->smsText());
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'code'         => (string) ($this->data['code'] ?? ''),
            'purpose'      => (string) ($this->data['purpose'] ?? 'login'),
            'purposeLabel' => $this->purposeLabel(),
            'expiresAt'    => $this->data['expires_at'] ?? null,
            'vars'         => $this->vars,
        ];
    }

    protected function purposeLabel(): string
    {
        return ($this->data['purpose'] ?? 'login') === 'password_reset' ? 'Password reset' : 'Sign-in';
    }
}
