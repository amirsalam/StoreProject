<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Seed the base role set and migrate the legacy `is_admin` boolean
     * into role assignments.
     *
     * Roles:
     *   - admin    → full access (gets all permissions)
     *   - support  → product + order read-only, can refund
     *   - customer → default for everyone signing up
     *
     * Permissions are namespaced as <domain>.<verb> so the listing in
     * the admin UI groups naturally.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'products.view', 'products.manage',
            'orders.view', 'orders.refund',
            'users.view', 'users.manage',
            'branding.manage',
            'settings.manage',
            'activity.view',
            'api-tokens.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $support = Role::findOrCreate('support', 'web');
        $customer = Role::findOrCreate('customer', 'web');

        $admin->syncPermissions($permissions);
        $support->syncPermissions([
            'products.view',
            'orders.view',
            'orders.refund',
            'users.view',
            'activity.view',
        ]);
        $customer->syncPermissions([
            'api-tokens.manage',
        ]);

        // Backfill: anyone with the legacy is_admin column gets the
        // admin role; everyone else gets the customer role. The
        // is_admin column stays for backward compatibility (the
        // EnsureUserIsAdmin middleware now reads roles first, falls
        // back to is_admin if the relation isn't loaded yet).
        User::query()->chunk(200, function ($chunk) {
            foreach ($chunk as $user) {
                $role = $user->is_admin ? 'admin' : 'customer';
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->whereIn('name', ['admin', 'support', 'customer'])->delete();
        Permission::query()->whereIn('name', [
            'products.view', 'products.manage',
            'orders.view', 'orders.refund',
            'users.view', 'users.manage',
            'branding.manage',
            'settings.manage',
            'activity.view',
            'api-tokens.manage',
        ])->delete();
    }
};
