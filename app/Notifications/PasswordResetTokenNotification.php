<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the password reset token so it is never exposed in the API response.
 */
class PasswordResetTokenNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/') . '/reset-password?' . http_build_query([
            'email' => $notifiable->email,
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('We received a request to reset the password for your account.')
            ->action('Reset Password', $resetUrl)
            ->line('Or enter this reset token manually:')
            ->line($this->token)
            ->line('This token expires in 60 minutes. If you did not request a reset, you can ignore this email.');
    }
}
