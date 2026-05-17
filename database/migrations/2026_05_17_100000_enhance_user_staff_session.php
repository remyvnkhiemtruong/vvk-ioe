<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thêm phone, avatar_path, staff_profile_id vào bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('username');
            $table->string('avatar_path', 500)->nullable()->after('phone');
        });

        // Thêm created_by vào exam_sessions
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'created_by');
            $table->dropColumn('created_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar_path']);
        });
    }
};
