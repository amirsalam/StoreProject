<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => Vendor::STATUS_ACTIVE,
            'verified_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_SUSPENDED]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => Vendor::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }
}
