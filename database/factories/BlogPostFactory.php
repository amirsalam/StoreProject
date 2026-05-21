<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'user_id' => User::factory()->admin(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(4)),
            'excerpt' => fake()->sentence(20),
            'content' => fake()->paragraphs(6, true),
            'thumbnail' => null,
            'status' => BlogPost::STATUS_PUBLISHED,
            'seo_title' => null,
            'seo_description' => null,
            'tags' => fake()->randomElements(['laravel', 'react', 'saas', 'tutorial', 'release', 'guide'], 3),
            'views_count' => fake()->numberBetween(0, 5000),
            'published_at' => fake()->dateTimeBetween('-180 days'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }
}
