<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 800);
        $discount = fake()->boolean(30) ? round($subtotal * fake()->randomFloat(2, 0.05, 0.25), 2) : 0;
        $tax = 0;
        $total = round($subtotal - $discount + $tax, 2);

        return [
            'user_id' => User::factory(),
            'coupon_id' => null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'currency' => 'USD',
            'status' => Order::STATUS_PENDING,
            'payment_method' => null,
            'billing_name' => fake()->name(),
            'billing_email' => fake()->safeEmail(),
            'billing_country' => fake()->countryCode(),
            'billing_address' => [
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'postal_code' => fake()->postcode(),
            ],
            'notes' => null,
            'paid_at' => null,
            'refunded_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_PAID,
            'payment_method' => fake()->randomElement(['stripe', 'paddle']),
            'paid_at' => fake()->dateTimeBetween('-90 days'),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_REFUNDED,
            'paid_at' => fake()->dateTimeBetween('-90 days', '-30 days'),
            'refunded_at' => fake()->dateTimeBetween('-30 days'),
        ]);
    }
}
