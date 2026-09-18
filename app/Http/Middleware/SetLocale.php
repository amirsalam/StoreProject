<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales supported by the storefront UI.
     */
    public const SUPPORTED = ['en', 'ar', 'fr', 'es'];

    /**
     * Locales that render right-to-left.
     */
    public const RTL = ['ar'];

    public const COOKIE_NAME = 'app_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        return $next($request);
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function direction(string $locale): string
    {
        return in_array($locale, self::RTL, true) ? 'rtl' : 'ltr';
    }

    private function resolveLocale(Request $request): string
    {
        $cookie = $request->cookie(self::COOKIE_NAME);
        if (is_string($cookie) && self::isSupported($cookie)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);
        if ($preferred && self::isSupported($preferred)) {
            return $preferred;
        }

        return config('app.locale', 'en');
    }
}
