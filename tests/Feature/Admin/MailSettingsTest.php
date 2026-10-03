<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin → Email: SMTP configured from the dashboard instead of .env.
 */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_smtp_settings_with_an_encrypted_password(): void
    {
        $this->actingAs($this->admin())->put(route('admin.mail.update'), $this->valid())
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $stored = (string) Setting::get('mail.password');
        $this->assertNotSame('app-password-123', $stored);
        $this->assertSame('app-password-123', Crypt::decryptString($stored));
        $this->assertSame('smtp.gmail.com', Setting::get('mail.host'));
    }

    public function test_the_page_never_receives_the_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.mail.update'), $this->valid());

        $response = $this->actingAs($admin)->get(route('admin.mail.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/mail/edit')
                ->where('settings.has_password', true)
                ->where('settings.host', 'smtp.gmail.com')
                ->missing('settings.password')
            );

        $this->assertStringNotContainsString('app-password-123', $response->getContent());
    }

    public function test_a_blank_password_keeps_the_saved_one(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.mail.update'), $this->valid());
        $this->actingAs($admin)->put(route('admin.mail.update'), [...$this->valid(), 'password' => '', 'from_name' => 'Renamed']);

        $this->assertSame('app-password-123', Crypt::decryptString((string) Setting::get('mail.password')));
        $this->assertSame('Renamed', Setting::get('mail.from_name'));
    }

    public function test_enabled_settings_drive_the_mailer_and_disabled_ones_leave_env_alone(): void
    {
        config(['mail.default' => 'log']);
        app(MailSettings::class)->save([...$this->valid(), 'enabled' => false]);
        $this->freshMailer();
        $this->assertSame('log', config('mail.default'));

        app(MailSettings::class)->save($this->valid());
        $this->freshMailer();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('you@gmail.com', config('mail.mailers.smtp.username'));
        $this->assertSame('app-password-123', config('mail.mailers.smtp.password'));
        $this->assertSame('orders@example.org', config('mail.from.address'));
    }

    public function test_enabling_requires_server_port_and_sender(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.mail.update'), ['enabled' => true, 'encryption' => 'tls'])
            ->assertSessionHasErrors(['host', 'port', 'from_address']);
    }

    public function test_the_test_email_needs_saved_enabled_settings(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.mail.test'), ['to' => 'me@example.org'])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'save first'));
    }

    public function test_a_failing_smtp_server_reports_its_error(): void
    {
        $admin = $this->admin();
        // Nothing listens on port 1: the connection is refused immediately.
        $this->actingAs($admin)->put(route('admin.mail.update'), [...$this->valid(), 'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none']);

        $this->actingAs($admin)
            ->post(route('admin.mail.test'), ['to' => 'me@example.org'])
            ->assertSessionHas('error', fn (string $m) => str_starts_with($m, 'The test email could not be sent:'));
    }

    public function test_non_admins_cannot_reach_email_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.mail.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.mail.update'), $this->valid())->assertForbidden();
    }

    private function freshMailer(): void
    {
        $this->app->forgetInstance('mail.manager');
        $this->app->make('mail.manager');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'enabled' => true,
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'you@gmail.com',
            'password' => 'app-password-123',
            'from_address' => 'orders@example.org',
            'from_name' => 'StoreProject',
        ];
    }
}
