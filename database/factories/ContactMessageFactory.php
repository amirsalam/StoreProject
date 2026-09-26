<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraphs(2, true),
            'status' => ContactMessage::STATUS_NEW,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => ContactMessage::STATUS_READ,
            'read_at' => now(),
        ]);
    }
}
