<?php

namespace App\Http\Controllers\Resources;

use App\Domain\Licensing\LicenseActivationException;
use App\Domain\Licensing\LicenseActivationService;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * Public /api-reference page.
 *
 * Endpoints are listed by route name; their method and path are read
 * from the router, and the license error codes from the exception that
 * renders them — so the page can't drift from routes/api.php.
 * ResourcePagesTest fails if an api.v1.* route is missing from GROUPS.
 */
class ApiReferenceController extends Controller
{
    /**
     * group => [auth scheme, [route name => [parameter => required?]]]
     */
    public const GROUPS = [
        'licenses' => [
            'auth' => 'license_key',
            'routes' => [
                'api.v1.licenses.activate' => ['license_key' => true, 'domain' => true, 'product_id' => false],
                'api.v1.licenses.validate' => ['license_key' => true, 'domain' => true, 'product_id' => false],
                'api.v1.licenses.deactivate' => ['license_key' => true, 'domain' => true, 'product_id' => false],
            ],
        ],
        'account' => [
            'auth' => 'token',
            'routes' => [
                'api.v1.user' => [],
                'api.v1.dashboard' => [],
                'api.v1.notifications.index' => ['status' => false, 'per_page' => false, 'cursor' => false],
                'api.v1.notifications.read' => [],
                'api.v1.notifications.read_all' => [],
                'api.v1.notifications.destroy' => [],
            ],
        ],
    ];

    public function __invoke(Request $request, Router $router): Response
    {
        $groups = collect(self::GROUPS)->map(fn (array $group, string $key) => [
            'key' => $key,
            'auth' => $group['auth'],
            'endpoints' => collect($group['routes'])->map(function (array $params, string $name) use ($router) {
                $route = $router->getRoutes()->getByName($name)
                    ?? throw new LogicException("API reference lists unknown route [{$name}].");

                return [
                    'key' => str_replace('.', '_', Str::after($name, 'api.v1.')),
                    'method' => collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->implode('|'),
                    'path' => '/'.$route->uri(),
                    'params' => collect($params)->map(fn (bool $required, string $param) => [
                        'name' => $param,
                        'required' => $required,
                    ])->values(),
                ];
            })->values(),
        ])->values();

        $licenseErrors = collect([
            LicenseActivationException::invalid(),
            LicenseActivationException::revoked(),
            LicenseActivationException::expired(),
            LicenseActivationException::limitReached(),
            LicenseActivationException::notActivated(),
        ])->map(fn (LicenseActivationException $e) => [
            'code' => $e->errorCode,
            'status' => $e->status,
            'message' => $e->getMessage(),
        ]);

        // Unsaved model: rendered through the endpoints' own payload builder.
        $example = new License([
            'product_id' => 42,
            'activation_limit' => 3,
            'activated_domains' => ['example.com'],
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addYear()->startOfDay(),
        ]);

        return Inertia::render('resources/api-reference', [
            'baseUrl' => $request->getSchemeAndHttpHost(),
            'groups' => $groups,
            'licenseExample' => ['data' => LicenseController::payload($example, 'example.com')],
            'licenseErrors' => $licenseErrors,
            'rateLimitPerMinute' => LicenseActivationService::RATE_LIMIT_PER_MINUTE,
        ]);
    }
}
