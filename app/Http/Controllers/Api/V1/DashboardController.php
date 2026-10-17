<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST counterpart to the Inertia dashboard route.
 *
 * Used by the mobile app and any external SPA. Returns the same
 * payload structure as the Inertia version.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->forUser($request->user()),
        );
    }
}
