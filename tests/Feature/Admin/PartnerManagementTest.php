<?php

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guests_and_non_admins_cannot_manage_partners(): void
    {
        $this->get('/admin/partners')->assertRedirect('/login');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin/partners')->assertForbidden();
        $this->actingAs($user)->post('/admin/partners', ['name' => 'Nope'])->assertForbidden();
        $this->assertDatabaseMissing('partners', ['name' => 'Nope']);
    }

    public function test_the_migration_seeds_the_original_logo_strip(): void
    {
        $this->assertSame(
            ['Laravel', 'Stripe', 'Inertia', 'Tailwind', 'Paddle', 'Cloudflare'],
            Partner::visible()->pluck('name')->all(),
        );
    }

    public function test_admins_see_partners_in_order(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/partners')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/partners/index')
                ->has('partners', 6)
                ->where('partners.0.name', 'Laravel')
                ->where('partners.5.name', 'Cloudflare'));
    }

    public function test_a_partner_can_be_created_with_a_logo(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/partners', [
                'name' => 'Acme',
                'website_url' => 'https://acme.test',
                'is_active' => '1',
                'logo' => UploadedFile::fake()->image('acme.png', 300, 100),
            ])
            ->assertRedirect('/admin/partners')
            ->assertSessionHas('success');

        $partner = Partner::where('name', 'Acme')->firstOrFail();
        $this->assertTrue($partner->is_active);
        $this->assertSame('https://acme.test', $partner->website_url);
        $this->assertStringStartsWith('partners/', $partner->logo_path);
        Storage::disk('public')->assertExists($partner->logo_path);
        // New partners go to the end of the strip.
        $this->assertSame(Partner::max('sort_order'), $partner->sort_order);
    }

    public function test_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/partners', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/admin/partners', ['name' => str_repeat('A', 81)])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/admin/partners', ['name' => 'X', 'website_url' => 'javascript:alert(1)'])->assertSessionHasErrors('website_url');
        $this->actingAs($admin)->post('/admin/partners', [
            'name' => 'X',
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('logo');
        $this->actingAs($admin)->post('/admin/partners', [
            'name' => 'X',
            'logo' => UploadedFile::fake()->image('big.png')->size(3000),
        ])->assertSessionHasErrors('logo');
    }

    public function test_a_partner_can_be_updated_and_its_logo_replaced_or_removed(): void
    {
        $admin = $this->admin();
        $partner = Partner::where('name', 'Stripe')->firstOrFail();

        $this->actingAs($admin)->put("/admin/partners/{$partner->id}", [
            'name' => 'Stripe Inc.',
            'website_url' => 'https://stripe.com',
            'is_active' => '1',
            'logo' => UploadedFile::fake()->image('a.png'),
        ])->assertRedirect('/admin/partners');

        $first = $partner->fresh()->logo_path;
        $this->assertSame('Stripe Inc.', $partner->fresh()->name);
        Storage::disk('public')->assertExists($first);

        // Replacing deletes the old file.
        $this->actingAs($admin)->put("/admin/partners/{$partner->id}", [
            'name' => 'Stripe Inc.',
            'is_active' => '1',
            'logo' => UploadedFile::fake()->image('b.png'),
        ]);
        $second = $partner->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Removing clears it.
        $this->actingAs($admin)->put("/admin/partners/{$partner->id}", [
            'name' => 'Stripe Inc.',
            'is_active' => '0',
            'remove_logo' => '1',
        ]);
        $partner->refresh();
        $this->assertNull($partner->logo_path);
        $this->assertFalse($partner->is_active);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_a_partner_can_be_deleted_with_its_logo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/partners', [
            'name' => 'Gone',
            'logo' => UploadedFile::fake()->image('gone.png'),
        ]);
        $partner = Partner::where('name', 'Gone')->firstOrFail();

        $this->actingAs($admin)->delete("/admin/partners/{$partner->id}")
            ->assertRedirect('/admin/partners');

        $this->assertModelMissing($partner);
        Storage::disk('public')->assertMissing($partner->logo_path);
    }

    public function test_partners_can_be_hidden_and_reordered(): void
    {
        $admin = $this->admin();
        $stripe = Partner::where('name', 'Stripe')->firstOrFail();

        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/toggle");
        $this->assertFalse($stripe->fresh()->is_active);
        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/toggle");
        $this->assertTrue($stripe->fresh()->is_active);

        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/move", ['direction' => 'up']);
        $this->assertSame(['Stripe', 'Laravel', 'Inertia'], Partner::visible()->limit(3)->pluck('name')->all());

        // Already first: moving up again changes nothing.
        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/move", ['direction' => 'up']);
        $this->assertSame('Stripe', Partner::visible()->value('name'));

        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/move", ['direction' => 'down']);
        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/move", ['direction' => 'down']);
        $this->assertSame(['Laravel', 'Inertia', 'Stripe'], Partner::visible()->limit(3)->pluck('name')->all());

        $this->actingAs($admin)->post("/admin/partners/{$stripe->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }

    public function test_the_homepage_shows_only_visible_partners_in_order(): void
    {
        Partner::where('name', 'Paddle')->update(['is_active' => false]);
        $logo = Partner::where('name', 'Laravel')->firstOrFail();
        $logo->update(['logo_path' => 'partners/laravel.svg', 'website_url' => 'https://laravel.com']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('welcome')
                ->has('partners', 5)
                ->where('partners.0.name', 'Laravel')
                ->where('partners.0.website_url', 'https://laravel.com')
                ->where('partners.0.logo_url', Storage::disk('public')->url('partners/laravel.svg'))
                ->where('partners.4.name', 'Cloudflare')
                ->missing('partners.0.logo_path'));
    }

    public function test_the_homepage_strip_is_empty_when_all_partners_are_removed(): void
    {
        Partner::query()->delete();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('partners', 0));
    }
}
