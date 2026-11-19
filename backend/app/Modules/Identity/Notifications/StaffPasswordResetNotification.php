<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StaffPasswordResetNotification extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('identity.frontend_url', config('app.url')), '/')
            .'/staff/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Reset your Minerva staff password')
            ->line('A password reset was requested for your staff account.')
            ->action('Reset password', $url)
            ->line(sprintf('This link expires in %d minutes.', (int) config('auth.passwords.users.expire', 30)))
            ->line('If you did not request this, no action is required.');
    }
}
