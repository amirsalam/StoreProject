<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'stripe',
            'name' => 'Stripe',
            'display_name' => 'Stripe',
            'description' => fake()->sentence(),
            'logo' => '💳',
            'is_active' => true,
            'is_default' => false,
            'environment' => PaymentGateway::ENV_SANDBOX,
            'credentials' => [
                'publishable_key' => 'pk_test_'.fake()->lexify('??????'),
                'secret_key' => 'sk_test_'.fake()->lexify('??????'),
            ],
            'webhook_secret' => 'whsec_'.fake()->lexify('??????'),
            'supported_currencies' => ['USD'],
            'supported_countries' => ['US'],
            'fee_fixed' => 0.30,
            'fee_percent' => 2.9,
            'min_amount' => null,
            'max_amount' => null,
            'sort_order' => 0,
            'webhook_status' => 'unknown',
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true, 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
