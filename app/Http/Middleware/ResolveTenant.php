<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active tenant from the request Host header.
 *
 * Lookup order:
 *   1. Custom domain (tenants.custom_domain = "store.acme.com")
 *   2. Subdomain on the central domain (acme.example.com → slug "acme")
 *   3. Central domain itself, or a reserved subdomain (www/app/api/…) → no tenant
 *
 * Unknown subdomains return 404 so we never silently serve another
 * tenant's data on a mistyped host.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->getHost());
        $central = strtolower((string) config('tenancy.central_domain', 'localhost'));

        // 1. Exact custom domain match
        if ($host !== $central) {
            $byCustom = Tenant::query()->where('custom_domain', $host)->first();
            if ($byCustom) {
                $this->context->set($byCustom);
                return $next($request);
            }
        }

        // 2. Subdomain on the central domain
        if ($host === $central) {
            $this->applyFallback();
            return $next($request);
        }

        $suffix = '.' . $central;
        if (! str_ends_with($host, $suffix)) {
            // Hit on an unrelated host — treat as central (e.g. an IP).
            $this->applyFallback();
            return $next($request);
        }

        $slug = substr($host, 0, -strlen($suffix));

        // Reserved labels stay central
        $reserved = (array) config('tenancy.reserved_subdomains', []);
        if ($slug === '' || in_array($slug, $reserved, true)) {
            $this->applyFallback();
            return $next($request);
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            abort(404, "No workspace at \"{$host}\".");
        }

        $this->context->set($tenant);
        return $next($request);
    }

    /**
     * Apply the configured central-domain fallback tenant (if any).
     *
     * When `tenancy.central_fallback_tenant` is set, central-domain
     * requests get scoped to that tenant. Useful in local development
     * where the whole app runs on `localhost` without per-tenant
     * subdomains; should usually be empty in production.
     */
    private function applyFallback(): void
    {
        $slug = (string) config('tenancy.central_fallback_tenant', '');
        if ($slug === '') {
            return;
        }
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant) {
            $this->context->set($tenant);
        }
    }
}
