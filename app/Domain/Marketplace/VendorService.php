<?php

namespace App\Domain\Marketplace;

use App\Events\VendorRegistered;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vendor lifecycle + profile management. The only place vendors and
 * their profiles are created/mutated, so tenant scoping, slug
 * uniqueness, image handling, auditing, and events stay in one place.
 *
 * See docs/marketplace-architecture.md §2/§11.
 */
class VendorService
{
    private const IMAGE_DIRECTORY = 'vendor-assets';

    /**
     * Register a new vendor (store) owned by $owner within the current
     * tenant, with an (initially empty) profile. Self-serve activation:
     * vendors start ACTIVE so the store is usable immediately; the
     * pending→approval moderation workflow is a documented follow-up.
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
                'status' => Vendor::STATUS_ACTIVE,
            ]);

            $vendor->profile()->create([
                'company_name' => $data['name'],
            ]);

            ActivityLog::record('vendor.registered', $owner, [
                'vendor_id' => $vendor->id,
                'name' => $vendor->name,
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

    public function verify(Vendor $vendor): Vendor
    {
        $vendor->forceFill([
            'status' => Vendor::STATUS_ACTIVE,
            'verified_at' => now(),
        ])->save();

        ActivityLog::record('vendor.verified', null, ['vendor_id' => $vendor->id]);

        return $vendor;
    }

    public function suspend(Vendor $vendor): Vendor
    {
        $vendor->forceFill(['status' => Vendor::STATUS_SUSPENDED])->save();

        ActivityLog::record('vendor.suspended', null, ['vendor_id' => $vendor->id]);

        return $vendor;
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
