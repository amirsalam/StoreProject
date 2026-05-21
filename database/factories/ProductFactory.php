<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 9, 499);
        $onSale = fake()->boolean(25);

        return [
            'category_id' => Category::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(4)),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'type' => fake()->randomElement([
                Product::TYPE_DIGITAL_DOWNLOAD,
                Product::TYPE_SUBSCRIPTION,
                Product::TYPE_API_ACCESS,
                Product::TYPE_LICENSE,
            ]),
            'price' => $price,
            'sale_price' => $onSale ? round($price * fake()->randomFloat(2, 0.5, 0.85), 2) : null,
            'currency' => 'USD',
            'thumbnail' => null,
            'gallery' => null,
            'download_file_path' => null,
            'version' => fake()->semver(),
            'license_type' => null,
            'default_activation_limit' => fake()->randomElement([1, 1, 1, 3, 5]),
            'download_limit' => null,
            'status' => Product::STATUS_PUBLISHED,
            'is_featured' => fake()->boolean(20),
            'seo_title' => null,
            'seo_description' => null,
            'sales_count' => fake()->numberBetween(0, 500),
        ];
    }

    public function digitalDownload(): static
    {
        return $this->state(fn () => [
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'download_file_path' => 'products/' . Str::random(16) . '.zip',
        ]);
    }

    public function subscription(): static
    {
        return $this->state(fn () => ['type' => Product::TYPE_SUBSCRIPTION]);
    }

    public function apiAccess(): static
    {
        return $this->state(fn () => ['type' => Product::TYPE_API_ACCESS]);
    }

    public function license(): static
    {
        return $this->state(fn () => [
            'type' => Product::TYPE_LICENSE,
            'license_type' => fake()->randomElement(['single-site', 'unlimited', 'developer']),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Product::STATUS_DRAFT]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
