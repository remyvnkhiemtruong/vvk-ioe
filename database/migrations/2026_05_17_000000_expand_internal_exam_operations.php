<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_years')) {
            Schema::create('academic_years', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_current')->default(false)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grades')) {
            Schema::create('grades', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('grade_number')->unique();
                $table->string('name')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }

        Schema::table('school_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('school_classes', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('school_year')->constrained('academic_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('school_classes', 'grade_id')) {
                $table->foreignId('grade_id')->nullable()->after('grade')->constrained('grades')->nullOnDelete();
            }
            if (! Schema::hasColumn('school_classes', 'homeroom_teacher_id')) {
                $table->foreignId('homeroom_teacher_id')->nullable()->after('homeroom_teacher')->constrained('staff_profiles')->nullOnDelete();
            }
            if (! Schema::hasColumn('school_classes', 'homeroom_teacher_resolution_status')) {
                $table->string('homeroom_teacher_resolution_status')->default('not_checked')->after('homeroom_teacher_id')->index();
            }
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_profiles', 'ministry_identifier')) {
                $table->string('ministry_identifier')->nullable()->after('identity_number')->index();
            }
            if (! Schema::hasColumn('staff_profiles', 'ethnicity')) {
                $table->string('ethnicity')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('staff_profiles', 'suggested_role')) {
                $table->string('suggested_role')->nullable()->after('subject');
            }
            if (! Schema::hasColumn('staff_profiles', 'role_approved_by')) {
                $table->foreignId('role_approved_by')->nullable()->after('suggested_role')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('staff_profiles', 'role_approved_at')) {
                $table->timestamp('role_approved_at')->nullable()->after('role_approved_by');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'ministry_identifier')) {
                $table->string('ministry_identifier')->nullable()->after('identity_number')->index();
            }
            if (! Schema::hasColumn('students', 'ethnicity')) {
                $table->string('ethnicity')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('students', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('class_name')->constrained('academic_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'grade_id')) {
                $table->foreignId('grade_id')->nullable()->after('grade')->constrained('grades')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'school_class_id')) {
                $table->foreignId('school_class_id')->nullable()->after('class_name')->constrained('school_classes')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'health_metadata')) {
                $table->json('health_metadata')->nullable()->after('note');
            }
        });

        if (! Schema::hasTable('academic_results')) {
            Schema::create('academic_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
                $table->string('student_code')->index();
                $table->string('ministry_identifier')->nullable()->index();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                $table->string('school_year')->index();
                $table->unsignedTinyInteger('grade')->nullable()->index();
                $table->string('class_name')->nullable()->index();
                $table->string('full_name');
                $table->string('status')->nullable();
                $table->decimal('final_score', 5, 2)->nullable();
                $table->string('semester')->nullable();
                $table->string('stage')->nullable();
                $table->string('academic_performance')->nullable();
                $table->string('conduct')->nullable();
                $table->string('title')->nullable();
                $table->string('learning_result')->nullable();
                $table->string('training_result')->nullable();
                $table->string('external_summary_id')->nullable();
                $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
                $table->timestamps();
                $table->unique(['student_code', 'school_year', 'semester', 'stage'], 'academic_results_business_unique');
            });
        }

        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('school_year')->constrained('academic_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'template_type')) {
                $table->string('template_type')->default('truong')->after('level')->index();
            }
            if (! Schema::hasColumn('exams', 'external_platform_name')) {
                $table->string('external_platform_name')->default('IOE')->after('template_type');
            }
            if (! Schema::hasColumn('exams', 'registration_mode')) {
                $table->string('registration_mode')->default('admin_assign_session')->after('external_platform_name')->index();
            }
            if (! Schema::hasColumn('exams', 'organizer_scope')) {
                $table->string('organizer_scope')->default('school')->after('registration_mode')->index();
            }
            if (! Schema::hasColumn('exams', 'registration_start_at')) {
                $table->timestamp('registration_start_at')->nullable()->after('registration_closes_at');
            }
            if (! Schema::hasColumn('exams', 'registration_end_at')) {
                $table->timestamp('registration_end_at')->nullable()->after('registration_start_at');
            }
            if (! Schema::hasColumn('exams', 'exam_start_at')) {
                $table->timestamp('exam_start_at')->nullable()->after('exam_date');
            }
            if (! Schema::hasColumn('exams', 'exam_end_at')) {
                $table->timestamp('exam_end_at')->nullable()->after('exam_start_at');
            }
            if (! Schema::hasColumn('exams', 'max_score_rule')) {
                $table->json('max_score_rule')->nullable()->after('target_classes');
            }
            if (! Schema::hasColumn('exams', 'result_source')) {
                $table->string('result_source')->default('manual')->after('max_score_rule');
            }
            if (! Schema::hasColumn('exams', 'settings')) {
                $table->json('settings')->nullable()->after('result_source');
            }
        });

        Schema::table('exam_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_rooms', 'exam_id')) {
                $table->foreignId('exam_id')->nullable()->after('id')->constrained('exams')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_rooms', 'capacity')) {
                $table->unsignedInteger('capacity')->default(0)->after('location');
            }
            if (! Schema::hasColumn('exam_rooms', 'computer_count')) {
                $table->unsignedInteger('computer_count')->default(0)->after('capacity');
            }
            if (! Schema::hasColumn('exam_rooms', 'headset_count')) {
                $table->unsignedInteger('headset_count')->default(0)->after('computer_count');
            }
            if (! Schema::hasColumn('exam_rooms', 'camera_available')) {
                $table->boolean('camera_available')->default(false)->after('headset_count');
            }
            if (! Schema::hasColumn('exam_rooms', 'internet_status')) {
                $table->string('internet_status')->nullable()->after('camera_available');
            }
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_sessions', 'session_name')) {
                $table->string('session_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('exam_sessions', 'session_date')) {
                $table->date('session_date')->nullable()->after('session_name');
            }
            if (! Schema::hasColumn('exam_sessions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('session_date');
            }
            if (! Schema::hasColumn('exam_sessions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('exam_sessions', 'allowed_grades')) {
                $table->json('allowed_grades')->nullable()->after('target_classes');
            }
            if (! Schema::hasColumn('exam_sessions', 'session_code')) {
                $table->text('session_code')->nullable()->after('allowed_grades');
            }
            if (! Schema::hasColumn('exam_sessions', 'code_visible_from')) {
                $table->timestamp('code_visible_from')->nullable()->after('session_code');
            }
        });

        if (! Schema::hasTable('exam_time_windows')) {
            Schema::create('exam_time_windows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
                $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->string('grade_group')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->unsignedInteger('max_duration_minutes')->default(30);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('exam_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_registrations', 'grade_id')) {
                $table->foreignId('grade_id')->nullable()->after('exam_session_id')->constrained('grades')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_registrations', 'school_class_id')) {
                $table->foreignId('school_class_id')->nullable()->after('grade_id')->constrained('school_classes')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_registrations', 'primary_external_account_id')) {
                $table->string('primary_external_account_id')->nullable()->after('ioe_id');
            }
            if (! Schema::hasColumn('exam_registrations', 'primary_external_username')) {
                $table->string('primary_external_username')->nullable()->after('primary_external_account_id');
            }
            if (! Schema::hasColumn('exam_registrations', 'backup_external_account_id')) {
                $table->string('backup_external_account_id')->nullable()->after('primary_external_username');
            }
            if (! Schema::hasColumn('exam_registrations', 'backup_external_username')) {
                $table->string('backup_external_username')->nullable()->after('backup_external_account_id');
            }
            if (! Schema::hasColumn('exam_registrations', 'requested_by_user_id')) {
                $table->foreignId('requested_by_user_id')->nullable()->after('registration_code')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_registrations', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_registrations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('exam_registrations', 'eligibility_snapshot')) {
                $table->json('eligibility_snapshot')->nullable()->after('rejection_reason');
            }
        });

        if (! Schema::hasTable('exam_councils')) {
            Schema::create('exam_councils', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->default('school')->index();
                $table->string('school_name')->nullable();
                $table->string('location')->nullable();
                $table->string('chairperson')->nullable();
                $table->string('secretary')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('proctor_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('proctor_assignments', 'exam_id')) {
                $table->foreignId('exam_id')->nullable()->after('id')->constrained('exams')->nullOnDelete();
            }
            if (! Schema::hasColumn('proctor_assignments', 'staff_profile_id')) {
                $table->foreignId('staff_profile_id')->nullable()->after('user_id')->constrained('staff_profiles')->nullOnDelete();
            }
            if (! Schema::hasColumn('proctor_assignments', 'exam_time_window_id')) {
                $table->foreignId('exam_time_window_id')->nullable()->after('exam_session_id')->constrained('exam_time_windows')->nullOnDelete();
            }
            if (! Schema::hasColumn('proctor_assignments', 'role_in_room')) {
                $table->string('role_in_room')->nullable()->after('role');
            }
            if (! Schema::hasColumn('proctor_assignments', 'status')) {
                $table->string('status')->default('active')->after('note')->index();
            }
        });

        if (! Schema::hasTable('exam_checklists')) {
            Schema::create('exam_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->nullable()->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_room_id')->nullable()->constrained('exam_rooms')->nullOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('exam_time_window_id')->nullable()->constrained('exam_time_windows')->nullOnDelete();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('internet_ok')->default(false);
                $table->boolean('computers_ok')->default(false);
                $table->boolean('headsets_ok')->default(false);
                $table->boolean('camera_ok')->default(false);
                $table->boolean('time_zone_ok')->default(false);
                $table->boolean('backup_power_network_ready')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();
                $table->unique(['exam_room_id', 'exam_session_id', 'exam_time_window_id'], 'exam_checklists_scope_unique');
            });
        }

        if (! Schema::hasTable('exam_attendance')) {
            Schema::create('exam_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_registration_id')->constrained('exam_registrations')->cascadeOnDelete();
                $table->foreignId('seat_assignment_id')->nullable()->constrained('seat_assignments')->nullOnDelete();
                $table->string('status')->default('present')->index();
                $table->timestamp('checked_in_at')->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['exam_registration_id', 'seat_assignment_id'], 'exam_attendance_registration_assignment_unique');
            });
        }

        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'exam_id')) {
                $table->foreignId('exam_id')->nullable()->after('id')->constrained('exams')->nullOnDelete();
            }
            if (! Schema::hasColumn('incidents', 'exam_room_id')) {
                $table->foreignId('exam_room_id')->nullable()->after('exam_id')->constrained('exam_rooms')->nullOnDelete();
            }
            if (! Schema::hasColumn('incidents', 'exam_session_id')) {
                $table->foreignId('exam_session_id')->nullable()->after('exam_room_id')->constrained('exam_sessions')->nullOnDelete();
            }
            if (! Schema::hasColumn('incidents', 'exam_time_window_id')) {
                $table->foreignId('exam_time_window_id')->nullable()->after('exam_session_id')->constrained('exam_time_windows')->nullOnDelete();
            }
            if (! Schema::hasColumn('incidents', 'result_impact')) {
                $table->string('result_impact')->default('none')->after('solution')->index();
            }
            if (! Schema::hasColumn('incidents', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('result_impact');
            }
        });

        if (! Schema::hasTable('exam_results')) {
            Schema::create('exam_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_registration_id')->constrained('exam_registrations')->cascadeOnDelete();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->string('external_account_type')->default('primary');
                $table->decimal('score', 6, 2)->nullable();
                $table->decimal('max_score', 6, 2)->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('result_status')->default('pending_review')->index();
                $table->string('source')->default('manual_entry')->index();
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id', 'grade_id'], 'exam_results_official_unique');
                $table->unique('exam_registration_id');
            });
        }

        if (! Schema::hasTable('exam_minutes')) {
            Schema::create('exam_minutes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_room_id')->nullable()->constrained('exam_rooms')->nullOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('exam_time_window_id')->nullable()->constrained('exam_time_windows')->nullOnDelete();
                $table->string('generated_file_path')->nullable();
                $table->string('signed_scan_path')->nullable();
                $table->string('status')->default('not_generated')->index();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['exam_room_id', 'exam_session_id', 'exam_time_window_id'], 'exam_minutes_scope_unique');
            });
        }

        if (! Schema::hasTable('video_evidence')) {
            Schema::create('video_evidence', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_room_id')->nullable()->constrained('exam_rooms')->nullOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('exam_time_window_id')->nullable()->constrained('exam_time_windows')->nullOnDelete();
                $table->string('video_url');
                $table->string('storage_provider')->default('other')->index();
                $table->boolean('visibility_checked')->default(false);
                $table->string('quality_status')->default('pending')->index();
                $table->string('duration_note')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
            });
        }

        $this->seedAcademicDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('video_evidence');
        Schema::dropIfExists('exam_minutes');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_attendance');
        Schema::dropIfExists('exam_checklists');
        Schema::dropIfExists('exam_councils');
        Schema::dropIfExists('exam_time_windows');
        Schema::dropIfExists('academic_results');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('academic_years');
    }

    private function seedAcademicDefaults(): void
    {
        if (Schema::hasTable('academic_years')) {
            DB::table('academic_years')->updateOrInsert(
                ['code' => '2025-2026'],
                [
                    'start_date' => '2025-09-01',
                    'end_date' => '2026-05-31',
                    'is_current' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('grades')) {
            foreach ([10, 11, 12] as $grade) {
                DB::table('grades')->updateOrInsert(
                    ['grade_number' => $grade],
                    [
                        'name' => 'Khối '.$grade,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
};
