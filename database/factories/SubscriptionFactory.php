<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-30 days'));

        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory()->subscription(),
            'gateway' => fake()->randomElement(['stripe', 'paddle']),
            'gateway_subscription_id' => 'sub_' . Str::random(20),
            'gateway_customer_id' => 'cus_' . Str::random(14),
            'plan' => fake()->randomElement(['starter', 'pro', 'business']),
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => fake()->randomFloat(2, 9, 99),
            'currency' => 'USD',
            'interval' => fake()->randomElement(['month', 'year']),
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'ends_at' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => fake()->dateTimeBetween('now', '+14 days'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => fake()->dateTimeBetween('-30 days'),
            'ends_at' => fake()->dateTimeBetween('now', '+30 days'),
        ]);
    }
}
