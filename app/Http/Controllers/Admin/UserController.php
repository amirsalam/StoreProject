<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'role' => (string) $request->string('role'),
        ];

        $query = User::query()->with('roles:id,name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        if ($filters['role'] !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }

        return Inertia::render('admin/users/index', [
            'users' => $query->latest('id')->paginate(20)->withQueryString()->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'email_verified_at' => $u->email_verified_at,
                'has_two_factor' => $u->hasTwoFactorEnabled(),
                'created_at' => $u->created_at,
                'role' => $u->roles->first()?->name,
            ]),
            'filters' => $filters,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(Role::query()->pluck('name')->all())],
        ]);

        // Guard: don't allow the only admin to demote themselves to a
        // non-admin role — would lock the panel.
        if (
            $user->id === $request->user()->id
            && $user->hasRole('admin')
            && $data['role'] !== 'admin'
            && User::query()->role('admin')->count() <= 1
        ) {
            return back()->withErrors(['role' => 'You are the only admin — promote another user first.']);
        }

        $user->syncRoles([$data['role']]);

        // Keep the legacy is_admin column in sync so the boolean still
        // reads correctly for callers that haven't migrated to roles.
        $user->forceFill(['is_admin' => $data['role'] === 'admin'])->save();

        return back()->with('success', "Updated {$user->name}'s role to {$data['role']}.");
    }
}
