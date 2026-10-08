<?php

namespace Tests\Feature\Settings;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_an_auth_login_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pw')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pw'])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_failed_login_records_an_event_with_no_user(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => bcrypt('right')]);

        $this->post('/login', ['email' => 'real@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $row = ActivityLog::query()->where('event', 'auth.login.failed')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
        $this->assertSame('real@example.com', $row->properties['email']);
    }

    public function test_logout_records_an_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'auth.logout',
        ]);
    }

    public function test_registration_records_an_event(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'super-secret-pw',
            'password_confirmation' => 'super-secret-pw',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'auth.registered',
        ]);
    }

    public function test_password_change_records_an_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-pw-1234')]);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'old-pw-1234',
            'password' => 'new-strong-pw-1234',
            'password_confirmation' => 'new-strong-pw-1234',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => 'password.changed',
        ]);
    }

    public function test_activity_page_shows_only_the_users_own_events(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        ActivityLog::record('auth.login', $me);
        ActivityLog::record('auth.login', $other);
        ActivityLog::record('password.changed', $me);

        $this->actingAs($me)
            ->get('/settings/activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/activity')
                ->has('entries', 2)
            );
    }
}
