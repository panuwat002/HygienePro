<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->unique()->numerify('EMP###'),
            'fullname' => $this->faker->name(),
            'department_id' => \App\Models\Department::factory(),
            'qr_code_hash' => $this->faker->unique()->sha256(),
            'is_active' => true,
        ];
    }
}
