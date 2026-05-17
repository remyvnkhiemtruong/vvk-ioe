<?php

namespace Database\Factories;

use App\Models\ExamRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamRoom> */
class ExamRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_code' => fake()->unique()->bothify('ROOM##'),
            'room_name' => 'Phòng Tin học '.fake()->numberBetween(1, 5),
            'usable_computers' => 25,
            'backup_computers' => 10,
            'status' => 'active',
        ];
    }
}
