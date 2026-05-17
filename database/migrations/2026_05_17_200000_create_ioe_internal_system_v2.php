<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration v2 – Hệ thống quản lý IOE nội bộ
 * Tạo các bảng mới theo spec nghiệp vụ đầy đủ.
 * Không xóa bảng cũ (exam_minutes, video_evidence, incidents) để không phá tests.
 * Giữ nguyên exam_time_windows, bổ sung columns còn thiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────────────
        // 1. exam_levels – Cấp thi
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('exam_levels')) {
            Schema::create('exam_levels', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique(); // school | ward | province | national
                $table->string('name');
                $table->unsignedTinyInteger('sort_order')->default(0)->index();
                $table->json('allowed_grades')->nullable(); // [1,2,...,12]
                $table->unsignedInteger('min_self_training_round')->default(0);
                $table->boolean('require_verified_account')->default(true);
                $table->boolean('require_previous_level_result')->default(false);
                $table->string('previous_level_code')->nullable(); // FK theo code
                $table->unsignedSmallInteger('min_previous_score_percent')->nullable();
                $table->json('max_score_by_grade')->nullable(); // {"10": 1000, "11": 1000, ...}
                $table->json('award_rules_config')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 2. Bổ sung exam_levels FK vào exams
        // ──────────────────────────────────────────────────────────────────────
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'exam_level_id')) {
                $table->foreignId('exam_level_id')->nullable()->after('level')
                    ->constrained('exam_levels')->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'timezone')) {
                $table->string('timezone')->default('Asia/Ho_Chi_Minh')->after('status');
            }
            if (! Schema::hasColumn('exams', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('timezone')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // ──────────────────────────────────────────────────────────────────────
        // 3. academic_years – bổ sung name
        // ──────────────────────────────────────────────────────────────────────
        Schema::table('academic_years', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_years', 'name')) {
                $table->string('name')->nullable()->after('code');
            }
            if (! Schema::hasColumn('academic_years', 'starts_at')) {
                $table->date('starts_at')->nullable()->after('name');
            }
            if (! Schema::hasColumn('academic_years', 'ends_at')) {
                $table->date('ends_at')->nullable()->after('starts_at');
            }
        });

        // ──────────────────────────────────────────────────────────────────────
        // 4. grades – bổ sung education_stage, numeric_level
        // ──────────────────────────────────────────────────────────────────────
        Schema::table('grades', function (Blueprint $table) {
            if (! Schema::hasColumn('grades', 'numeric_level')) {
                $table->unsignedTinyInteger('numeric_level')->nullable()->after('grade_number');
            }
            if (! Schema::hasColumn('grades', 'education_stage')) {
                // tieu_hoc | trung_hoc_co_so | trung_hoc_pho_thong
                $table->string('education_stage')->nullable()->after('numeric_level');
            }
        });

        // ──────────────────────────────────────────────────────────────────────
        // 5. exam_eligibility_rules – Điều kiện dự thi
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('exam_eligibility_rules')) {
            Schema::create('exam_eligibility_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->nullable()->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_level_id')->nullable()->constrained('exam_levels')->nullOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index(); // null = áp dụng tất cả
                $table->unsignedInteger('min_self_training_round')->default(0);
                $table->boolean('require_verified_account')->default(true);
                $table->boolean('require_previous_exam_result')->default(false);
                $table->foreignId('previous_exam_level_id')->nullable()->constrained('exam_levels')->nullOnDelete();
                $table->unsignedSmallInteger('min_previous_score')->nullable();
                $table->unsignedSmallInteger('min_previous_score_percent')->nullable();
                $table->unsignedSmallInteger('max_score')->default(2000);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['exam_id', 'exam_level_id', 'grade_number'], 'eligibility_rules_unique');
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 6. exam_students – Danh sách học sinh nội bộ
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('exam_students')) {
            Schema::create('exam_students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index();
                $table->string('class_name')->nullable()->index();
                $table->string('ioe_username')->nullable();
                $table->string('ioe_account_id')->nullable();
                $table->boolean('ioe_account_verified')->default(false);
                $table->unsignedInteger('self_training_round')->default(0);
                // Trạng thái tổng
                $table->string('status')->default('draft')->index();
                // draft | eligible | ineligible | selected | registered_on_ioe | assigned_to_slot | completed_exam | score_entered | ranked | cancelled
                $table->string('eligibility_status')->nullable()->index();
                // eligible | ineligible | pending
                $table->json('ineligible_reasons')->nullable();
                $table->boolean('registered_on_ioe')->default(false);
                $table->timestamp('registered_on_ioe_at')->nullable();
                $table->foreignId('assigned_time_slot_id')->nullable()->constrained('exam_time_windows')->nullOnDelete();
                $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('selected_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id'], 'exam_students_unique');
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 7. exam_time_windows – Bổ sung fields còn thiếu (alias = exam_time_slots)
        // ──────────────────────────────────────────────────────────────────────
        Schema::table('exam_time_windows', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_time_windows', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('exam_time_windows', 'grade_ids')) {
                $table->json('grade_ids')->nullable()->after('grade_id');
            }
            if (! Schema::hasColumn('exam_time_windows', 'code_reveal_before_minutes')) {
                $table->unsignedTinyInteger('code_reveal_before_minutes')->default(5)->after('max_duration_minutes');
            }
            if (! Schema::hasColumn('exam_time_windows', 'code_hide_after_start_minutes')) {
                $table->unsignedTinyInteger('code_hide_after_start_minutes')->default(5)->after('code_reveal_before_minutes');
            }
            if (! Schema::hasColumn('exam_time_windows', 'has_students')) {
                $table->boolean('has_students')->default(false)->after('code_hide_after_start_minutes')->index();
            }
            if (! Schema::hasColumn('exam_time_windows', 'student_count')) {
                $table->unsignedInteger('student_count')->default(0)->after('has_students');
            }
            if (! Schema::hasColumn('exam_time_windows', 'status')) {
                $table->string('status')->default('draft')->after('student_count')->index();
                // draft | ready | running | finished | cancelled
            }
        });

        // ──────────────────────────────────────────────────────────────────────
        // 8. exam_codes – Mã ca thi do admin nhập từ ioe.vn
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('exam_codes')) {
            Schema::create('exam_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->foreignId('exam_time_slot_id')->nullable()->constrained('exam_time_windows')->nullOnDelete();
                $table->string('code'); // Mã lấy từ ioe.vn, admin nhập thủ công
                $table->string('label')->nullable(); // Nhãn mô tả
                $table->json('applied_grade_ids')->nullable(); // Khối áp dụng
                $table->string('source')->default('manual_from_ioe'); // Không tự sinh mã
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['exam_id', 'exam_session_id', 'exam_time_slot_id']);
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 9. live_screens – Token màn hình /live
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('live_screens')) {
            Schema::create('live_screens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
                $table->string('token', 64)->unique();
                $table->boolean('is_enabled')->default(true)->index();
                $table->string('scope_type')->nullable(); // exam | session
                $table->string('display_title')->nullable();
                $table->boolean('admin_override_hide')->default(false); // Tạm ẩn mã
                $table->boolean('admin_override_show')->default(false); // Hiện mã thủ công
                $table->timestamp('force_ended_at')->nullable(); // Kết thúc sớm
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 10. student_scores – Điểm sau thi
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('student_scores')) {
            Schema::create('student_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('exam_student_id')->nullable()->constrained('exam_students')->nullOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index();
                $table->string('class_name')->nullable()->index();
                $table->decimal('score', 8, 2)->nullable();
                $table->decimal('max_score', 8, 2)->default(2000);
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('entered_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->string('status')->default('draft')->index();
                // draft | submitted | locked | ranked
                $table->boolean('exclude_from_awards')->default(false)->index();
                $table->text('exclude_reason')->nullable();
                $table->text('note')->nullable();
                $table->boolean('needs_rerank')->default(false)->index();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id'], 'student_scores_unique');
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 11. award_rules – Quy tắc xếp giải
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('award_rules')) {
            Schema::create('award_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->string('name');
                $table->string('scope'); // national | province | ward | school
                $table->unsignedTinyInteger('grade_number')->nullable()->index(); // null = tất cả khối
                $table->decimal('min_score', 8, 2)->nullable();
                $table->unsignedSmallInteger('min_score_percent')->nullable(); // % điểm tối đa
                $table->decimal('top_percent', 5, 2)->nullable(); // TOP % học sinh
                $table->unsignedInteger('max_awards')->nullable(); // Số giải tối đa
                $table->unsignedTinyInteger('priority_order')->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 12. award_rule_items – Chi tiết giải Nhất/Nhì/Ba/KK
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('award_rule_items')) {
            Schema::create('award_rule_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('award_rule_id')->constrained('award_rules')->cascadeOnDelete();
                $table->string('award_name'); // Giải Nhất, Giải Nhì, v.v.
                $table->string('award_code'); // first, second, third, encouragement, gold, silver, bronze
                $table->decimal('ratio_percent', 5, 2)->nullable(); // % trong số học sinh đủ điều kiện
                $table->unsignedInteger('max_quantity')->nullable(); // Số lượng tối đa
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 13. rankings – Bảng xếp hạng + xếp giải
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('rankings')) {
            Schema::create('rankings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('award_rule_id')->nullable()->constrained('award_rules')->nullOnDelete();
                $table->foreignId('student_score_id')->constrained('student_scores')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->unsignedTinyInteger('grade_number')->nullable()->index();
                $table->string('scope')->index(); // national | province | ward | school
                $table->unsignedInteger('scope_id')->nullable();
                $table->unsignedInteger('rank');
                $table->decimal('score', 8, 2);
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('award_name')->nullable();
                $table->string('award_code')->nullable();
                $table->boolean('is_highest_award')->default(false)->index();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->index(['exam_id', 'grade_number', 'scope']);
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // 14. province_award_groups – Bảng A/B/C
        // ──────────────────────────────────────────────────────────────────────
        if (! Schema::hasTable('province_award_groups')) {
            Schema::create('province_award_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                $table->string('province_name');
                $table->string('group_code'); // A | B | C
                $table->timestamps();
                $table->unique(['academic_year_id', 'province_name'], 'province_groups_unique');
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // Seed dữ liệu mặc định
        // ──────────────────────────────────────────────────────────────────────
        $this->seedExamLevels();
        $this->seedGrades();
        $this->seedProvinceGroups();
    }

    public function down(): void
    {
        Schema::dropIfExists('rankings');
        Schema::dropIfExists('award_rule_items');
        Schema::dropIfExists('award_rules');
        Schema::dropIfExists('student_scores');
        Schema::dropIfExists('live_screens');
        Schema::dropIfExists('exam_codes');
        Schema::dropIfExists('exam_students');
        Schema::dropIfExists('exam_eligibility_rules');
        Schema::dropIfExists('province_award_groups');
        Schema::dropIfExists('exam_levels');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Seed helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function seedExamLevels(): void
    {
        if (! Schema::hasTable('exam_levels')) {
            return;
        }

        $levels = [
            [
                'code' => 'school',
                'name' => 'Cấp trường',
                'sort_order' => 1,
                'allowed_grades' => json_encode(range(1, 9)),
                'min_self_training_round' => 15,
                'require_verified_account' => 1,
                'require_previous_level_result' => 0,
                'previous_level_code' => null,
                'min_previous_score_percent' => null,
                'max_score_by_grade' => json_encode([
                    '1' => 1000, '2' => 1000,
                    '3' => 2000, '4' => 2000, '5' => 2000,
                    '6' => 2000, '7' => 2000, '8' => 2000, '9' => 2000,
                ]),
                'is_active' => 1,
            ],
            [
                'code' => 'ward',
                'name' => 'Cấp xã/phường/đặc khu',
                'sort_order' => 2,
                'allowed_grades' => json_encode(range(1, 12)),
                'min_self_training_round' => 20,
                'require_verified_account' => 1,
                'require_previous_level_result' => 0,
                'previous_level_code' => null,
                'min_previous_score_percent' => null,
                'max_score_by_grade' => json_encode([
                    '1' => 1000, '2' => 1000,
                    '3' => 2000, '4' => 2000, '5' => 2000,
                    '6' => 2000, '7' => 2000, '8' => 2000, '9' => 2000,
                    '10' => 1000, '11' => 1000, '12' => 1000,
                ]),
                'is_active' => 1,
            ],
            [
                'code' => 'province',
                'name' => 'Cấp tỉnh/thành phố',
                'sort_order' => 3,
                'allowed_grades' => json_encode(range(1, 12)),
                'min_self_training_round' => 25,
                'require_verified_account' => 1,
                'require_previous_level_result' => 0,
                'previous_level_code' => null,
                'min_previous_score_percent' => null,
                'max_score_by_grade' => json_encode([
                    '1' => 1000, '2' => 1000,
                    '3' => 2000, '4' => 2000, '5' => 2000,
                    '6' => 2000, '7' => 2000, '8' => 2000, '9' => 2000,
                    '10' => 1000, '11' => 1000, '12' => 1000,
                ]),
                'is_active' => 1,
            ],
            [
                'code' => 'national',
                'name' => 'Cấp quốc gia',
                'sort_order' => 4,
                'allowed_grades' => json_encode([3, 4, 5, 6, 7, 8, 9, 10, 11]),
                'min_self_training_round' => 30,
                'require_verified_account' => 1,
                'require_previous_level_result' => 1,
                'previous_level_code' => 'province',
                'min_previous_score_percent' => 50,
                'max_score_by_grade' => json_encode([
                    '3' => 2000, '4' => 2000, '5' => 2000,
                    '6' => 2000, '7' => 2000, '8' => 2000, '9' => 2000,
                    '10' => 1000, '11' => 1000,
                ]),
                'is_active' => 1,
            ],
        ];

        foreach ($levels as $level) {
            DB::table('exam_levels')->updateOrInsert(
                ['code' => $level['code']],
                array_merge($level, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedGrades(): void
    {
        if (! Schema::hasTable('grades')) {
            return;
        }

        $grades = [];
        for ($i = 1; $i <= 12; $i++) {
            $stage = $i <= 5 ? 'tieu_hoc' : ($i <= 9 ? 'trung_hoc_co_so' : 'trung_hoc_pho_thong');
            $grades[] = [
                'grade_number' => $i,
                'numeric_level' => $i,
                'education_stage' => $stage,
                'name' => 'Khối '.$i,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($grades as $grade) {
            DB::table('grades')->updateOrInsert(
                ['grade_number' => $grade['grade_number']],
                $grade
            );
        }
    }

    private function seedProvinceGroups(): void
    {
        if (! Schema::hasTable('province_award_groups')) {
            return;
        }

        $groups = [
            'A' => ['Hà Nội', 'Hồ Chí Minh', 'Hải Phòng', 'Cần Thơ', 'Đà Nẵng', 'Huế'],
            'B' => [
                'An Giang', 'Bắc Ninh', 'Cà Mau', 'Đồng Nai', 'Đồng Tháp',
                'Hà Tĩnh', 'Hưng Yên', 'Khánh Hòa', 'Ninh Bình', 'Nghệ An',
                'Quảng Ngãi', 'Quảng Trị', 'Tây Ninh', 'Thanh Hóa', 'Vĩnh Long',
            ],
            'C' => [
                'Cao Bằng', 'Đắk Lắk', 'Điện Biên', 'Gia Lai', 'Lai Châu',
                'Lạng Sơn', 'Lào Cai', 'Lâm Đồng', 'Phú Thọ', 'Quảng Ninh',
                'Sơn La', 'Tuyên Quang', 'Thái Nguyên',
            ],
        ];

        foreach ($groups as $code => $provinces) {
            foreach ($provinces as $province) {
                DB::table('province_award_groups')->updateOrInsert(
                    ['academic_year_id' => null, 'province_name' => $province],
                    ['group_code' => $code, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
};
