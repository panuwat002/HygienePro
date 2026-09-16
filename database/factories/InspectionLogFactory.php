<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InspectionLog>
 */
class InspectionLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => \App\Models\InspectionSession::factory(),
            'employee_id' => \App\Models\Employee::factory(),
            'checkpoint_id' => \App\Models\Checkpoint::factory(),
            'result' => 'fail',
            'verification_status' => 'reclean',
            'note' => $this->faker->sentence(),
        ];
    }
}
