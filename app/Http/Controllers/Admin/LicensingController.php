<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\LicensingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Licensing: the store-wide Extended License rule (regular price ×
 * N for every product without its own Extended price).
 */
class LicensingController extends Controller
{
    public function __construct(private readonly LicensingSettings $settings) {}

    public function edit(): Response
    {
        $licensable = Product::query()->where('type', '!=', Product::TYPE_SUBSCRIPTION);

        return Inertia::render('admin/licensing/edit', [
            'settings' => $this->settings->forForm(),
            'counts' => [
                'products' => (clone $licensable)->count(),
                'own_price' => (clone $licensable)->whereNotNull('extended_price')->count(),
            ],
            // A real product to preview the rule on.
            'example' => (clone $licensable)->where('status', Product::STATUS_PUBLISHED)->orderByDesc('sales_count')->first(['id', 'title', 'price']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = $request->validate([
            'extended_enabled' => ['required', 'boolean'],
            'extended_multiplier' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $this->settings->save($data);

        ActivityLog::record('licensing.updated', $request->user(), $data);

        return back()->with('success', __('Licensing settings saved.'));
    }
}
