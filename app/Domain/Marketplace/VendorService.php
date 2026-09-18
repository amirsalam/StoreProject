<?php

namespace App\Domain\Marketplace;

use App\Events\VendorRegistered;
use App\Events\VendorStatusChanged;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vendor lifecycle + profile management. The only place vendors and
 * their profiles are created/mutated, so tenant scoping, slug
 * uniqueness, image handling, moderation transitions, auditing, and
 * events stay in one place.
 *
 * See docs/marketplace-architecture.md §2/§11.
 */
class VendorService
{
    private const IMAGE_DIRECTORY = 'vendor-assets';

    /**
     * The moderation state machine (marketplace doc §11):
     * action => [allowed from-statuses, to-status].
     *
     *   pending   --approve-->   active
     *   pending   --reject-->    rejected
     *   active    --suspend-->   suspended
     *   suspended --reinstate--> active
     */
    private const TRANSITIONS = [
        'approve' => [[Vendor::STATUS_PENDING], Vendor::STATUS_ACTIVE],
        'reject' => [[Vendor::STATUS_PENDING], Vendor::STATUS_REJECTED],
        'suspend' => [[Vendor::STATUS_ACTIVE], Vendor::STATUS_SUSPENDED],
        'reinstate' => [[Vendor::STATUS_SUSPENDED], Vendor::STATUS_ACTIVE],
    ];

    /**
     * Register a new vendor (store) owned by $owner within the current
     * tenant, with an (initially empty) profile. The starting status
     * follows the tenant's approval mode: PENDING under manual review
     * (the store and its products stay unlisted until approved), ACTIVE
     * under auto.
     *
     * @param  array{name: string}  $data
     */
    public function registerForUser(User $owner, array $data): Vendor
    {
        return DB::transaction(function () use ($owner, $data) {
            $vendor = Vendor::create([
                'owner_user_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'status' => $this->approvalMode() === VendorApprovalMode::Auto
                    ? Vendor::STATUS_ACTIVE
                    : Vendor::STATUS_PENDING,
            ]);

            $vendor->profile()->create([
                'company_name' => $data['name'],
            ]);

            ActivityLog::record('vendor.registered', $owner, [
                'vendor_id' => $vendor->id,
                'name' => $vendor->name,
                'status' => $vendor->status,
            ]);

            VendorRegistered::dispatch($vendor);

            return $vendor;
        });
    }

    /**
     * Update a vendor's display name and profile fields. Raster image
     * uploads (logo/banner) are validated to jpg/png/webp upstream
     * (no SVG → no sanitization surface) and stored on the public disk.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(
        Vendor $vendor,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $banner = null,
    ): Vendor {
        return DB::transaction(function () use ($vendor, $data, $logo, $banner) {
            if (array_key_exists('name', $data) && $data['name'] !== $vendor->name) {
                $vendor->name = $data['name'];
                $vendor->save();
            }

            $profile = $vendor->profile()->firstOrNew([]);

            $profile->fill([
                'company_name' => $data['company_name'] ?? $profile->company_name,
                'bio' => $data['bio'] ?? null,
                'website' => $data['website'] ?? null,
                'social_links' => $data['social_links'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'country' => $data['country'] ?? null,
                'founded_year' => $data['founded_year'] ?? null,
            ]);

            if ($logo instanceof UploadedFile) {
                $this->deleteImage($profile->logo_path);
                $profile->logo_path = $this->storeImage($logo, 'logo');
            }

            if ($banner instanceof UploadedFile) {
                $this->deleteImage($profile->banner_path);
                $profile->banner_path = $this->storeImage($banner, 'banner');
            }

            $vendor->profile()->save($profile);

            ActivityLog::record('vendor.profile_updated', null, [
                'vendor_id' => $vendor->id,
            ]);

            return $vendor->load('profile');
        });
    }

    public function approve(Vendor $vendor, User $actor): Vendor
    {
        return $this->transition($vendor, 'approve', 'approved', $actor);
    }

    public function reject(Vendor $vendor, User $actor, ?string $reason = null): Vendor
    {
        return $this->transition($vendor, 'reject', 'rejected', $actor, $reason);
    }

    public function suspend(Vendor $vendor, User $actor, ?string $reason = null): Vendor
    {
        return $this->transition($vendor, 'suspend', 'suspended', $actor, $reason);
    }

    public function reinstate(Vendor $vendor, User $actor): Vendor
    {
        return $this->transition($vendor, 'reinstate', 'reinstated', $actor);
    }

    /**
     * Grant the verified badge (`verified_at`). Separate from approval:
     * approval admits the store, verification attests identity (doc §2/§11)
     * — so only an active vendor can be verified.
     */
    public function verify(Vendor $vendor, User $actor): Vendor
    {
        if (! $vendor->isActive()) {
            throw InvalidVendorTransition::for($vendor, 'verify');
        }

        if ($vendor->isVerified()) {
            return $vendor;
        }

        $vendor->forceFill(['verified_at' => now()])->save();

        ActivityLog::record('vendor.verified', $actor, ['vendor_id' => $vendor->id]);

        return $vendor;
    }

