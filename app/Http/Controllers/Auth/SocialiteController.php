<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialiteService;
use App\Services\SocialLoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;

class SocialiteController extends Controller
{
    public const LABELS = ['google' => 'Google', 'github' => 'GitHub'];

    public function __construct(
        private readonly SocialiteService $socialite,
        private readonly SocialLoginSettings $settings,
    ) {}

    /**
     * Kick off the OAuth handshake. We use the regular stateful redirect
     * (no `stateless()`) so CSRF is enforced on the callback via the
     * session-stored state token.
     */
    public function redirect(string $provider): Response
    {
        abort_unless(SocialiteService::isSupported($provider), 404);

        if (! $this->settings->isAvailable($provider)) {
            return $this->unavailable($provider);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        abort_unless(SocialiteService::isSupported($provider), 404);

        if (! $this->settings->isAvailable($provider)) {
            return $this->unavailable($provider);
        }

        // The user can deny consent on the provider's screen. Surface a
        // friendly redirect instead of a raw exception.
        if ($request->boolean('error') || $request->has('error_description')) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'social' => __('Sign-in with :provider was cancelled.', ['provider' => self::LABELS[$provider]]),
                ]);
        }

        try {
            $payload = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            // Session state token mismatch — usually a stale tab. Restart.
            return redirect()->route('login')->withErrors([
                'social' => __('The sign-in session expired. Please try again.'),
            ]);
        } catch (\Throwable $e) {
            // Wrong client secret, revoked app, provider outage…
            report($e);

            return redirect()->route('login')->withErrors([
                'social' => __('Sign-in with :provider failed. Please try again or use your email and password.', ['provider' => self::LABELS[$provider]]),
            ]);
        }

        $user = $this->socialite->findOrCreateUser($provider, $payload);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Switched off or missing credentials in Admin → Social login.
     */
    private function unavailable(string $provider): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'social' => __('Sign-in with :provider is not available right now.', ['provider' => self::LABELS[$provider]]),
        ]);
    }
}
