<?php

namespace App\Domain\Team;

use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Invite a new member to a tenant.
 *
 *   $action->execute($tenant, $inviter, 'maya@example.com', Tenant::ROLE_ADMIN);
 *
 * - Refuses if the email is already a member of the tenant.
 * - Refuses if an open (unexpired, unaccepted) invitation already
 *   exists for the same (tenant, email).
 * - Sends the notification only after the row commits, so a queue
 *   driver failure can never produce an email referencing a token
 *   that doesn't exist in the DB.
 */
final readonly class InviteMemberAction
{
    public const TOKEN_BYTES = 32;                  // 32 random bytes → 64 hex chars

    public const TTL_DAYS = 7;

    public function execute(Tenant $tenant, User $inviter, string $email, string $role): TeamInvitation
    {
        $email = strtolower(trim($email));

        // Already a member?
        if ($tenant->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$email} is already a member of this workspace.",
            ]);
        }

        // Already invited and the invite is still open?
        $existing = TeamInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'email' => "An invitation for {$email} is already pending.",
            ]);
        }

        return DB::transaction(function () use ($tenant, $inviter, $email, $role) {
            $invitation = TeamInvitation::create([
                'tenant_id' => $tenant->id,
                'invited_by_id' => $inviter->id,
                'email' => $email,
                'role' => $role,
                'token' => bin2hex(random_bytes(self::TOKEN_BYTES)),
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ]);

            // Notification sends after commit so we never leak a token
            // for a row that ultimately rolled back.
            DB::afterCommit(function () use ($invitation) {
                Notification::route('mail', $invitation->email)
                    ->notify(new TeamInvitationNotification($invitation));
            });

            return $invitation;
        });
    }
}
