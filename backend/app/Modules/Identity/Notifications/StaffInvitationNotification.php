<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StaffInvitationNotification extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $role,
        public readonly string $invitedByName,
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
            .'/staff/accept-invitation?token='.urlencode($this->token);

        return (new MailMessage)
            ->subject('Minerva staff invitation')
            ->greeting('You have been invited')
            ->line(sprintf('%s invited you to join the Minerva Digital Platform as a %s.', $this->invitedByName, $this->role))
            ->action('Accept invitation', $url)
            ->line(sprintf('This invitation expires in %d hours.', (int) config('identity.staff.invitation_ttl_hours', 72)))
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
