<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'gateway' => fake()->randomElement(['stripe', 'paddle']),
            'gateway_payment_id' => 'pi_' . Str::random(24),
            'gateway_customer_id' => 'cus_' . Str::random(14),
            'amount' => fake()->randomFloat(2, 10, 800),
            'currency' => 'USD',
            'status' => Payment::STATUS_SUCCEEDED,
            'payment_method' => fake()->randomElement(['card', 'paypal', 'apple_pay']),
            'raw_response' => ['mock' => true],
            'failure_reason' => null,
            'processed_at' => fake()->dateTimeBetween('-90 days'),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => fake()->randomElement([
                'card_declined',
                'insufficient_funds',
                'expired_card',
            ]),
            'processed_at' => null,
        ]);
    }
}
