<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(5),
            'comment' => fake()->paragraph(),
            'is_approved' => true,
            'is_verified_purchase' => fake()->boolean(70),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['is_approved' => false]);
    }
}
