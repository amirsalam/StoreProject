<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_a_redirect_to_the_provider(): void
    {
        // Don't follow the redirect — we just want to confirm it was issued.
        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect();
        $this->assertStringContainsString(
            'accounts.google.com',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->get('/auth/twitter/redirect')->assertNotFound();
        $this->get('/auth/twitter/callback')->assertNotFound();
    }

    public function test_callback_creates_a_new_user_and_logs_them_in(): void
    {
        $this->mockSocialite('google', 'gid-123', 'new@example.com', 'New User');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at, 'Social signups should be pre-verified');
        $this->assertAuthenticatedAs($user);

        $account = SocialAccount::query()->where('provider', 'google')->first();
        $this->assertNotNull($account);
        $this->assertSame('gid-123', $account->provider_id);
        $this->assertSame($user->id, $account->user_id);
    }

    public function test_callback_links_to_an_existing_email_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => null,
        ]);

        $this->mockSocialite('github', 'ghid-77', 'existing@example.com', 'Existing User');

        $this->get('/auth/github/callback')->assertRedirect();

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(
            1,
            SocialAccount::query()->where('user_id', $existing->id)->where('provider', 'github')->count(),
        );
        $this->assertSame(
            1,
            User::query()->where('email', 'existing@example.com')->count(),
            'No duplicate user should be created when an email match exists',
        );
    }

    public function test_repeated_callback_is_idempotent(): void
    {
        $this->mockSocialite('google', 'gid-stable', 'me@example.com', 'Me');
        $this->get('/auth/google/callback');

        $this->app->forgetInstance(Provider::class);

        // Log out so the second visit is treated as fresh.
        auth()->logout();
        $this->mockSocialite('google', 'gid-stable', 'me@example.com', 'Me');
        $this->get('/auth/google/callback');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, SocialAccount::query()->count());
    }

    public function test_provider_denial_redirects_back_to_login_with_error(): void
    {
        $this->get('/auth/google/callback?error=access_denied&error_description=denied')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    /**
     * Wire up a fake Socialite driver that returns a deterministic user
     * payload for the given provider.
     */
    private function mockSocialite(string $provider, string $id, ?string $email, ?string $name): void
    {
        $abstract = new SocialiteUser();
        $abstract->id = $id;
        $abstract->name = $name;
        $abstract->email = $email;
        $abstract->avatar = 'https://example.com/avatar.png';
        $abstract->user = ['raw' => 'payload'];

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('redirect')->andReturn(redirect("https://accounts.google.com/o/oauth2/auth?provider={$provider}"));
        $driver->shouldReceive('user')->andReturn($abstract);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }
}
