<?php

namespace Database\Factories;

use App\Models\RandomAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RandomAudit>
 */
class RandomAuditFactory extends Factory
{
    public function definition(): array
    {
        $date = now();

        return [
            'department_id' => \App\Models\Department::factory(),
            'audit_date' => $date->toDateString(),
            'shift' => $this->faker->randomElement(['morning', 'afternoon']),
            'status' => RandomAudit::PENDING,
            'auditor_id' => null,
            'week_number' => $date->isoWeek(),
            'year' => $date->year,
            'sample_size' => 10,
        ];
    }

    /**
     * Scheduled for a day that has already gone by.
     */
    public function overdue(int $daysAgo = 3): static
    {
        return $this->state(function () use ($daysAgo) {
            $date = now()->subDays($daysAgo);

            return [
                'audit_date' => $date->toDateString(),
                'week_number' => $date->isoWeek(),
                'year' => $date->year,
            ];
        });
    }
}
