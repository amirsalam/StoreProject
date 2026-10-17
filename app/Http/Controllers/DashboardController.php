<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia entry point for the in-app dashboard.
 *
 * Renders one of four React pages based on the active role:
 *   - super-admin → dashboard/super-admin
 *   - vendor      → dashboard/vendor
 *   - customer    → dashboard/customer
 *   - team        → dashboard/team
 *
 * All four pages consume the same WidgetPayload protocol so they share
 * the same widget components.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    public function show(Request $request): Response
    {
        $payload = $this->service->forUser($request->user());

        return Inertia::render(
            "dashboard/{$payload['layout']}",
            $payload,
        );
    }
}
