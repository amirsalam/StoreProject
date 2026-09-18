<?php

namespace Tests\Feature\Admin;

use App\Events\VendorStatusChanged;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin vendor moderation (marketplace doc §11/§17/§21): the review
 * queue, the guarded lifecycle transitions, owner notifications, audit,
 * tenant isolation, and the per-tenant approval mode.
 */
class VendorModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admins_cannot_view_or_moderate(): void
    {
        $vendor = Vendor::factory()->pending()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/vendors')->assertForbidden();
        $this->actingAs($user)->post("/admin/vendors/{$vendor->id}/approve")->assertForbidden();

        $this->assertSame(Vendor::STATUS_PENDING, $vendor->fresh()->status);
    }

    public function test_a_vendor_owner_cannot_approve_their_own_store(): void
    {
        // Even if they could reach the route, the policy refuses non-admins.
        $vendor = Vendor::factory()->pending()->create();

        $this->assertFalse($vendor->owner->can('moderate', $vendor));
        $this->assertTrue($this->admin()->can('moderate', $vendor));
    }

    public function test_queue_lists_pending_first_with_status_counts(): void
    {
        Vendor::factory()->count(2)->create();
        $pending = Vendor::factory()->pending()->create();
        Vendor::factory()->suspended()->create();

        $this->actingAs($this->admin())
            ->get('/admin/vendors')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/vendors/index')
                ->has('vendors.data', 4)
                ->where('vendors.data.0.id', $pending->id)
                ->where('counts.pending', 1)
                ->where('counts.active', 2)
                ->where('counts.suspended', 1)
                ->where('counts.rejected', 0)
                ->where('approvalMode', 'manual')
            );
    }

    public function test_queue_filters_by_status_and_searches_owner_email(): void
    {
        $owner = User::factory()->create(['email' => 'seller@acme.test']);
        $match = Vendor::factory()->pending()->create(['owner_user_id' => $owner->id]);
        Vendor::factory()->pending()->create();
        Vendor::factory()->create();

        $this->actingAs($this->admin())
            ->get('/admin/vendors?status=pending&search=acme.test')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('vendors.data', 1)
                ->where('vendors.data.0.id', $match->id)
                ->where('filters.status', 'pending')
            );

        // Unknown status values are ignored rather than matching nothing.
        $this->actingAs($this->admin())
            ->get('/admin/vendors?status=bogus')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.status', '')
                ->has('vendors.data', 3)
            );
    }

    public function test_approve_activates_audits_and_notifies_the_owner(): void
    {
        $vendor = Vendor::factory()->pending()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/vendors/{$vendor->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Vendor::STATUS_ACTIVE, $vendor->fresh()->status);

        $log = ActivityLog::query()->where('event', 'vendor.approved')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['vendor_id' => $vendor->id, 'from' => 'pending', 'to' => 'active'], $log->properties);

        $note = Notification::query()->where('user_id', $vendor->owner_user_id)->sole();
        $this->assertSame('vendor.approved', $note->type);
        $this->assertSame(Notification::LEVEL_SUCCESS, $note->level);
        $this->assertSame('/workspace/vendor', $note->action_url);
    }

    public function test_reject_records_the_reason_and_tells_the_owner_why(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->actingAs($this->admin())
            ->post("/admin/vendors/{$vendor->id}/reject", ['reason' => '  Name impersonates a brand  '])
            ->assertSessionHas('success');

        $this->assertSame(Vendor::STATUS_REJECTED, $vendor->fresh()->status);
        $this->assertSame(
            'Name impersonates a brand',
            ActivityLog::query()->where('event', 'vendor.rejected')->sole()->properties['reason'],
        );

        $note = Notification::query()->where('user_id', $vendor->owner_user_id)->sole();
        $this->assertSame('vendor.rejected', $note->type);
        $this->assertSame('Name impersonates a brand', $note->body);
    }

    public function test_suspend_then_reinstate(): void
    {
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/vendors/{$vendor->id}/suspend", ['reason' => 'Chargebacks']);
        $this->assertSame(Vendor::STATUS_SUSPENDED, $vendor->fresh()->status);

        $this->actingAs($admin)->post("/admin/vendors/{$vendor->id}/reinstate");
        $this->assertSame(Vendor::STATUS_ACTIVE, $vendor->fresh()->status);

        $this->assertSame(
            ['vendor.suspended', 'vendor.reinstated'],
            Notification::query()->where('user_id', $vendor->owner_user_id)->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_verify_grants_the_badge_only_to_active_vendors(): void
    {
        $active = Vendor::factory()->create();
        $pending = Vendor::factory()->pending()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/vendors/{$active->id}/verify")->assertSessionHas('success');
        $this->assertNotNull($active->fresh()->verified_at);
        $this->assertSame(Vendor::STATUS_ACTIVE, $active->fresh()->status);

        $this->actingAs($admin)->post("/admin/vendors/{$pending->id}/verify")->assertSessionHasErrors('vendor');
        $this->assertNull($pending->fresh()->verified_at);
        $this->assertSame(Vendor::STATUS_PENDING, $pending->fresh()->status);
    }

    /**
     * Every move the §11 state machine does NOT allow.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function illegalTransitions(): array
    {
        return [
            'approve active' => [Vendor::STATUS_ACTIVE, 'approve'],
            'approve suspended' => [Vendor::STATUS_SUSPENDED, 'approve'],
            'approve rejected' => [Vendor::STATUS_REJECTED, 'approve'],
            'reject active' => [Vendor::STATUS_ACTIVE, 'reject'],
            'suspend pending' => [Vendor::STATUS_PENDING, 'suspend'],
            'suspend rejected' => [Vendor::STATUS_REJECTED, 'suspend'],
            'reinstate active' => [Vendor::STATUS_ACTIVE, 'reinstate'],
            'reinstate rejected' => [Vendor::STATUS_REJECTED, 'reinstate'],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_are_refused_without_side_effects(string $status, string $action): void
    {
        Event::fake([VendorStatusChanged::class]);
        $vendor = Vendor::factory()->create(['status' => $status]);

        $this->actingAs($this->admin())
            ->post("/admin/vendors/{$vendor->id}/{$action}")
            ->assertSessionHasErrors('vendor');

        $this->assertSame($status, $vendor->fresh()->status);
        $this->assertSame(0, ActivityLog::query()->where('event', 'like', 'vendor.%')->count());
        Event::assertNotDispatched(VendorStatusChanged::class);
    }

    public function test_status_change_event_carries_action_actor_and_reason(): void
    {
        Event::fake([VendorStatusChanged::class]);
        $vendor = Vendor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/vendors/{$vendor->id}/suspend", ['reason' => 'Fraud']);

        Event::assertDispatched(VendorStatusChanged::class, fn (VendorStatusChanged $e) => $e->vendor->is($vendor)
            && $e->action === 'suspended'
            && $e->fromStatus === Vendor::STATUS_ACTIVE
            && $e->actor?->is($admin)
            && $e->reason === 'Fraud');
    }

    public function test_reason_is_capped_at_500_characters(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/vendors/{$vendor->id}/suspend", ['reason' => str_repeat('x', 501)])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Vendor::STATUS_ACTIVE, $vendor->fresh()->status);
    }

    public function test_vendors_of_another_tenant_are_not_found(): void
    {
        [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();

        app(TenantContext::class)->set($tenantB);
        $foreign = Vendor::factory()->pending()->create();

        app(TenantContext::class)->set($tenantA);

        $this->actingAs($this->admin())
            ->post("/admin/vendors/{$foreign->id}/approve")
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get('/admin/vendors')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('vendors.data', 0));

        $this->assertSame(
            Vendor::STATUS_PENDING,
            Vendor::query()->withoutGlobalScope('tenant')->find($foreign->id)->status,
        );
    }

    public function test_admin_sets_the_approval_mode_for_the_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $this->actingAs($this->admin())
            ->put('/admin/vendors/approval-mode', ['mode' => 'auto'])
            ->assertSessionHas('success');

        $this->assertSame('auto', $tenant->fresh()->settings['marketplace']['vendor_approval_mode']);
        $this->assertSame('vendor.approval_mode_changed', ActivityLog::query()->latest('id')->first()->event);

        $this->actingAs($this->admin())
            ->get('/admin/vendors')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('approvalMode', 'auto')
                ->where('canConfigureMode', true)
            );
    }

    public function test_approval_mode_needs_a_tenant_in_context(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/vendors/approval-mode', ['mode' => 'auto'])
            ->assertSessionHasErrors('mode');

        $this->actingAs($this->admin())
            ->get('/admin/vendors')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canConfigureMode', false));
    }

    public function test_approval_mode_rejects_unknown_values(): void
    {
        app(TenantContext::class)->set(Tenant::factory()->create());

        $this->actingAs($this->admin())
            ->put('/admin/vendors/approval-mode', ['mode' => 'sometimes'])
            ->assertSessionHasErrors('mode');
    }
}
