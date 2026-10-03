<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-password 2FA challenge.
 *
 * AuthenticatedSessionController.store stashes the user id into the
 * session under `auth.2fa.user_id` when 2FA is enabled, then redirects
 * here. The user enters a TOTP code (or a recovery code) and is logged
 * in on success. The pending marker is cleared either way.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'auth.2fa.user_id';
    public const SESSION_REMEMBER_KEY = 'auth.2fa.remember';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $code = (string) $request->input('code', '');
        $recovery = (string) $request->input('recovery_code', '');

        $passed = false;
        $usedRecovery = false;

        if ($code !== '') {
            $passed = $this->twoFactor->verifyTotp($user, $code);
        } elseif ($recovery !== '') {
            $passed = $this->twoFactor->consumeRecoveryCode($user, $recovery);
            $usedRecovery = $passed;
        } else {
            throw ValidationException::withMessages([
                'code' => __('Enter a 6-digit code or a recovery code.'),
            ]);
        }

        if (! $passed) {
            ActivityLog::record('2fa.challenge.failed', $user, [
                'used_recovery' => $recovery !== '',
            ]);
            throw ValidationException::withMessages([
                'code' => __('That code is invalid. Try again.'),
            ]);
        }

        $remember = (bool) $request->session()->pull(self::SESSION_REMEMBER_KEY, false);
        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        ActivityLog::record('2fa.challenge.passed', $user, [
            'used_recovery' => $usedRecovery,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id) {
            return null;
        }
        return User::find($id);
    }
}
