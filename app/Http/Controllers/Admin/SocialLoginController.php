<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSocialLoginRequest;
use App\Models\ActivityLog;
use App\Services\SocialLoginSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Social login: the Google / GitHub app credentials behind the
 * "Continue with …" buttons. Replaces editing GOOGLE_* / GITHUB_* in .env.
 */
class SocialLoginController extends Controller
{
    public function __construct(private readonly SocialLoginSettings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/social-login/edit', [
            'settings' => $this->settings->forForm(),
        ]);
    }

    public function update(UpdateSocialLoginRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        ActivityLog::record('social_login.updated', $request->user(), [
            'enabled' => collect(SocialLoginSettings::PROVIDERS)
                ->filter(fn (string $p) => $request->boolean("$p.enabled"))
                ->values()
                ->all(),
        ]);

        return back()->with('success', __('Social login settings saved.'));
    }
}
