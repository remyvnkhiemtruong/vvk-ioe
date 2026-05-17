<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ExamRegistration> */
class ExamRegistrationFactory extends Factory
{
    public function definition(): array
    {
        $student = Student::factory()->create();

        return [
            'student_id' => $student->id,
            'exam_id' => Exam::factory(),
            'full_name' => $student->full_name,
            'ioe_id' => fake()->unique()->bothify('ioe####'),
            'date_of_birth' => $student->date_of_birth,
            'gender' => $student->gender,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address,
            'phone' => $student->phone,
            'email' => $student->email,
            'uses_personal_computer' => false,
            'personal_computer_status' => 'approved',
            'registration_code' => 'IOE-'.Str::upper(Str::random(8)),
            'status' => 'approved',
            'registered_at' => now(),
        ];
    }
}
