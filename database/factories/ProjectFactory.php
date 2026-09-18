<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->catchPhrase();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(12),
            'status' => Project::STATUS_ACTIVE,
            'starts_on' => fake()->dateTimeBetween('-30 days', 'now'),
            'due_on' => fake()->dateTimeBetween('+1 week', '+12 weeks'),
            'metadata' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => Project::STATUS_PAUSED]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => Project::STATUS_ARCHIVED]);
    }
}
