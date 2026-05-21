<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory()->digitalDownload(),
            'order_item_id' => null,
            'downloads_count' => fake()->numberBetween(0, 8),
            'max_downloads' => fake()->randomElement([null, 5, 10]),
            'last_ip' => fake()->ipv4(),
            'last_downloaded_at' => fake()->dateTimeBetween('-30 days'),
            'expires_at' => null,
        ];
    }
}
