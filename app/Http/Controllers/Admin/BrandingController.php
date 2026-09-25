<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function edit(): Response
    {
        return Inertia::render('admin/branding/edit', [
            'branding' => $this->branding->summary(),
        ]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $this->branding->updateTitle((string) $request->validated('title'));

        if ($request->hasFile('logo')) {
            $this->branding->replaceLogo($request->file('logo'));
        }

        return redirect()
            ->route('admin.branding.edit')
            ->with('success', 'Branding updated.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->branding->deleteLogo();

        return redirect()
            ->route('admin.branding.edit')
            ->with('success', 'Logo removed. Default mark restored.');
    }
}
