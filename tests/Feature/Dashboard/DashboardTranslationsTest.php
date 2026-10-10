<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The dashboard is translated with Laravel JSON phrases (lang/{locale}.json,
 * English text as the key): __('…') in React and PHP. Every phrase in the
 * source must have an Arabic, French and Spanish translation.
 */
class DashboardTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['ar', 'fr', 'es'];

    public function test_every_dashboard_phrase_is_translated(): void
    {
        $phrases = $this->sourcePhrases();
        $this->assertGreaterThan(300, count($phrases), 'Phrase scan found suspiciously few phrases.');

        foreach (self::LOCALES as $locale) {
            $translations = json_decode((string) file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            $missing = array_values(array_filter($phrases, fn (string $p) => ! isset($translations[$p]) || $translations[$p] === ''));
            $this->assertSame([], $missing, "Missing {$locale} translations in lang/{$locale}.json");

            foreach ($translations as $english => $translated) {
                $this->assertSame(
                    $this->placeholders($english),
                    $this->placeholders($translated),
                    "Placeholder mismatch in lang/{$locale}.json for \"{$english}\"",
                );
            }
        }
    }

    public function test_dashboard_pages_receive_the_phrases_for_the_active_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'ar')
            ->get('/settings/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('phrases.Settings', 'الإعدادات')
                ->where('phrases.Dashboard', 'لوحة التحكم'));

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en')
            ->get('/settings/profile')
            ->assertInertia(fn (Assert $page) => $page->where('phrases', []));
    }

    public function test_server_messages_are_translated(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Tâche créée.', __('Task created.'));
        $this->assertSame('Passerelle « Stripe » créée.', __('Gateway ":name" created.', ['name' => 'Stripe']));
    }

    /**
     * Every literal passed to __() / __el() in resources/js and app/, plus
     * the titles/labels of module-level nav and breadcrumb arrays (rendered
     * through __() by the shared components).
     *
     * @return list<string>
     */
    private function sourcePhrases(): array
    {
        $call = '/\b__(?:el)?\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
        $keyed = '/\b(?:title|label|hint): \'((?:[^\'\\\\]|\\\\.)*)\'/';

        $phrases = [];
        $scan = function (string $dir, array $patterns, bool $jsOnlyTranslated) use (&$phrases) {
            foreach (Finder::create()->files()->in($dir)->name(['*.ts', '*.tsx', '*.php']) as $file) {
                $lines = array_filter(
                    explode("\n", $file->getContents()),
                    fn (string $l) => ! preg_match('#^\s*(\*|//|/\*)#', $l),
                );
                $src = implode("\n", $lines);
                $isSidebar = str_contains($file->getFilename(), 'app-sidebar');
                if ($jsOnlyTranslated && ! str_contains($src, '__(') && ! $isSidebar) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $src, $m, PREG_SET_ORDER);
                    foreach ($m as $match) {
                        $text = preg_replace('/\\\\(.)/', '$1', ($match[2] ?? '') ?: $match[1]);
                        // Group-file keys (messages.foo) and interpolated PHP strings are not phrases.
                        if ($text === '' || preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/', $text) || str_contains($text, '{$')) {
                            continue;
                        }
                        $phrases[$text] = true;
                    }
                }
            }
        };

        $scan(resource_path('js'), [$call, $keyed], true);
        $scan(app_path(), [$call], false);

        $list = array_keys($phrases);
        sort($list);

        return $list;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:\w+/', $text, $m);
        $found = $m[0];
        sort($found);

        return $found;
    }
}
