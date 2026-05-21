<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED]);

        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'type' => $type,
            'value' => $type === Coupon::TYPE_PERCENTAGE
                ? fake()->numberBetween(5, 50)
                : fake()->randomFloat(2, 5, 50),
            'min_order_amount' => fake()->boolean(40) ? fake()->randomFloat(2, 20, 100) : null,
            'max_uses' => fake()->randomElement([null, 50, 100, 500]),
            'used_count' => 0,
            'max_uses_per_user' => fake()->randomElement([null, 1, 3]),
            'starts_at' => null,
            'expires_at' => fake()->boolean(60) ? fake()->dateTimeBetween('+1 day', '+90 days') : null,
            'is_active' => true,
        ];
    }

    public function percentage(int $percent): static
    {
        return $this->state(fn () => [
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => $percent,
        ]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => [
            'type' => Coupon::TYPE_FIXED,
            'value' => $amount,
        ]);
    }
}
