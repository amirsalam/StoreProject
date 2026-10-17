<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly TeamInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation->loadMissing(['tenant', 'invitedBy']);
        $tenantName = $invitation->tenant->name;
        $inviterName = $invitation->invitedBy->name ?? 'A teammate';
        $url = route('invitations.show', $invitation->token);

        return (new MailMessage)
            ->subject("You're invited to join {$tenantName}")
            ->greeting('Hi there,')
            ->line("{$inviterName} invited you to join the **{$tenantName}** workspace as **{$invitation->role}**.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires on '.$invitation->expires_at->format('M j, Y').'.')
            ->line('If you weren\'t expecting this, you can safely ignore the email.');
    }
}
