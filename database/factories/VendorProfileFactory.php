<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'company_name' => fake()->company(),
            'bio' => fake()->paragraph(),
            'logo_path' => null,
            'banner_path' => null,
            'website' => fake()->url(),
            'social_links' => null,
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => null,
            'country' => fake()->countryCode(),
            'founded_year' => fake()->numberBetween(2005, 2024),
        ];
    }
}
