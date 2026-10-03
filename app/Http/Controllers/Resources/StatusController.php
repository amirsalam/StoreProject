<?php

namespace App\Http\Controllers\Resources;

use App\Domain\Status\SystemStatus;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public /status page: the live component checks from SystemStatus.
 */
class StatusController extends Controller
{
    public function __invoke(SystemStatus $status): Response
    {
        return Inertia::render('resources/status', [
            'status' => $status->current(),
            'refreshSeconds' => SystemStatus::CACHE_SECONDS,
        ]);
    }
}
