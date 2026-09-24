<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The legal pages ship as unreviewed outlines: section bodies are drafting
 * notes, not policy. These tests hold that line — every document keeps its
 * draft warning and stays out of search results while that is true.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function legalDocuments(): array
    {
        return [
            // route name, page component, section count
            'terms' => ['terms', 'legal/terms', 13],
            'privacy' => ['privacy', 'legal/privacy', 16],
        ];
    }

    #[DataProvider('legalDocuments')]
    public function test_the_page_renders_with_its_sections(string $routeName, string $component, int $sectionCount): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has("translations.legal.{$routeName}.sections", $sectionCount)
                ->where("translations.legal.{$routeName}.title", trans("messages.legal.{$routeName}.title", [], 'en'))
            );
    }

    #[DataProvider('legalDocuments')]
    public function test_the_draft_warning_is_present(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('translations.legal.draft_banner.title')
                ->has('translations.legal.draft_banner.body')
            );
    }

    /**
     * An unreviewed legal page must not be indexable. Drop this test in the
     * same change that publishes real wording and removes the meta tag.
     */
    #[DataProvider('legalDocuments')]
    public function test_the_page_is_not_indexable_while_it_is_a_draft(string $routeName, string $component): void
    {
        $source = file_get_contents(resource_path("js/pages/{$component}.tsx"));
        $shared = file_get_contents(resource_path('js/pages/legal/legal-document.tsx'));

        $this->assertTrue(
            str_contains($source.$shared, 'name="robots" content="noindex"'),
            "{$component} must send robots: noindex while it holds placeholder wording",
        );
    }

    #[DataProvider('legalDocuments')]
    public function test_every_section_has_an_id_title_and_body_in_every_locale(string $routeName, string $component, int $sectionCount): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            // fallback=false: a missing block must not silently resolve to en
            $sections = trans()->get("messages.legal.{$routeName}.sections", [], $locale, false);

            $this->assertIsArray($sections, "lang/{$locale}/messages.php has no {$routeName} sections");
            $this->assertCount($sectionCount, $sections, "lang/{$locale}/messages.php has a different section count");

            foreach ($sections as $i => $section) {
                foreach (['id', 'title', 'body'] as $key) {
                    $this->assertArrayHasKey($key, $section, "locale {$locale}, section {$i} is missing '{$key}'");
                    $this->assertNotSame('', trim((string) $section[$key]));
                }
            }
        }
    }

    #[DataProvider('legalDocuments')]
    public function test_section_ids_match_across_locales(string $routeName): void
    {
        $expected = array_column(trans("messages.legal.{$routeName}.sections", [], 'en'), 'id');

        foreach (SetLocale::SUPPORTED as $locale) {
            $ids = array_column(trans()->get("messages.legal.{$routeName}.sections", [], $locale, false), 'id');
            $this->assertSame($expected, $ids, "lang/{$locale}/messages.php {$routeName} section ids differ from en");
        }
    }
}
