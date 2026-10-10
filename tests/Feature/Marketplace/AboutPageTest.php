<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_for_guests(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('about')
                ->where('translations.about.title', trans('messages.about.title', [], 'en'))
            );
    }

    public function test_about_page_is_translated_per_locale(): void
    {
        $this->withHeader('Accept-Language', 'ar')
            ->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
                ->where('translations.about.eyebrow', 'من نحن')
            );
    }

    public function test_every_locale_defines_the_same_about_keys(): void
    {
        $expected = $this->flattenKeys(trans('messages.about', [], 'en'));
        $this->assertNotEmpty($expected);

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->assertSame(
                $expected,
                // fallback=false: a missing block must not silently resolve to en
                $this->flattenKeys(trans()->get('messages.about', [], $locale, false)),
                "lang/{$locale}/messages.php 'about' keys differ from en",
            );
        }
    }

    /** @return list<string> */
    private function flattenKeys(mixed $node, string $prefix = ''): array
    {
        if (! is_array($node)) {
            return [$prefix];
        }

        $keys = [];
        foreach ($node as $key => $child) {
            array_push($keys, ...$this->flattenKeys($child, ltrim("{$prefix}.{$key}", '.')));
        }
        sort($keys);

        return $keys;
    }
}
