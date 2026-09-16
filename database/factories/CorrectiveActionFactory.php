<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorrectiveAction>
 */
class CorrectiveActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_log_id' => \App\Models\InspectionLog::factory(),
            'status' => 'open',
            'escalated_by' => \App\Models\User::factory(),
            'assigned_to' => null,
            'root_cause' => $this->faker->sentence(),
            'action_taken' => null,
        ];
    }
}
