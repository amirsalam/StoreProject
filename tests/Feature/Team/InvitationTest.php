<?php

namespace Tests\Feature\Team;

use App\Models\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    public function test_workspace_owner_can_invite_a_new_member(): void
    {
        Notification::fake();
        [$tenant, $owner] = $this->tenantWithOwner();
        app(TenantContext::class)->set($tenant);

        $this->actingAs($owner)
            ->post('/workspace/team/invitations', [
                'email' => 'newbie@example.com',
                'role' => Tenant::ROLE_MEMBER,
            ])
            ->assertRedirect('/workspace/team')
            ->assertSessionHas('success');

        $invitation = TeamInvitation::query()->where('email', 'newbie@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame($tenant->id, $invitation->tenant_id);
        $this->assertSame(Tenant::ROLE_MEMBER, $invitation->role);
        $this->assertTrue(strlen($invitation->token) === 64);
        $this->assertTrue($invitation->expires_at->isFuture());

        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_invite_rejects_existing_member(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $tenant->users()->attach($existing->id, ['role' => Tenant::ROLE_MEMBER, 'joined_at' => now()]);

        app(TenantContext::class)->set($tenant);

        $this->actingAs($owner)
            ->post('/workspace/team/invitations', [
                'email' => 'existing@example.com',
                'role' => Tenant::ROLE_MEMBER,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('team_invitations', ['email' => 'existing@example.com']);
    }

    public function test_invite_rejects_duplicate_open_invitation(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        app(TenantContext::class)->set($tenant);

        TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'dup@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)
            ->post('/workspace/team/invitations', [
                'email' => 'dup@example.com',
                'role' => Tenant::ROLE_MEMBER,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, TeamInvitation::query()->where('email', 'dup@example.com')->count());
    }

    public function test_accept_attaches_user_and_marks_accepted(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $invited = User::factory()->create(['email' => 'invited@example.com']);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'invited@example.com',
            'role' => Tenant::ROLE_ADMIN,
            'token' => str_repeat('b', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($invited)
            ->post("/invitations/{$invitation->token}/accept")
            ->assertRedirect('/workspace/team');

        $this->assertNotNull($invitation->fresh()->accepted_at);

        $pivot = $tenant->users()->where('users.id', $invited->id)->first()->pivot;
        $this->assertSame(Tenant::ROLE_ADMIN, $pivot->role);
        $this->assertNotNull($pivot->joined_at);
    }

    public function test_accept_rejects_expired_invitation(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $invited = User::factory()->create(['email' => 'late@example.com']);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'late@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('c', 64),
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($invited)
            ->post("/invitations/{$invitation->token}/accept")
            ->assertSessionHasErrors('token');

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertSame(0, $tenant->users()->where('users.id', $invited->id)->count());
    }

    public function test_accept_rejects_email_mismatch(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'intended@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('d', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($other)
            ->post("/invitations/{$invitation->token}/accept")
            ->assertSessionHasErrors('email');

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_accept_stashes_token_when_unauthenticated(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'guest@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('e', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->post("/invitations/{$invitation->token}/accept")
            ->assertRedirect('/login');

        $this->assertSame($invitation->token, session('pending_invitation_token'));
    }

    public function test_accept_is_idempotent_when_already_a_member(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $invited = User::factory()->create(['email' => 'already@example.com']);
        $tenant->users()->attach($invited->id, ['role' => Tenant::ROLE_MEMBER, 'joined_at' => now()->subDay()]);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'already@example.com',
            'role' => Tenant::ROLE_ADMIN,                      // upgrade
            'token' => str_repeat('f', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($invited)->post("/invitations/{$invitation->token}/accept")->assertRedirect();

        // Still one pivot row, but role got upgraded.
        $this->assertSame(1, $tenant->users()->where('users.id', $invited->id)->count());
        $this->assertSame(Tenant::ROLE_ADMIN, $tenant->users()->where('users.id', $invited->id)->first()->pivot->role);
    }

    public function test_revoke_deletes_pending_invitation(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        app(TenantContext::class)->set($tenant);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'revoked@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('g', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)
            ->delete("/workspace/team/invitations/{$invitation->id}")
            ->assertRedirect('/workspace/team');

        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
    }

    public function test_cannot_revoke_invitation_from_another_tenant(): void
    {
        [$tenantA, $ownerA] = $this->tenantWithOwner();
        [$tenantB, $ownerB] = $this->tenantWithOwner();
        app(TenantContext::class)->set($tenantA);

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenantB->id,
            'invited_by_id' => $ownerB->id,
            'email' => 'cross@example.com',
            'role' => Tenant::ROLE_MEMBER,
            'token' => str_repeat('h', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($ownerA)
            ->delete("/workspace/team/invitations/{$invitation->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
        $tenant->users()->attach($owner->id, ['role' => Tenant::ROLE_OWNER, 'joined_at' => now()]);

        return [$tenant, $owner];
    }
}
