<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        $limit = fake()->randomElement([1, 3, 5, 10]);

        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory()->license(),
            'order_item_id' => null,
            'activation_limit' => $limit,
            'activations_count' => fake()->numberBetween(0, $limit),
            'activated_domains' => null,
            'status' => License::STATUS_ACTIVE,
            'expires_at' => fake()->boolean(40) ? fake()->dateTimeBetween('now', '+1 year') : null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => License::STATUS_EXPIRED,
            'expires_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => License::STATUS_REVOKED,
            'revoked_at' => fake()->dateTimeBetween('-90 days'),
        ]);
    }
}
