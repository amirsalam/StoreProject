<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Setting::flushCache();
    }

    public function test_guests_are_redirected_from_admin_branding(): void
    {
        $this->get('/admin/branding')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin/branding')->assertForbidden();
    }

    public function test_admins_can_load_the_branding_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/branding')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('admin/branding/edit')
                    ->where('branding.title', BrandingService::DEFAULT_TITLE)
                    ->where('branding.has_custom_logo', false)
            );
    }

    public function test_title_can_be_updated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/branding', ['title' => 'Acme Marketplace'])
            ->assertRedirect('/admin/branding')
            ->assertSessionHas('success');

        $this->assertSame('Acme Marketplace', Setting::get(BrandingService::TITLE_KEY));
    }

    public function test_title_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => str_repeat('A', 61)])
            ->assertSessionHasErrors('title');
    }

    public function test_logo_upload_png(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('brand.png', 256, 256);

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => 'Acme', 'logo' => $file])
            ->assertRedirect('/admin/branding')
            ->assertSessionHas('success');

        $path = Setting::get(BrandingService::LOGO_KEY);
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('branding/logo-', $path);
        $this->assertStringEndsWith('.png', $path);
    }

    public function test_oversized_raster_is_downsized(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('huge.png', 1800, 1800);

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => 'Acme', 'logo' => $file])
            ->assertRedirect('/admin/branding');

        $path = Setting::get(BrandingService::LOGO_KEY);
        $bytes = Storage::disk('public')->get($path);
        $img = imagecreatefromstring($bytes);

        $this->assertNotFalse($img);
        $longest = max(imagesx($img), imagesy($img));
        $this->assertLessThanOrEqual(
            BrandingService::MAX_RASTER_DIMENSION,
            $longest,
            "Logo wasn't downsized below MAX_RASTER_DIMENSION (got {$longest}px)",
        );
    }

    public function test_logo_upload_svg_is_sanitized(): void
    {
        $admin = User::factory()->admin()->create();

        $hostile = <<<'SVG'
<?xml version="1.0"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" onload="alert(1)">
    <script>alert('xss')</script>
    <rect width="64" height="64" fill="purple"/>
    <a href="javascript:alert('x')"><rect width="10" height="10"/></a>
</svg>
SVG;

        $file = UploadedFile::fake()->createWithContent('brand.svg', $hostile);

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => 'Acme', 'logo' => $file])
            ->assertRedirect('/admin/branding');

        $path = Setting::get(BrandingService::LOGO_KEY);
        $contents = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('<script', $contents);
        $this->assertStringNotContainsString('onload=', $contents);
        $this->assertStringNotContainsString('javascript:', $contents);
        $this->assertStringNotContainsString('<!DOCTYPE', $contents);
        $this->assertStringContainsString('<rect', $contents); // safe content kept
    }

    public function test_uploading_new_logo_deletes_previous(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/branding', [
                'title' => 'Acme',
                'logo' => UploadedFile::fake()->image('first.png', 200, 200),
            ]);
        $first = Setting::get(BrandingService::LOGO_KEY);
        $this->assertNotNull($first);

        $this->actingAs($admin)
            ->post('/admin/branding', [
                'title' => 'Acme',
                'logo' => UploadedFile::fake()->image('second.webp', 200, 200),
            ]);
        $second = Setting::get(BrandingService::LOGO_KEY);

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_logo_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post('/admin/branding', [
                'title' => 'Acme',
                'logo' => UploadedFile::fake()->image('brand.png', 200, 200),
            ]);
        $path = Setting::get(BrandingService::LOGO_KEY);

        $this->actingAs($admin)
            ->delete('/admin/branding/logo')
            ->assertRedirect('/admin/branding')
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Setting::get(BrandingService::LOGO_KEY));
    }

    public function test_unsupported_file_types_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $bad = UploadedFile::fake()->create('payload.php', 10, 'application/x-php');

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => 'Acme', 'logo' => $bad])
            ->assertSessionHasErrors('logo');
    }

    public function test_oversized_logo_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        // create returns a file with kB-controlled size — 4 MB exceeds the 2 MB cap
        $big = UploadedFile::fake()->create('huge.png', 4096, 'image/png');

        $this->actingAs($admin)
            ->post('/admin/branding', ['title' => 'Acme', 'logo' => $big])
            ->assertSessionHasErrors('logo');
    }

    public function test_branding_is_shared_via_inertia(): void
    {
        Setting::put(BrandingService::TITLE_KEY, 'Shared Brand');

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('branding.title', 'Shared Brand')
                    ->where('branding.has_custom_logo', false)
            );
    }

    public function test_non_admins_cannot_mutate_or_delete(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post('/admin/branding', ['title' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete('/admin/branding/logo')->assertForbidden();
    }
}
