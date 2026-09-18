<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'custom_domain' => null,
            'owner_id' => User::factory(),
            'settings' => null,
            'trial_ends_at' => null,
        ];
    }

    public function forOwner(User $owner): static
    {
        return $this->state(fn () => ['owner_id' => $owner->id]);
    }
}
