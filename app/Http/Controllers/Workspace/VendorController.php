<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Marketplace\VendorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreVendorRequest;
use App\Http\Requests\Workspace\UpdateVendorProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A tenant member manages their own vendor store + profile here. Opening
 * a store (registration) and editing the profile both live on one page;
 * ownership is enforced by VendorPolicy. See docs/marketplace-architecture.md §2.
 */
class VendorController extends Controller
{
    public function __construct(private readonly VendorService $vendors) {}

    public function edit(Request $request): Response
    {
        $vendor = $request->user()->vendor()->with('profile')->first();

        return Inertia::render('workspace/vendor/edit', [
            'vendor' => $vendor,
            'storeUrl' => $vendor?->isActive()
                ? route('store.show', $vendor->slug)
                : null,
        ]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        // One store per user in this increment.
        if ($request->user()->vendor()->exists()) {
            return redirect()->route('workspace.vendor.edit');
        }

        $this->vendors->registerForUser($request->user(), $request->validated());

        return redirect()
            ->route('workspace.vendor.edit')
            ->with('success', 'Your store is live. Add your details below.');
    }

    public function update(UpdateVendorProfileRequest $request): RedirectResponse
    {
        $vendor = $request->user()->vendor()->firstOrFail();
        Gate::authorize('update', $vendor);

        $this->vendors->updateProfile(
            $vendor,
            $request->safe()->except(['logo', 'banner']),
            $request->file('logo'),
            $request->file('banner'),
        );

        return redirect()
            ->route('workspace.vendor.edit')
            ->with('success', 'Store profile updated.');
    }
}
