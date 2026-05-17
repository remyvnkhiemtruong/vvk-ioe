<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('students');
            $table->string('file_name')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->json('mapping')->nullable();
            $table->json('preview_rows')->nullable();
            $table->json('errors')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->unsignedTinyInteger('grade')->index();
            $table->string('class_name')->index();
            $table->string('student_code')->nullable()->unique();
            $table->string('identity_number')->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('school_year')->index();
            $table->string('level')->default('school')->index();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->date('exam_date')->nullable();
            $table->json('target_grades')->nullable();
            $table->json('target_classes')->nullable();
            $table->boolean('allow_student_edit')->default(true);
            $table->boolean('require_approval')->default(true);
            $table->boolean('publish_scores')->default(false);
            $table->string('status')->default('draft')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['exam_id', 'field_key']);
        });

        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code')->unique();
            $table->string('room_name');
            $table->string('location')->nullable();
            $table->unsignedInteger('usable_computers')->default(0);
            $table->unsignedInteger('backup_computers')->default(0);
            $table->text('note')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('room_computers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_room_id')->constrained('exam_rooms')->cascadeOnDelete();
            $table->string('computer_label');
            $table->unsignedInteger('computer_number')->nullable();
            $table->string('type')->default('main')->index();
            $table->string('status')->default('ready')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['exam_room_id', 'computer_label']);
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_room_id')->nullable()->constrained('exam_rooms')->nullOnDelete();
            $table->string('name');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('target_grade')->nullable()->index();
            $table->json('target_classes')->nullable();
            $table->unsignedInteger('max_candidates')->default(25);
            $table->string('status')->default('draft')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('ioe_id');
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('identity_number');
            $table->string('class_name')->index();
            $table->text('address');
            $table->string('phone');
            $table->string('email');
            $table->text('note')->nullable();
            $table->boolean('uses_personal_computer')->default(false);
            $table->string('device_type')->nullable();
            $table->string('device_os')->nullable();
            $table->boolean('has_charger')->nullable();
            $table->text('device_note')->nullable();
            $table->boolean('device_commitment')->default(false);
            $table->string('personal_computer_status')->default('pending')->index();
            $table->string('registration_code')->unique();
            $table->string('status')->default('submitted')->index();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
            $table->unique(['exam_id', 'ioe_id']);
            $table->unique(['exam_id', 'identity_number']);
        });

        Schema::create('seat_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_registration_id')->constrained('exam_registrations')->cascadeOnDelete();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_room_id')->constrained('exam_rooms')->cascadeOnDelete();
            $table->string('seat_type')->default('school_computer')->index();
            $table->foreignId('computer_id')->nullable()->constrained('room_computers')->nullOnDelete();
            $table->unsignedInteger('computer_number')->nullable();
            $table->foreignId('backup_computer_id')->nullable()->constrained('room_computers')->nullOnDelete();
            $table->unsignedInteger('candidate_number')->nullable();
            $table->string('assignment_method')->default('manual');
            $table->string('status')->default('assigned')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_registration_id', 'exam_session_id'], 'seat_registration_session_unique');
            $table->unique(['exam_session_id', 'exam_room_id', 'computer_id'], 'seat_session_room_computer_unique');
            $table->unique(['exam_session_id', 'exam_room_id', 'candidate_number'], 'seat_session_room_candidate_unique');
        });

        Schema::create('proctor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_room_id')->constrained('exam_rooms')->cascadeOnDelete();
            $table->string('role')->default('proctor');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'exam_session_id', 'exam_room_id'], 'proctor_assignment_unique');
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_assignment_id')->constrained('seat_assignments')->cascadeOnDelete();
            $table->string('status')->default('not_checked_in')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('personal_device_present')->nullable();
            $table->boolean('charger_present')->nullable();
            $table->boolean('network_ok')->nullable();
            $table->boolean('ioe_login_ok')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique('seat_assignment_id');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_assignment_id')->nullable()->constrained('seat_assignments')->nullOnDelete();
            $table->foreignId('exam_registration_id')->nullable()->constrained('exam_registrations')->nullOnDelete();
            $table->string('incident_type')->index();
            $table->text('description');
            $table->text('solution')->nullable();
            $table->foreignId('old_computer_id')->nullable()->constrained('room_computers')->nullOnDelete();
            $table->foreignId('new_computer_id')->nullable()->constrained('room_computers')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_registration_id')->constrained('exam_registrations')->cascadeOnDelete();
            $table->foreignId('seat_assignment_id')->nullable()->constrained('seat_assignments')->nullOnDelete();
            $table->decimal('official_score', 6, 2)->nullable();
            $table->unsignedInteger('completion_time_seconds')->nullable();
            $table->unsignedInteger('correct_answers')->nullable();
            $table->string('exam_status')->default('not_started')->index();
            $table->string('score_status')->default('not_entered')->index();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique('exam_registration_id');
        });

        Schema::create('score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_score_id')->constrained('exam_scores')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('old_score', 6, 2)->nullable();
            $table->decimal('new_score', 6, 2)->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason');
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('ioe_research_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('level')->default('general')->index();
            $table->string('school_year')->nullable();
            $table->date('issued_date')->nullable();
            $table->string('source_url')->nullable();
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ioe_research_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('level')->default('general')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ioe_research_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('level')->index();
            $table->string('school_year')->nullable();
            $table->longText('content');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ioe_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('level')->default('school')->index();
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ioe_potential_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('full_name');
            $table->string('class_name')->nullable();
            $table->string('ioe_id')->nullable();
            $table->string('self_practice_round')->nullable();
            $table->string('school_result')->nullable();
            $table->boolean('recommend_next_round')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ioe_reference_results', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('level')->default('school')->index();
            $table->string('school_year')->nullable();
            $table->json('data')->nullable();
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('password_reset_requests');
        Schema::dropIfExists('ioe_reference_results');
        Schema::dropIfExists('ioe_potential_students');
        Schema::dropIfExists('ioe_checklists');
        Schema::dropIfExists('ioe_research_conditions');
        Schema::dropIfExists('ioe_research_calendar_events');
        Schema::dropIfExists('ioe_research_documents');
        Schema::dropIfExists('score_logs');
        Schema::dropIfExists('exam_scores');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('proctor_assignments');
        Schema::dropIfExists('seat_assignments');
        Schema::dropIfExists('exam_registrations');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('room_computers');
        Schema::dropIfExists('exam_rooms');
        Schema::dropIfExists('exam_form_fields');
        Schema::dropIfExists('exams');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::dropIfExists('students');
        Schema::dropIfExists('import_batches');
    }
};
