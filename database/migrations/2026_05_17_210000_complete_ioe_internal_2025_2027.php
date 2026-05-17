<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('province_name')->nullable()->index();
                $table->string('ioe_management_id')->nullable()->index();
                $table->timestamps();
            });
        }

        Schema::table('academic_years', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_years', 'status')) {
                $table->string('status')->default('preparing')->after('end_date')->index();
            }
            if (! Schema::hasColumn('academic_years', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('is_current')->index();
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('academic_year_id')->constrained('schools')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'ioe_account_id')) {
                $table->string('ioe_account_id')->nullable()->after('student_code')->unique();
            }
            if (! Schema::hasColumn('students', 'normalized_name')) {
                $table->string('normalized_name')->nullable()->after('full_name')->index();
            }
            if (! Schema::hasColumn('students', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('grade_id')->index();
            }
            if (! Schema::hasColumn('students', 'current_self_training_round')) {
                $table->unsignedInteger('current_self_training_round')->default(0)->after('is_verified');
            }
            if (! Schema::hasColumn('students', 'imported_from_ioe')) {
                $table->boolean('imported_from_ioe')->default(false)->after('current_self_training_round')->index();
            }
            if (! Schema::hasColumn('students', 'source_academic_year')) {
                $table->string('source_academic_year')->nullable()->after('imported_from_ioe')->index();
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'code')) {
                $table->string('code')->nullable()->after('id')->unique();
            }
            if (! Schema::hasColumn('exams', 'source')) {
                $table->string('source')->nullable()->after('result_source')->index();
            }
            if (! Schema::hasColumn('exams', 'has_imported_results')) {
                $table->boolean('has_imported_results')->default(false)->after('source')->index();
            }
            if (! Schema::hasColumn('exams', 'imported_results_count')) {
                $table->unsignedInteger('imported_results_count')->default(0)->after('has_imported_results');
            }
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_sessions', 'code')) {
                $table->string('code')->nullable()->after('id')->unique();
            }
            if (! Schema::hasColumn('exam_sessions', 'session_period')) {
                $table->string('session_period')->nullable()->after('session_date')->index();
            }
            if (! Schema::hasColumn('exam_sessions', 'source')) {
                $table->string('source')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('exam_sessions', 'mapping_status')) {
                $table->string('mapping_status')->nullable()->after('source')->index();
            }
            if (! Schema::hasColumn('exam_sessions', 'official_reference_session_id')) {
                $table->foreignId('official_reference_session_id')->nullable()->after('mapping_status')->constrained('exam_sessions')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_sessions', 'import_note')) {
                $table->text('import_note')->nullable()->after('official_reference_session_id');
            }
        });

        Schema::table('exam_time_windows', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_time_windows', 'code')) {
                $table->string('code')->nullable()->after('id')->unique();
            }
            if (! Schema::hasColumn('exam_time_windows', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->default(30)->after('ends_at');
            }
            if (! Schema::hasColumn('exam_time_windows', 'source')) {
                $table->string('source')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('exam_time_windows', 'mapping_status')) {
                $table->string('mapping_status')->nullable()->after('source')->index();
            }
        });

        Schema::table('exam_students', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_students', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('grade_number')->constrained('schools')->nullOnDelete();
            }
        });

        Schema::table('live_screens', function (Blueprint $table) {
            if (! Schema::hasColumn('live_screens', 'scope_id')) {
                $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type')->index();
            }
        });

        Schema::table('student_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('student_scores', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('grade_number')->constrained('schools')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_scores', 'raw_duration_text')) {
                $table->string('raw_duration_text')->nullable()->after('duration_seconds');
            }
            if (! Schema::hasColumn('student_scores', 'raw_exam_taken_at')) {
                $table->timestamp('raw_exam_taken_at')->nullable()->after('raw_duration_text')->index();
            }
            if (! Schema::hasColumn('student_scores', 'exam_session_id')) {
                $table->foreignId('exam_session_id')->nullable()->after('raw_exam_taken_at')->constrained('exam_sessions')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_scores', 'exam_time_slot_id')) {
                $table->foreignId('exam_time_slot_id')->nullable()->after('exam_session_id')->constrained('exam_time_windows')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_scores', 'source_key')) {
                $table->string('source_key')->nullable()->after('exam_time_slot_id')->index();
            }
            if (! Schema::hasColumn('student_scores', 'mapping_status')) {
                $table->string('mapping_status')->nullable()->after('source_key')->index();
            }
            if (! Schema::hasColumn('student_scores', 'mapping_note')) {
                $table->text('mapping_note')->nullable()->after('mapping_status');
            }
            if (! Schema::hasColumn('student_scores', 'imported_from_file')) {
                $table->string('imported_from_file')->nullable()->after('mapping_note');
            }
        });

        if (! Schema::hasTable('self_training_progress')) {
            Schema::create('self_training_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index();
                $table->string('class_name')->nullable()->index();
                $table->unsignedInteger('round_number')->default(0);
                $table->unsignedInteger('total_score')->default(0);
                $table->unsignedInteger('total_duration_seconds')->default(0);
                $table->string('source_key')->index();
                $table->string('imported_from_file')->nullable();
                $table->timestamps();
                $table->unique(['academic_year_id', 'student_id', 'source_key'], 'self_training_business_unique');
            });
        }

        if (! Schema::hasTable('award_records')) {
            Schema::create('award_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('student_score_id')->nullable()->constrained('student_scores')->nullOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index();
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->string('award_scope')->index();
                $table->string('award_name');
                $table->string('award_code')->nullable()->index();
                $table->decimal('score', 8, 2)->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('raw_duration_text')->nullable();
                $table->text('raw_award_text');
                $table->string('source_key')->index();
                $table->string('mapping_status')->nullable()->index();
                $table->string('imported_from_file')->nullable();
                $table->boolean('is_highest_award')->default(false)->index();
                $table->string('status')->default('imported')->index();
                $table->timestamps();
                $table->unique(['source_key', 'student_id', 'award_scope', 'raw_award_text'], 'award_records_business_unique');
            });
        }

        if (! Schema::hasTable('academic_year_students')) {
            Schema::create('academic_year_students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('previous_academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                $table->string('previous_status')->nullable();
                $table->string('carry_over_reason')->nullable();
                $table->foreignId('current_grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->foreignId('previous_grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->unsignedTinyInteger('current_grade_number')->nullable()->index();
                $table->unsignedTinyInteger('previous_grade_number')->nullable()->index();
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->string('class_name')->nullable();
                $table->string('status')->default('awaiting_announcement')->index();
                $table->string('eligibility_status')->default('pending_official_rules')->index();
                $table->string('registration_status')->default('not_registered_yet')->index();
                $table->string('score_status')->default('no_score')->index();
                $table->string('award_status')->default('no_award')->index();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['academic_year_id', 'student_id'], 'academic_year_students_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_year_students');
        Schema::dropIfExists('award_records');
        Schema::dropIfExists('self_training_progress');
    }
};
