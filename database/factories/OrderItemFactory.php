<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unit = fake()->randomFloat(2, 9, 199);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_title' => fake()->words(3, true),
            'product_type' => fake()->randomElement([
                Product::TYPE_DIGITAL_DOWNLOAD,
                Product::TYPE_LICENSE,
                Product::TYPE_SUBSCRIPTION,
            ]),
            'quantity' => $quantity,
            'unit_price' => $unit,
            'total_price' => round($unit * $quantity, 2),
            'metadata' => null,
        ];
    }
}
