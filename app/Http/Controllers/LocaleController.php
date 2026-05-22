<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', SetLocale::SUPPORTED)],
        ]);

        return back()->withCookie(
            Cookie::make(
                name: SetLocale::COOKIE_NAME,
                value: $data['locale'],
                minutes: 60 * 24 * 365, // 1 year
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: false,
                sameSite: 'Lax',
            )
        );
    }
}
