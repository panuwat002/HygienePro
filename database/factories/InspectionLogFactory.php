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

            // In the same department as the round. A personnel round only ever
            // contains employees of its own department - getTargetEmployees()
            // filters on exactly that - but the factory used to put each one in
            // a department of its own, so a log's employee and its session
            // disagreed about who the work belonged to. Nothing noticed until
            // a finding's owner started being read from the employee.
            'employee_id' => fn (array $attributes) => \App\Models\Employee::factory()->create([
                'department_id' => \App\Models\InspectionSession::find($attributes['session_id'])?->department_id,
            ])->id,
            'checkpoint_id' => \App\Models\Checkpoint::factory(),
            'result' => 'fail',
            'verification_status' => 'reclean',
            'note' => $this->faker->sentence(),
        ];
    }
}
