<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10_000, 500_000);
        $tax = (int) round($subtotal * 0.10);
        $issued = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'client_id' => User::factory(),
            'project_id' => null,
            'number' => 'INV-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999_999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
            'currency' => 'USD',
            'line_items' => [
                [
                    'description' => fake()->sentence(4),
                    'qty' => 1,
                    'unit_cents' => $subtotal,
                    'total_cents' => $subtotal,
                ],
            ],
            'notes' => null,
            'issued_on' => $issued,
            'due_on' => (clone $issued)->modify('+14 days'),
            'sent_at' => null,
            'paid_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_PAID,
            'sent_at' => now()->subDays(5),
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Invoice::STATUS_OVERDUE,
            'sent_at' => now()->subDays(45),
            'due_on' => now()->subDays(15),
        ]);
    }
}
