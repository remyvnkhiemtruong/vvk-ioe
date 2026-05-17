<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_code')->nullable()->unique();
            $table->string('identity_code')->nullable();
            $table->string('class_name')->index();
            $table->unsignedTinyInteger('grade')->index();
            $table->string('homeroom_teacher')->nullable();
            $table->string('study_shift')->nullable();
            $table->string('foreign_language_1')->nullable();
            $table->string('foreign_language_2')->nullable();
            $table->string('track')->nullable();
            $table->boolean('is_specialized')->nullable();
            $table->boolean('has_vocational_students')->nullable();
            $table->boolean('is_combined')->nullable();
            $table->string('combined_into_class')->nullable();
            $table->boolean('is_boarding')->nullable();
            $table->unsignedTinyInteger('weekly_sessions')->nullable();
            $table->string('school_year')->default('2025-2026')->index();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['school_year', 'class_name']);
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code')->nullable()->unique();
            $table->string('identity_number')->nullable()->unique();
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('staff_type')->nullable();
            $table->string('position_group')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('qualification')->nullable();
            $table->string('subject')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('school_classes');
    }
};
