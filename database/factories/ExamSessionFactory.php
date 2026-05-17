<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamSession> */
class ExamSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'exam_room_id' => ExamRoom::factory(),
            'name' => 'Ca '.fake()->numberBetween(1, 12),
            'session_name' => 'Ca '.fake()->numberBetween(1, 12),
            'exam_date' => now()->addWeek()->toDateString(),
            'session_date' => now()->addWeek()->toDateString(),
            'start_time' => '07:30',
            'end_time' => '08:00',
            'starts_at' => now()->addWeek()->setTime(7, 30),
            'ends_at' => now()->addWeek()->setTime(8, 0),
            'max_candidates' => 25,
            'status' => 'open',
        ];
    }
}
