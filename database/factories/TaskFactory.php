<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assignee_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement([
                Task::STATUS_TODO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_REVIEW,
                Task::STATUS_DONE,
            ]),
            'priority' => fake()->randomElement([
                Task::PRIORITY_LOW,
                Task::PRIORITY_NORMAL,
                Task::PRIORITY_NORMAL,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ]),
            'due_on' => fake()->optional()->dateTimeBetween('+1 day', '+4 weeks'),
            'completed_at' => null,
            'position' => fake()->numberBetween(0, 1000),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => Task::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }
}
