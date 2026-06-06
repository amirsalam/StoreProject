<?php

namespace App\Http\Controllers;

use App\Domain\Team\AcceptInvitationAction;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team-invitation accept flow.
 *
 * GET  /invitations/{token}        — show the accept page (logged-in
 *                                    or logged-out; guests see a
 *                                    "Sign in to accept" prompt).
 * POST /invitations/{token}/accept — accept (auth required; calls
 *                                    AcceptInvitationAction).
 *
 * The token is looked up directly by string match — TeamInvitation
 * is not BelongsToTenant for this reason.
 */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = TeamInvitation::query()
            ->with(['tenant:id,name,slug', 'invitedBy:id,name'])
            ->where('token', $token)
            ->first();

        return Inertia::render('invitations/accept', [
            'invitation' => $invitation ? [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'tenant' => [
                    'name' => $invitation->tenant->name,
                    'slug' => $invitation->tenant->slug,
                ],
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at,
                'is_open' => $invitation->isOpen(),
                'is_accepted' => $invitation->isAccepted(),
                'is_expired' => $invitation->isExpired(),
            ] : null,
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitationAction $action): RedirectResponse
    {
        $invitation = TeamInvitation::query()->where('token', $token)->firstOrFail();

        if (! $request->user()) {
            // Stash the invitation token + send to login; the login flow
            // can redirect back here on success.
            $request->session()->put('pending_invitation_token', $token);

            return redirect()->route('login')->with('info', 'Sign in to accept your invitation.');
        }

        $tenant = $action->execute($invitation, $request->user());

        // Clear any pending stash.
        $request->session()->forget('pending_invitation_token');

        return redirect()
            ->route('workspace.team.index')
            ->with('success', "You've joined {$tenant->name}.");
    }
}
