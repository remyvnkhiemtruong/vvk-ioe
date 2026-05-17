<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('student_id', 'users_student_id_unique');
        });

        Schema::table('proctor_assignments', function (Blueprint $table) {
            $table->index(['exam_session_id', 'exam_room_id'], 'proctor_session_room_index');
        });

        Schema::table('seat_assignments', function (Blueprint $table) {
            $table->index(['exam_session_id', 'exam_room_id'], 'seat_session_room_index');
        });
    }

    public function down(): void
    {
        Schema::table('seat_assignments', function (Blueprint $table) {
            $table->dropIndex('seat_session_room_index');
        });

        Schema::table('proctor_assignments', function (Blueprint $table) {
            $table->dropIndex('proctor_session_room_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_student_id_unique');
        });
    }
};
