<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $enabled = $user->hasTwoFactorEnabled();
        $pending = $user->two_factor_secret && ! $enabled;

        return Inertia::render('settings/two-factor', [
            'enabled' => $enabled,
            'pending' => $pending,
            // Surface QR + recovery codes only while setup is in flight
            // (before confirmation) or when the user just rotated codes.
            'qr_svg' => $pending ? $this->twoFactor->qrSvg($user) : null,
            'secret' => $pending ? $user->two_factor_secret : null,
            'recovery_codes' => $enabled ? ($user->two_factor_recovery_codes ?? []) : ($pending ? ($user->two_factor_recovery_codes ?? []) : []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->twoFactor->enable($request->user());

        return back()->with('success', 'Two-factor authentication enabled. Confirm with a code from your authenticator app to finish setup.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();
        if (! $this->twoFactor->confirm($user, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => __('That code is invalid. Try again.'),
            ]);
        }

        ActivityLog::record('2fa.enabled', $user, description: 'Two-factor authentication confirmed');

        return back()->with('success', 'Two-factor authentication is now active.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $this->twoFactor->disable($request->user());

        ActivityLog::record('2fa.disabled', $request->user(), description: 'Two-factor authentication removed');

        return back()->with('success', 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 400);
        $request->validate(['password' => ['required', 'current_password']]);

        $this->twoFactor->regenerateRecoveryCodes($request->user());

        ActivityLog::record('2fa.recovery_codes_regenerated', $request->user());

        return back()->with('success', 'New recovery codes generated.');
    }
}
