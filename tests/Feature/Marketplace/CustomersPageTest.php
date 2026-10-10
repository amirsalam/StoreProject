<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_page_renders_for_guests(): void
    {
        $this->get(route('customers'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers')
                ->where('translations.customers.title', trans('messages.customers.title', [], 'en'))
                // The page reuses the home page's testimonial copy.
                ->has('translations.testimonials.items')
            );
    }

    public function test_customers_page_is_translated_per_locale(): void
    {
        $this->withHeader('Accept-Language', 'ar')
            ->get(route('customers'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
                ->where('translations.customers.eyebrow', 'العملاء')
            );
    }

    public function test_every_locale_defines_the_same_customers_keys(): void
    {
        $expected = $this->flattenKeys(trans('messages.customers', [], 'en'));
        $this->assertNotEmpty($expected);

        foreach (SetLocale::SUPPORTED as $locale) {
            // fallback=false: a missing block must not silently resolve to en
            $this->assertSame(
                $expected,
                $this->flattenKeys(trans()->get('messages.customers', [], $locale, false)),
                "lang/{$locale}/messages.php 'customers' keys differ from en",
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
