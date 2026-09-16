<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InspectionSession>
 */
class InspectionSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspector_id' => \App\Models\User::factory(),
            'department_id' => \App\Models\Department::factory(),
            'inspection_date' => clone $this->faker->dateTimeBetween('-1 month', 'now'),
            'shift' => $this->faker->randomElement(['morning', 'afternoon', 'night']),
            'status' => 'draft',
            'locked_at' => null,
        ];
    }
}
