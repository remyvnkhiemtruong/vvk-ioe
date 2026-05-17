<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $grade = fake()->randomElement([10, 11, 12]);

        return [
            'full_name' => fake()->name(),
            'grade' => $grade,
            'class_name' => $grade.'A'.fake()->numberBetween(1, 10),
            'student_code' => fake()->unique()->numerify('HS#####'),
            'identity_number' => fake()->unique()->numerify('0###########'),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-15 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Nam', 'Nữ']),
            'phone' => '09'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
