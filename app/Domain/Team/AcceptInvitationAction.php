<?php

namespace App\Domain\Team;

use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accept a team invitation as the currently-authenticated user.
 *
 * The caller is responsible for resolving the invitation by token
 * and authenticating (or registering) the user beforehand. This
 * action validates the invariants and attaches the user.
 *
 * Returns the joined Tenant on success.
 */
final readonly class AcceptInvitationAction
{
    public function execute(TeamInvitation $invitation, User $user): Tenant
    {
        // (1) Email must match the invitation. If it doesn't, the user
        //     authenticated as a different account than the invite
        //     targeted — refuse and let the controller prompt re-auth.
        if (strcasecmp($user->email, $invitation->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => "This invitation is for {$invitation->email}. "
                    .'Sign in with that email to accept.',
            ]);
        }

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has already been accepted.',
            ]);
        }
        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired. Ask for a new one.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $tenant = $invitation->tenant;

            // Idempotent: if a pivot row already exists (re-clicked link
            // mid-flight), don't duplicate it — update the role if it changed.
            $existing = $tenant->users()->where('users.id', $user->id)->first();
            if ($existing) {
                $tenant->users()->updateExistingPivot($user->id, [
                    'role' => $invitation->role,
                ]);
            } else {
                $tenant->users()->attach($user->id, [
                    'role' => $invitation->role,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            return $tenant;
        });
    }
}
