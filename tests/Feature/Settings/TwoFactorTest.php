<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_page_loads(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/two-factor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/two-factor')
                ->where('enabled', false)
                ->where('pending', false)
            );
    }

    public function test_enabling_generates_secret_and_recovery_codes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/two-factor')->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertIsArray($user->two_factor_recovery_codes);
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at); // pending until confirm
    }

    public function test_confirming_with_valid_code_activates_2fa(): void
    {
        $user = User::factory()->create();
        app(TwoFactorService::class)->enable($user);

        $code = (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret);

        $this->actingAs($user)
            ->post('/settings/two-factor/confirm', ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => '2fa.enabled',
        ]);
    }

    public function test_confirming_with_invalid_code_fails(): void
    {
        $user = User::factory()->create();
        app(TwoFactorService::class)->enable($user);

        $this->actingAs($user)
            ->post('/settings/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_requires_password_and_clears_state(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        // Wrong password rejected
        $this->actingAs($user)
            ->delete('/settings/two-factor', ['password' => 'nope'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        // Correct password disables
        $this->actingAs($user)
            ->delete('/settings/two-factor', ['password' => 'correct-pw'])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_login_redirects_to_challenge_when_2fa_enabled(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-pw',
        ]);

        $response->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_challenge_signs_user_in_with_valid_totp(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        // Step 1: primary login redirects to challenge
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-pw'])
            ->assertRedirect('/two-factor-challenge');

        // Step 2: submit TOTP
        $otp = (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret);

        $this->post('/two-factor-challenge', ['code' => $otp])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => '2fa.challenge.passed',
        ]);
    }

    public function test_recovery_code_is_consumed_on_use(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        $code = $user->fresh()->two_factor_recovery_codes[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-pw'])
            ->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge', ['recovery_code' => $code])
            ->assertRedirect('/dashboard');

        $remaining = $user->fresh()->two_factor_recovery_codes;
        $this->assertNotContains($code, $remaining);
        $this->assertCount(7, $remaining);
    }

    public function test_challenge_rejects_invalid_code(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-pw'])
            ->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_regenerating_recovery_codes_requires_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pw')]);
        $svc = app(TwoFactorService::class);
        $svc->enable($user);
        $svc->confirm($user, (new Google2FA())->getCurrentOtp($user->fresh()->two_factor_secret));

        $original = $user->fresh()->two_factor_recovery_codes;

        $this->actingAs($user)
            ->post('/settings/two-factor/recovery-codes', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertSame($original, $user->fresh()->two_factor_recovery_codes);

        $this->actingAs($user)
            ->post('/settings/two-factor/recovery-codes', ['password' => 'correct-pw'])
            ->assertRedirect();

        $this->assertNotSame($original, $user->fresh()->two_factor_recovery_codes);
    }
}
