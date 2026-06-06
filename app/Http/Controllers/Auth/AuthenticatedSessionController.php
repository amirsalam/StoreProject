<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = Auth::user();

        // If 2FA is on, hold the session and force the challenge step.
        if ($user->hasTwoFactorEnabled()) {
            $remember = $request->boolean('remember');
            Auth::guard('web')->logout();
            $request->session()->put(TwoFactorChallengeController::SESSION_KEY, $user->id);
            $request->session()->put(TwoFactorChallengeController::SESSION_REMEMBER_KEY, $remember);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        ActivityLog::record('auth.login', $user, description: 'Signed in');

        // If the user got here from an invitation link, follow through to
        // the accept page instead of dashboard. (The accept page itself
        // will re-check the user is the invited email.)
        $pendingToken = $request->session()->pull('pending_invitation_token');
        if (is_string($pendingToken) && $pendingToken !== '') {
            return redirect()->route('invitations.show', $pendingToken);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        ActivityLog::record('auth.logout', $user, description: 'Signed out');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
