<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = ActivityLog::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'event', 'description', 'properties', 'ip_address', 'user_agent', 'created_at']);

        return Inertia::render('settings/activity', [
            'entries' => $entries->map(fn (ActivityLog $row) => [
                'id' => $row->id,
                'event' => $row->event,
                'description' => $row->description,
                'properties' => $row->properties,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'created_at' => $row->created_at,
            ]),
        ]);
    }
}
