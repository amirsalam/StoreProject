<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin routes. Reads the `admin` role first (spatie/permission)
 * and falls back to the legacy `is_admin` boolean so this middleware
 * keeps working during the role-system migration window.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user && (
            $user->hasRole('admin') || (bool) $user->is_admin
        );

        abort_unless($allowed, 403);

        return $next($request);
    }
}