    /**
     * The approval mode in force for $tenant (default: the current
     * tenant): its own setting, else the platform default from
     * config/marketplace.php. Unknown values fall back to manual — the
     * safe side, since it never lists an unreviewed store.
     */
    public function approvalMode(?Tenant $tenant = null): VendorApprovalMode
    {
        $tenant ??= tenant();

        $value = $tenant
            ? data_get($tenant->settings, 'marketplace.vendor_approval_mode')
            : null;

        return VendorApprovalMode::tryFrom((string) ($value ?? config('marketplace.vendor_approval_mode')))
            ?? VendorApprovalMode::Manual;
    }

    public function setApprovalMode(Tenant $tenant, VendorApprovalMode $mode, User $actor): void
    {
        $settings = $tenant->settings ?? [];
        $previous = data_get($settings, 'marketplace.vendor_approval_mode');
        data_set($settings, 'marketplace.vendor_approval_mode', $mode->value);

        $tenant->forceFill(['settings' => $settings])->save();

        ActivityLog::record('vendor.approval_mode_changed', $actor, [
            'tenant_id' => $tenant->id,
            'from' => $previous,
            'to' => $mode->value,
        ]);
    }

    /**
     * Apply one moderation action. The row is re-read under a lock so two
     * admins acting at once can't both pass the from-status check; the
     * event fires only after commit (VendorStatusChanged is
     * ShouldDispatchAfterCommit).
     */
    private function transition(
        Vendor $vendor,
        string $action,
        string $verb,
        User $actor,
        ?string $reason = null,
    ): Vendor {
        [$from, $to] = self::TRANSITIONS[$action];

        return DB::transaction(function () use ($vendor, $action, $verb, $actor, $reason, $from, $to) {
            $locked = Vendor::query()->whereKey($vendor->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw InvalidVendorTransition::for($locked, $action);
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => $to])->save();
            $vendor->setRawAttributes($locked->getAttributes(), true);

            ActivityLog::record("vendor.{$verb}", $actor, array_filter([
                'vendor_id' => $vendor->id,
                'from' => $previous,
                'to' => $to,
                'reason' => $reason,
            ], fn ($v) => $v !== null));

            VendorStatusChanged::dispatch($vendor, $verb, $previous, $actor, $reason);

            return $vendor;
        });
    }

    /**
     * A slug unique within the vendor's tenant (the store URL key).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $i = 1;

        // Vendor uses BelongsToTenant, so this lookup is tenant-scoped on
        // web/api requests — exactly the scope the unique index covers.
        while (Vendor::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function storeImage(UploadedFile $file, string $kind): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $name = self::IMAGE_DIRECTORY.'/'.$kind.'-'.Str::random(16).'.'.$ext;
        Storage::disk('public')->putFileAs('', $file, $name);

        return $name;
    }

    private function deleteImage(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
