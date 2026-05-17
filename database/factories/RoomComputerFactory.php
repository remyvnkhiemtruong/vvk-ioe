<?php

namespace Database\Factories;

use App\Models\ExamRoom;
use App\Models\RoomComputer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoomComputer> */
class RoomComputerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_room_id' => ExamRoom::factory(),
            'computer_label' => 'Máy '.fake()->unique()->numberBetween(1, 1000),
            'computer_number' => fake()->numberBetween(1, 1000),
            'type' => 'main',
            'status' => 'ready',
        ];
    }
}
