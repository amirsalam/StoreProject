<?php

namespace App\Http\Controllers\Admin;

use App\Domain\AppLicense\AppLicenseManager;
use App\Domain\AppLicense\AppLicenseMessages;
use App\Domain\AppLicense\LicenseServerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateAppLicenseRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Installation license: activate THIS StoreProject installation
 * with a key from IndependentLicenseServer. Unrelated to the product
 * licenses StoreProject sells (Admin → Licensing).
 *
 * Must stay reachable while no license is active (it is how you activate).
 */
class AppLicenseController extends Controller
{
    public function __construct(private readonly AppLicenseManager $manager) {}

    public function edit(): Response
    {
        return Inertia::render('admin/app-license/edit', [
            'license' => $this->manager->status()->toArray(),
        ]);
    }

    public function activate(ActivateAppLicenseRequest $request): RedirectResponse
    {
        try {
            $this->manager->activate($request->string('license_key')->toString());
        } catch (LicenseServerException $e) {
            return back()->withErrors(['license_key' => AppLicenseMessages::for($e->errorCode)]);
        }

        return back()->with('success', __('This installation is now activated.'));
    }

    /** "Check now": an immediate check-in, e.g. after a reinstatement. */
    public function refresh(): RedirectResponse
    {
        return match ($outcome = $this->manager->refresh()) {
            'refreshed' => back()->with('success', __('License confirmed by the license server.')),
            'not_activated' => back()->with('error', __('This installation is not activated.')),
            'domain_changed' => back()->with('error', __('APP_URL no longer matches the activated domain. Activate this installation again.')),
            default => back()->with('error', AppLicenseMessages::for((string) $this->manager->status()->lastErrorCode ?: $outcome)),
        };
    }

    public function deactivate(): RedirectResponse
    {
        try {
            $this->manager->deactivate();
        } catch (LicenseServerException $e) {
            return back()->with('error', AppLicenseMessages::for($e->errorCode));
        }

        return back()->with('success', __('This installation was deactivated. The license can now be activated on another domain.'));
    }
}
