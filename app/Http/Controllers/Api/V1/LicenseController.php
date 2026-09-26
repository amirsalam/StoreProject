<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Licensing\LicenseActivationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LicenseActivationRequest;
use App\Models\License;
use Illuminate\Http\JsonResponse;

/**
 * Public license activation API — what a customer's script calls to
 * activate, validate, or release a license on a site.
 *
 * Auth: none; the license key is the credential. Rate-limited per IP
 * (the `license-api` limiter).
 * Tenant: resolved from the request host by ResolveTenant, so a script
 * calls its own store's host (acme.example.com/api/v1/licenses/…).
 * Errors: LicenseActivationException renders `{ message, error }`.
 */
class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseActivationService $licenses,
    ) {}

    public function activate(LicenseActivationRequest $request): JsonResponse
    {
        $license = $this->licenses->activate($request->licenseKey(), $request->domain(), $request->productId());

        return $this->respond($license, $request->domain());
    }

    public function deactivate(LicenseActivationRequest $request): JsonResponse
    {
        $license = $this->licenses->deactivate($request->licenseKey(), $request->domain(), $request->productId());

        return $this->respond($license, $request->domain());
    }

    public function validate(LicenseActivationRequest $request): JsonResponse
    {
        $license = $this->licenses->validate($request->licenseKey(), $request->domain(), $request->productId());

        return $this->respond($license, $request->domain());
    }

    private function respond(License $license, string $domain): JsonResponse
    {
        return response()->json(['data' => self::payload($license, $domain)]);
    }

    /**
     * The success payload. Public so the API reference page can render an
     * example from the same code the endpoints use.
     *
     * @return array<string, mixed>
     */
    public static function payload(License $license, string $domain): array
    {
        $used = count($license->activatedDomains());

        return [
            'domain' => $domain,
            'activated' => $license->hasActivation($domain),
            'status' => $license->status,
            'product_id' => $license->product_id,
            'activation_limit' => $license->activation_limit,
            'activations_count' => $used,
            'activations_remaining' => max(0, $license->activation_limit - $used),
            'expires_at' => $license->expires_at?->toIso8601String(),
        ];
    }
}
