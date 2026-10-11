<?php

namespace App\Http\Middleware;

use App\Domain\AppLicense\AppLicenseEvaluator;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Installation-license gate (alias `app.licensed`), applied ONLY to route
 * groups that run the business: admin (except Installation license),
 * vendor workspace, starting a purchase, product uploads and the Sanctum
 * business API. Storefront, sign-in/recovery, payment completion, buyers'
 * purchases/downloads, webhooks and the product-license API are never
 * behind it — see routes/web.php and routes/api.php.
 *
 * Licensed or in the offline grace period → pass. Otherwise:
 *   API / JSON   403 { "error": { "code": "app_license_required" } }
 *   admin        redirect to Admin → Installation license
 *   others       503 "temporarily unavailable" page
 */
class EnsureAppLicensed
{
    public function __construct(private readonly AppLicenseEvaluator $evaluator) {}

    public static function enforced(): bool
    {
        // The off switch only exists for development and the test suite.
        return ! app()->environment(['local', 'testing']) || filter_var(config('app_license.enforce', true), FILTER_VALIDATE_BOOL);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enforced() || $this->evaluator->evaluate()->allowsUse()) {
            return $next($request);
        }

        if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
            return response()->json(['error' => [
                'code' => 'app_license_required',
                'message' => 'This installation is not licensed. An administrator must activate it.',
            ]], 403);
        }

        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->is_admin)) {
            return redirect()
                ->route('admin.app-license.edit')
                ->with('error', __('This installation must be activated before this page can be used.'));
        }

        return Inertia::render('app-license-unavailable')
            ->toResponse($request)
            ->setStatusCode(503)
            ->header('Retry-After', '3600');
    }
}
