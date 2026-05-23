<?php

namespace App\Http\Middleware;

use App\Services\BrandingService;
use App\Services\CartService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_admin' => (bool) $user->is_admin,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'cart' => app(CartService::class)->summary(),
            'branding' => app(BrandingService::class)->summary(),
            'locale' => App::getLocale(),
            'direction' => SetLocale::direction(App::getLocale()),
            'supportedLocales' => SetLocale::SUPPORTED,
            'translations' => fn () => $this->loadTranslations(App::getLocale()),
        ]);
    }

    /**
     * Load the message bag for the active locale, falling back to English
     * for any missing keys so the UI never renders a raw "key.path" string.
     *
     * @return array<string, mixed>
     */
    private function loadTranslations(string $locale): array
    {
        $messages = trans()->get('messages', [], $locale);
        if (! is_array($messages)) {
            $messages = [];
        }

        if ($locale === 'en') {
            return $messages;
        }

        $fallback = trans()->get('messages', [], 'en');

        return $this->mergeRecursive(is_array($fallback) ? $fallback : [], $messages);
    }

    /**
     * Deep-merge with the localized value winning over the fallback. Lists
     * (numeric-keyed arrays) are replaced wholesale so a locale can override
     * an entire array like testimonials/items without falling back to en.
     *
     * @param  array<int|string, mixed>  $base
     * @param  array<int|string, mixed>  $override
     * @return array<int|string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && $this->isAssoc($value)) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<int|string, mixed>  $arr
     */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
