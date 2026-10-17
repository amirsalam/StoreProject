<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\SocialLoginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class SocialLoginSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        // Start from an empty .env for both providers.
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function save(array $google = [], array $github = []): TestResponse
    {
        return $this->actingAs($this->admin())->put('/admin/social-login', [
            'google' => $google + ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            'github' => $github + ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
        ]);
    }

    public function test_only_admins_can_open_the_page(): void
    {
        $this->get('/admin/social-login')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/social-login')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->put('/admin/social-login', [])->assertForbidden();
    }

    public function test_the_page_shows_the_callback_urls_and_never_the_secret(): void
    {
        $this->save(['enabled' => true, 'client_id' => 'gid.apps.googleusercontent.com', 'client_secret' => 'GOCSPX-secret']);

        $this->actingAs($this->admin())
            ->get('/admin/social-login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/social-login/edit')
                ->where('settings.google.client_id', 'gid.apps.googleusercontent.com')
                ->where('settings.google.has_secret', true)
                ->where('settings.google.callback_url', url('/auth/google/callback'))
                ->where('settings.github.callback_url', url('/auth/github/callback'))
                ->missing('settings.google.client_secret'))
            ->assertDontSee('GOCSPX-secret');
    }

    public function test_the_secret_is_stored_encrypted_and_kept_when_left_empty(): void
    {
        $this->save(['enabled' => true, 'client_id' => 'gid', 'client_secret' => 'GOCSPX-secret'])->assertSessionHasNoErrors();

        $raw = Setting::get('social.google.client_secret');
        $this->assertNotSame('GOCSPX-secret', $raw);

        // Saving again without a secret keeps the stored one.
        $this->save(['enabled' => true, 'client_id' => 'gid-2', 'client_secret' => ''])->assertSessionHasNoErrors();
        $this->assertSame($raw, Setting::get('social.google.client_secret'));
        $this->assertTrue(app(SocialLoginSettings::class)->isAvailable('google'));
    }

    public function test_switching_on_requires_an_id_and_a_secret(): void
    {
        $this->save(['enabled' => true])
            ->assertSessionHasErrors(['google.client_id', 'google.client_secret']);

        $this->save(['enabled' => true, 'client_id' => 'has space', 'client_secret' => 'x'])
            ->assertSessionHasErrors('google.client_id');

        // Switched off, empty fields are fine.
        $this->save()->assertSessionHasNoErrors();
    }

    public function test_dot_env_credentials_are_enough_to_switch_on(): void
    {
        config(['services.github.client_id' => 'env-id', 'services.github.client_secret' => 'env-secret']);

        $this->save([], ['enabled' => true])->assertSessionHasNoErrors();
        $this->assertSame(['github'], app(SocialLoginSettings::class)->available());
    }

    public function test_login_and_register_pages_only_offer_configured_providers(): void
    {
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('socialProviders', []));

        $this->save(['enabled' => true, 'client_id' => 'gid', 'client_secret' => 'gsecret']);
        auth()->logout();

        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('socialProviders', ['google']));
        $this->get('/register')->assertInertia(fn (AssertableInertia $page) => $page->where('socialProviders', ['google']));

        // Switched off again → gone, even though credentials stay saved.
        $this->save(['enabled' => false, 'client_id' => 'gid']);
        auth()->logout();
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('socialProviders', []));
    }

    public function test_an_unconfigured_provider_redirects_back_with_a_message(): void
    {
        $this->get('/auth/google/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_saved_credentials_are_used_for_the_provider_redirect(): void
    {
        $this->save(['enabled' => true, 'client_id' => 'dashboard-google-id', 'client_secret' => 'dashboard-secret']);
        auth()->logout();
        Socialite::clearResolvedInstances();
        $this->app->forgetInstance(Factory::class);

        $location = (string) $this->get('/auth/google/redirect')->assertRedirect()->headers->get('Location');

        $this->assertStringContainsString('accounts.google.com', $location);
        $this->assertStringContainsString('client_id=dashboard-google-id', $location);
        $this->assertStringContainsString(urlencode(url('/auth/google/callback')), $location);
    }
}
