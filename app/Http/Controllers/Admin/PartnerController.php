<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\MovesSortOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerRequest;
use App\Models\Partner;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Partners: the logos in the homepage's "trusted by" strip —
 * add, edit, delete, show/hide and reorder.
 */
class PartnerController extends Controller
{
    use MovesSortOrder;

    public function __construct(private readonly BrandingService $images) {}

    public function index(): Response
    {
        return Inertia::render('admin/partners/index', [
            'partners' => Partner::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/partners/create');
    }

    public function store(PartnerRequest $request): RedirectResponse
    {
        $partner = Partner::create([
            ...$request->safe()->only(['name', 'website_url', 'is_active']),
            'sort_order' => (int) Partner::query()->max('sort_order') + 1,
        ]);
        $this->syncLogo($request, $partner);

        return redirect()->route('admin.partners.index')
            ->with('success', __('Partner ":name" added.', ['name' => $partner->name]));
    }

    public function edit(Partner $partner): Response
    {
        return Inertia::render('admin/partners/edit', ['partner' => $partner]);
    }

    public function update(PartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner->update($request->safe()->only(['name', 'website_url', 'is_active']));
        $this->syncLogo($request, $partner);

        return redirect()->route('admin.partners.index')
            ->with('success', __('Partner ":name" updated.', ['name' => $partner->name]));
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        $name = $partner->name;
        $this->deleteLogo($partner);
        $partner->delete();

        return redirect()->route('admin.partners.index')
            ->with('success', __('Partner ":name" deleted.', ['name' => $name]));
    }

    /**
     * Move one place up or down in the strip.
     */
    public function move(Request $request, Partner $partner): RedirectResponse
    {
        $this->moveInOrder($request, $partner);

        return back();
    }

    public function toggle(Partner $partner): RedirectResponse
    {
        $partner->update(['is_active' => ! $partner->is_active]);

        return back();
    }

    private function syncLogo(PartnerRequest $request, Partner $partner): void
    {
        if ($request->hasFile('logo')) {
            $this->deleteLogo($partner);
            $partner->update(['logo_path' => $this->images->storeImage($request->file('logo'), Partner::LOGO_DIRECTORY, 'partner')]);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($partner);
            $partner->update(['logo_path' => null]);
        }
    }

    private function deleteLogo(Partner $partner): void
    {
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }
    }
}
