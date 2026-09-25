<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_sessions_page(): void
    {
        $this->get('/settings/sessions')->assertRedirect('/login');
    }

    public function test_user_sees_their_own_sessions(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, id: 'foreign-session-id');
        $this->seedSession(User::factory()->create(), id: 'other-user-session');

        $this->actingAs($user)
            ->get('/settings/sessions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/sessions')
                ->where('sessions.0.id', fn ($id) => is_string($id))
            );
    }

    public function test_user_can_revoke_another_session(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, id: 'revoke-me');

        $this->actingAs($user)
            ->delete('/settings/sessions/revoke-me')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'revoke-me']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'session.revoked',
        ]);
    }

    public function test_user_cannot_revoke_a_session_belonging_to_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->seedSession($other, id: 'not-mine');

        $this->actingAs($user)->delete('/settings/sessions/not-mine');

        // Other user's session should still exist (the query filters by user_id).
        $this->assertDatabaseHas('sessions', ['id' => 'not-mine']);
    }

    public function test_destroy_other_requires_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);

        $this->actingAs($user)
            ->post('/settings/sessions/destroy-other', ['password' => 'wrong-pw'])
            ->assertSessionHasErrors('password');
    }

    public function test_destroy_other_clears_other_sessions(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $this->seedSession($user, id: 'session-a');
        $this->seedSession($user, id: 'session-b');

        $this->actingAs($user)
            ->post('/settings/sessions/destroy-other', ['password' => 'correct-pw'])
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-b']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'session.revoked_others',
        ]);
    }

    private function seedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit Chrome/120.0',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }
}
