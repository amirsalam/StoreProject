<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketplace\InvalidVendorTransition;
use App\Domain\Marketplace\VendorApprovalMode;
use App\Domain\Marketplace\VendorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateVendorRequest;
use App\Http\Requests\Admin\UpdateVendorApprovalModeRequest;
use App\Models\Vendor;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin vendor moderation queue (marketplace doc §11/§17): review new
 * stores, approve/reject them, suspend/reinstate active ones, grant the
 * verified badge, and set the tenant's approval mode (§21).
 *
 * Runs in the current tenant context like the rest of /admin — the
 * BelongsToTenant scope keeps the queue (and route binding) to that
 * tenant's vendors, so a cross-tenant vendor id 404s.
 */
class VendorController extends Controller
{
    private const STATUSES = [
        Vendor::STATUS_PENDING,
        Vendor::STATUS_ACTIVE,
        Vendor::STATUS_SUSPENDED,
        Vendor::STATUS_REJECTED,
    ];

    public function __construct(private readonly VendorService $vendors) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status');
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
        ];

        $query = Vendor::query()
            ->with(['owner:id,name,email', 'profile:id,vendor_id,company_name,logo_path'])
            ->withCount('products');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $term)));
        }

        // Pending first — it's a review queue — then newest.
        $query->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Vendor::STATUS_PENDING])
            ->latest('id');

        $counts = Vendor::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('admin/vendors/index', [
            'vendors' => $query->paginate(20)->withQueryString()->through(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'slug' => $v->slug,
                'status' => $v->status,
                'is_verified' => $v->isVerified(),
                'products_count' => $v->products_count,
                'created_at' => $v->created_at,
                'company_name' => $v->profile?->company_name,
                'owner' => $v->owner ? [
                    'name' => $v->owner->name,
                    'email' => $v->owner->email,
                ] : null,
                'store_url' => $v->isActive() ? route('store.show', $v->slug) : null,
            ]),
            'filters' => $filters,
            'counts' => collect(self::STATUSES)
                ->mapWithKeys(fn (string $s) => [$s => (int) ($counts[$s] ?? 0)]),
            'approvalMode' => $this->vendors->approvalMode()->value,
            // The mode is a per-tenant setting; without a tenant in context
            // (bare central domain) there is nothing to write it to.
            'canConfigureMode' => tenant() !== null,
        ]);
    }

    public function approve(ModerateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        return $this->moderate(
            fn () => $this->vendors->approve($vendor, $request->user()),
            "Approved {$vendor->name}. Their store is now live.",
        );
    }

    public function reject(ModerateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        return $this->moderate(
            fn () => $this->vendors->reject($vendor, $request->user(), $request->reason()),
            "Rejected {$vendor->name}.",
        );
    }

    public function suspend(ModerateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        return $this->moderate(
            fn () => $this->vendors->suspend($vendor, $request->user(), $request->reason()),
            "Suspended {$vendor->name}. Their store and products are hidden.",
        );
    }

    public function reinstate(ModerateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        return $this->moderate(
            fn () => $this->vendors->reinstate($vendor, $request->user()),
            "Reinstated {$vendor->name}. Their store is live again.",
        );
    }

    public function verify(ModerateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        return $this->moderate(
            fn () => $this->vendors->verify($vendor, $request->user()),
            "Marked {$vendor->name} as verified.",
        );
    }

    public function updateApprovalMode(UpdateVendorApprovalModeRequest $request): RedirectResponse
    {
        $tenant = tenant();

        if ($tenant === null) {
            return back()->withErrors([
                'mode' => 'Open this page from a workspace to set its vendor approval mode.',
            ]);
        }

        $this->vendors->setApprovalMode($tenant, $request->mode(), $request->user());

        return back()->with('success', $request->mode() === VendorApprovalMode::Manual
            ? 'New vendors will now wait for approval.'
            : 'New vendors will now go live immediately.');
    }

    /**
     * Run one moderation action; an illegal transition (e.g. approving an
     * already-active vendor) comes back as a validation-style error.
     */
    private function moderate(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidVendorTransition $e) {
            return back()->withErrors(['vendor' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
