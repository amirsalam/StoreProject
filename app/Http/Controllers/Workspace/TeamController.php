<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Plans\PlanGate;
use App\Domain\Team\InviteMemberAction;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $tenant = app(TenantContext::class)->current();

        return Inertia::render('workspace/team/index', [
            'members' => $tenant
                ? $tenant->users()
                    ->withPivot(['role', 'joined_at'])
                    ->get()
                    ->map(fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->pivot->role,
                        'joined_at' => $user->pivot->joined_at,
                    ])
                : [],
            'invitations' => $tenant
                ? TeamInvitation::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->latest()
                    ->get(['id', 'email', 'role', 'expires_at', 'created_at'])
                : [],
            'roles' => $this->roles(),
        ]);
    }

    public function invite(Request $request, InviteMemberAction $action, PlanGate $gate): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', 'in:'.implode(',', array_column($this->roles(), 'value'))],
        ]);

        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 404, 'No workspace in scope.');

        // PlanGate guard: adding +1 user must stay under the plan's seat cap.
        if (! $gate->withinLimit($tenant, 'users', 1)) {
            abort(402, 'You\'ve reached your plan\'s seat limit. Upgrade to invite more members.');
        }

        $action->execute($tenant, $request->user(), $data['email'], $data['role']);

        return redirect()
            ->route('workspace.team.index')
            ->with('success', "Invitation sent to {$data['email']}.");
    }

    public function revoke(TeamInvitation $invitation): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $invitation->tenant_id === $tenant->id, 404);

        $invitation->delete();

        return redirect()
            ->route('workspace.team.index')
            ->with('success', 'Invitation revoked.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function roles(): array
    {
        return [
            ['value' => Tenant::ROLE_OWNER, 'label' => 'Owner'],
            ['value' => Tenant::ROLE_ADMIN, 'label' => 'Admin'],
            ['value' => Tenant::ROLE_MEMBER, 'label' => 'Member'],
        ];
    }
}
