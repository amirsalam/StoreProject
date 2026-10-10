<?php

namespace Tests\Feature\Resources;

use App\Domain\Licensing\LicenseActivationService;
use App\Domain\Status\SystemStatus;
use App\Http\Controllers\Resources\ApiReferenceController;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The footer's Resources column: docs and guides (marked draft outlines),
 * the API reference (built from the live routes) and the status page
 * (live checks). These tests keep the reference complete and the
 * status line honest.
 */
class ResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function draftDocuments(): array
    {
        return [
            // route name, page component, section count
            'docs' => ['docs', 'resources/docs', 9],
            'guides' => ['guides', 'resources/guides', 6],
        ];
    }

    #[DataProvider('draftDocuments')]
    public function test_draft_document_renders_with_its_sections_and_draft_banner(string $routeName, string $component, int $sectionCount): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has("translations.help.{$routeName}.sections", $sectionCount)
                ->has('translations.help.draft_banner.title')
                ->has('translations.help.draft_banner.body')
            );
    }

    #[DataProvider('draftDocuments')]
    public function test_draft_document_is_not_indexable(string $routeName, string $component): void
    {
        $source = file_get_contents(resource_path("js/pages/{$component}.tsx"));
        $shell = file_get_contents(resource_path('js/components/draft-document.tsx'));

        $this->assertStringContainsString('DraftDocument', $source);
        $this->assertStringContainsString('name="robots" content="noindex"', $shell);
    }

    #[DataProvider('draftDocuments')]
    public function test_draft_document_sections_are_complete_in_every_locale(string $routeName, string $component, int $sectionCount): void
    {
        $expectedIds = array_column(trans("messages.help.{$routeName}.sections", [], 'en'), 'id');

        foreach (SetLocale::SUPPORTED as $locale) {
            $sections = trans()->get("messages.help.{$routeName}.sections", [], $locale, false);

            $this->assertIsArray($sections, "lang/{$locale} has no help.{$routeName} sections");
            $this->assertCount($sectionCount, $sections, "lang/{$locale} help.{$routeName} section count");
            $this->assertSame($expectedIds, array_column($sections, 'id'), "lang/{$locale} help.{$routeName} ids differ from en");

            foreach ($sections as $i => $section) {
                foreach (['title', 'body'] as $key) {
                    $this->assertNotSame('', trim((string) ($section[$key] ?? '')), "{$locale} {$routeName} section {$i} {$key}");
                }
            }
        }
    }

    public function test_api_reference_lists_every_v1_route(): void
    {
        $documented = collect(ApiReferenceController::GROUPS)->flatMap(fn (array $g) => array_keys($g['routes']))->all();

        $actual = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn (?string $name) => $name !== null && str_starts_with($name, 'api.v1.'))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($actual, $documented, 'Every api.v1.* route must appear in ApiReferenceController::GROUPS.');
    }

    public function test_api_reference_renders_routes_errors_and_example_from_code(): void
    {
        $this->get(route('api-reference'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resources/api-reference')
                ->where('groups.0.key', 'licenses')
                ->where('groups.0.endpoints.0.method', 'POST')
                ->where('groups.0.endpoints.0.path', '/api/v1/licenses/activate')
                ->where('groups.0.endpoints.1.method', 'GET')
                ->where('licenseErrors.0.code', 'invalid_license')
                ->where('licenseErrors.0.status', 404)
                ->where('licenseExample.data.domain', 'example.com')
                ->where('licenseExample.data.activations_remaining', 2)
                ->where('rateLimitPerMinute', LicenseActivationService::RATE_LIMIT_PER_MINUTE)
            );
    }

    public function test_api_reference_text_exists_in_every_locale(): void
    {
        $response = $this->get(route('api-reference'))->assertOk();
        $props = $response->viewData('page')['props'];

        foreach (SetLocale::SUPPORTED as $locale) {
            $has = fn (string $key) => is_string(trans()->get("messages.api_reference.{$key}", [], $locale, false));

            foreach ($props['groups'] as $group) {
                $this->assertTrue($has("groups.{$group['key']}.title"), "{$locale}: group {$group['key']}");
                foreach ($group['endpoints'] as $endpoint) {
                    $this->assertTrue($has("endpoints.{$endpoint['key']}"), "{$locale}: endpoint {$endpoint['key']}");
                    foreach ($endpoint['params'] as $param) {
                        $this->assertTrue($has("params.{$param['name']}"), "{$locale}: param {$param['name']}");
                    }
                }
            }

            foreach ($props['licenseErrors'] as $error) {
                $this->assertTrue($has("errors.codes.{$error['code']}"), "{$locale}: error {$error['code']}");
            }
        }
    }

    public function test_status_page_reports_live_component_checks(): void
    {
        $this->get(route('status'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resources/status')
                ->where('status.overall', SystemStatus::OPERATIONAL)
                ->has('status.components', 3)
                ->where('status.components.0.key', 'database')
                ->where('status.components.1.key', 'cache')
                ->where('status.components.2.key', 'queue')
                ->where('system_status', SystemStatus::OPERATIONAL)
            );
    }

    public function test_a_stalled_queue_degrades_the_status_and_the_footer(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subSeconds(SystemStatus::QUEUE_BACKLOG_SECONDS + 60)->getTimestamp(),
            'created_at' => now()->subSeconds(SystemStatus::QUEUE_BACKLOG_SECONDS + 60)->getTimestamp(),
        ]);

        $this->get(route('status'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status.overall', SystemStatus::DEGRADED)
                ->where('status.components.0.status', SystemStatus::OPERATIONAL)
                ->where('status.components.2.status', SystemStatus::DEGRADED)
                ->where('system_status', SystemStatus::DEGRADED)
            );
    }

    public function test_a_recent_pending_job_is_not_a_backlog(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->assertSame(SystemStatus::OPERATIONAL, app(SystemStatus::class)->check()['overall']);
    }

    public function test_footer_has_no_placeholder_links(): void
    {
        $layout = file_get_contents(resource_path('js/layouts/storefront-layout.tsx'));

        $this->assertStringNotContainsString("href: '#'", $layout);
    }
}
