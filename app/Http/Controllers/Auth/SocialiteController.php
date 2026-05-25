<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;

class SocialiteController extends Controller
{
    public function __construct(private readonly SocialiteService $socialite) {}

    /**
     * Kick off the OAuth handshake. We use the regular stateful redirect
     * (no `stateless()`) so CSRF is enforced on the callback via the
     * session-stored state token.
     */
    public function redirect(string $provider): Response
    {
        abort_unless(SocialiteService::isSupported($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        abort_unless(SocialiteService::isSupported($provider), 404);

        // The user can deny consent on the provider's screen. Surface a
        // friendly redirect instead of a raw exception.
        if ($request->boolean('error') || $request->has('error_description')) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'social' => 'Sign-in with ' . ucfirst($provider) . ' was cancelled.',
                ]);
        }

        try {
            $payload = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            // Session state token mismatch — usually a stale tab. Restart.
            return redirect()->route('login')->withErrors([
                'social' => 'The sign-in session expired. Please try again.',
            ]);
        }

        $user = $this->socialite->findOrCreateUser($provider, $payload);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
