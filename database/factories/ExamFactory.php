<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Exam> */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'IOE cấp trường năm học 2025-2026',
            'school_year' => '2025-2026',
            'registration_mode' => 'admin_assign_session',
            'level' => 'school',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'exam_date' => now()->addWeek()->toDateString(),
            'exam_time' => '07:00',
            'target_grades' => [10, 11, 12],
            'allow_student_edit' => true,
            'allow_student_session_change' => true,
            'require_session_choice' => false,
            'allow_personal_computer' => true,
            'auto_lock_full_sessions' => true,
            'show_public_stats' => true,
            'require_approval' => true,
            'publish_scores' => false,
            'show_countdown' => true,
            'countdown_mode' => 'auto',
            'status' => 'open',
        ];
    }
}
