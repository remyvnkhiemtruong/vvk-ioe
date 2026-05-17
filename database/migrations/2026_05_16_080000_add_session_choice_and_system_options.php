<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'exam_time')) {
                $table->time('exam_time')->nullable()->after('exam_date');
            }
            if (! Schema::hasColumn('exams', 'show_countdown')) {
                $table->boolean('show_countdown')->default(true)->after('publish_scores');
            }
            if (! Schema::hasColumn('exams', 'countdown_mode')) {
                $table->string('countdown_mode')->default('auto')->after('show_countdown');
            }
            if (! Schema::hasColumn('exams', 'allow_student_session_change')) {
                $table->boolean('allow_student_session_change')->default(true)->after('allow_student_edit');
            }
            if (! Schema::hasColumn('exams', 'require_session_choice')) {
                $table->boolean('require_session_choice')->default(true)->after('allow_student_session_change');
            }
            if (! Schema::hasColumn('exams', 'allow_personal_computer')) {
                $table->boolean('allow_personal_computer')->default(true)->after('require_session_choice');
            }
            if (! Schema::hasColumn('exams', 'auto_lock_full_sessions')) {
                $table->boolean('auto_lock_full_sessions')->default(true)->after('allow_personal_computer');
            }
            if (! Schema::hasColumn('exams', 'show_public_stats')) {
                $table->boolean('show_public_stats')->default(true)->after('auto_lock_full_sessions');
            }
        });

        Schema::table('exam_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_registrations', 'exam_session_id')) {
                $table->foreignId('exam_session_id')
                    ->nullable()
                    ->after('exam_id')
                    ->constrained('exam_sessions')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_registrations', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('note');
            }
        });

        Schema::table('exam_form_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_form_fields', 'help_text')) {
                $table->text('help_text')->nullable()->after('label');
            }
            if (! Schema::hasColumn('exam_form_fields', 'metadata')) {
                $table->json('metadata')->nullable()->after('options');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'note')) {
                $table->text('note')->nullable()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_form_fields', function (Blueprint $table) {
            if (Schema::hasColumn('exam_form_fields', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('exam_form_fields', 'help_text')) {
                $table->dropColumn('help_text');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'note')) {
                $table->dropColumn('note');
            }
        });

        Schema::table('exam_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('exam_registrations', 'exam_session_id')) {
                $table->dropConstrainedForeignId('exam_session_id');
            }
            if (Schema::hasColumn('exam_registrations', 'custom_fields')) {
                $table->dropColumn('custom_fields');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            foreach ([
                'show_public_stats',
                'auto_lock_full_sessions',
                'allow_personal_computer',
                'require_session_choice',
                'allow_student_session_change',
                'countdown_mode',
                'show_countdown',
                'exam_time',
            ] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
