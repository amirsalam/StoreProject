<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_renders_with_its_sections(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/terms')
                ->has('translations.legal.terms.sections', 13)
                ->where('translations.legal.terms.title', trans('messages.legal.terms.title', [], 'en'))
            );
    }

    /**
     * The page is an unreviewed outline, so it must carry the draft warning
     * for as long as it carries placeholder wording.
     */
    public function test_the_draft_warning_is_present(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('translations.legal.draft_banner.title')
                ->has('translations.legal.draft_banner.body')
            );
    }

    public function test_every_section_has_an_id_title_and_body_in_every_locale(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            // fallback=false: a missing block must not silently resolve to en
            $sections = trans()->get('messages.legal.terms.sections', [], $locale, false);

            $this->assertIsArray($sections, "lang/{$locale}/messages.php has no terms sections");
            $this->assertCount(13, $sections, "lang/{$locale}/messages.php has a different section count");

            foreach ($sections as $i => $section) {
                foreach (['id', 'title', 'body'] as $key) {
                    $this->assertArrayHasKey($key, $section, "locale {$locale}, section {$i} is missing '{$key}'");
                    $this->assertNotSame('', trim((string) $section[$key]));
                }
            }
        }
    }

    public function test_section_ids_match_across_locales(): void
    {
        $expected = array_column(trans('messages.legal.terms.sections', [], 'en'), 'id');

        foreach (SetLocale::SUPPORTED as $locale) {
            $ids = array_column(trans()->get('messages.legal.terms.sections', [], $locale, false), 'id');
            $this->assertSame($expected, $ids, "lang/{$locale}/messages.php section ids differ from en");
        }
    }
}
