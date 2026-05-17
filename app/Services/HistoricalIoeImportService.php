<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AwardRecord;
use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\ExamEligibilityRule;
use App\Models\ExamLevel;
use App\Models\ExamSession;
use App\Models\ExamStudent;
use App\Models\ExamTimeWindow;
use App\Models\Grade;
use App\Models\School;
use App\Models\SelfTrainingProgress;
use App\Models\Student;
use App\Models\StudentScore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistoricalIoeImportService
{
    private const SCHOOL_NAME = 'Trường THPT Võ Văn Kiệt';
    private const PROVINCE_NAME = 'Tỉnh Cà Mau';
    private const IOE_MANAGEMENT_ID = '1313056684';
    private const TZ = 'Asia/Ho_Chi_Minh';

    private array $summary = [];
    private ?AcademicYear $year2025 = null;
    private ?School $school = null;

    public function __construct(private readonly AcademicYearRolloverService $rolloverService) {}

    public function import(bool $dryRun = false): array
    {
        $this->summary = $this->emptySummary();

        DB::beginTransaction();

        try {
            $this->seedReferenceData();
            $this->seedSchedules();
            $this->importSelfTraining();
            $this->importScores('result_2026_01_12', self::WARD_RESULT_CSV, 'ioe_2025_2026_ward', 'date_mismatch_keep_raw_date', self::WARD_RESULT_FILE);
            $this->importScores('result_2026_03_09', self::PROVINCE_RESULT_CSV, 'ioe_2025_2026_province', 'matched_official_schedule', self::PROVINCE_RESULT_FILE);
            $this->importScores('result_2026_04_03', self::NATIONAL_RESULT_CSV, 'ioe_2025_2026_national', 'matched_official_schedule', self::NATIONAL_RESULT_FILE);
            $this->importAwards('award_province_2025_2026', self::AWARD_PROVINCE_CSV, 'province', self::AWARD_PROVINCE_FILE);
            $this->importAwards('award_school_2025_2026', self::AWARD_SCHOOL_CSV, 'school', self::AWARD_SCHOOL_FILE);
            $this->markHighestImportedAwards();
            $this->updateImportedCounts();
            $this->summary['rollover'] = $this->rolloverService->rollover('2025-2026', '2026-2027', false, false);

            $dryRun ? DB::rollBack() : DB::commit();

            return array_merge($this->summary, ['dry_run' => $dryRun]);
        } catch (\Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
        }
    }

    public function parseDuration(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $normalized = Str::ascii(Str::lower($value));
        preg_match('/(\d+)\s*phut/', $normalized, $minutes);
        preg_match('/(\d+)\s*giay/', $normalized, $seconds);

        return ((int) ($minutes[1] ?? 0) * 60) + (int) ($seconds[1] ?? 0);
    }

    private function emptySummary(): array
    {
        return [
            'academic_years_created' => 0,
            'academic_years_updated' => 0,
            'academic_years_skipped' => 0,
            'schools_created' => 0,
            'schools_updated' => 0,
            'schools_skipped' => 0,
            'exams_created' => 0,
            'exams_updated' => 0,
            'exams_skipped' => 0,
            'exam_sessions_created' => 0,
            'exam_sessions_updated' => 0,
            'exam_sessions_skipped' => 0,
            'exam_time_windows_created' => 0,
            'exam_time_windows_updated' => 0,
            'exam_time_windows_skipped' => 0,
            'students_created' => 0,
            'students_updated' => 0,
            'students_skipped' => 0,
            'self_training_created' => 0,
            'self_training_updated' => 0,
            'self_training_skipped' => 0,
            'student_scores_created' => 0,
            'student_scores_updated' => 0,
            'student_scores_skipped' => 0,
            'award_records_created' => 0,
            'award_records_updated' => 0,
            'award_records_skipped' => 0,
        ];
    }

    private function seedReferenceData(): void
    {
        $this->year2025 = $this->upsert(AcademicYear::class, ['code' => '2025-2026'], [
            'name' => 'Năm học 2025 - 2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-05-31',
            'starts_at' => '2025-09-01',
            'ends_at' => '2026-05-31',
            'status' => 'archived',
            'is_current' => false,
            'is_active' => false,
        ], 'academic_years');

        $this->upsert(AcademicYear::class, ['code' => '2026-2027'], [
            'name' => 'Năm học 2026 - 2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-05-31',
            'starts_at' => '2026-09-01',
            'ends_at' => '2027-05-31',
            'status' => 'current',
            'is_current' => true,
            'is_active' => true,
        ], 'academic_years');

        $this->school = $this->upsert(School::class, ['name' => self::SCHOOL_NAME], [
            'province_name' => self::PROVINCE_NAME,
            'ioe_management_id' => self::IOE_MANAGEMENT_ID,
        ], 'schools');

        for ($grade = 1; $grade <= 12; $grade++) {
            Grade::updateOrCreate(
                ['grade_number' => $grade],
                [
                    'numeric_level' => $grade,
                    'education_stage' => $grade <= 5 ? 'tieu_hoc' : ($grade <= 9 ? 'trung_hoc_co_so' : 'trung_hoc_pho_thong'),
                    'name' => 'Khối '.$grade,
                    'status' => 'active',
                ]
            );
        }

        $this->seedExamLevels();
    }

    private function seedExamLevels(): void
    {
        $configs = [
            'school' => ['Cấp trường', range(1, 9), 15, false, null, null],
            'ward' => ['Cấp xã/phường/đặc khu', range(1, 12), 20, false, null, null],
            'province' => ['Cấp tỉnh/thành phố', range(1, 12), 25, false, null, null],
            'national' => ['Cấp quốc gia', [3, 4, 5, 6, 7, 8, 9, 10, 11], 30, true, 'province', 50],
        ];

        $order = 1;
        foreach ($configs as $code => [$name, $grades, $round, $requiresPrevious, $previousCode, $previousPercent]) {
            ExamLevel::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'sort_order' => $order++,
                    'allowed_grades' => $grades,
                    'min_self_training_round' => $round,
                    'require_verified_account' => true,
                    'require_previous_level_result' => $requiresPrevious,
                    'previous_level_code' => $previousCode,
                    'min_previous_score_percent' => $previousPercent,
                    'max_score_by_grade' => $this->maxScoreMap($code),
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedSchedules(): void
    {
        $school = $this->exam('ioe_2025_2026_school', 'IOE 2025-2026 - Cấp trường', 'school', range(1, 9), false);
        $ward = $this->exam('ioe_2025_2026_ward', 'IOE 2025-2026 - Cấp xã/phường/đặc khu', 'ward', range(1, 12), true);
        $province = $this->exam('ioe_2025_2026_province', 'IOE 2025-2026 - Cấp tỉnh/thành phố', 'province', range(1, 12), true);
        $national = $this->exam('ioe_2025_2026_national', 'IOE 2025-2026 - Cấp quốc gia', 'national', [3, 4, 5, 6, 7, 8, 9, 10, 11], true);

        $this->seedDefaultEligibilityRules([$school, $ward, $province, $national]);
        $this->seedDefaultAwardRules([$school, $ward, $province, $national]);

        $schoolSessions = [
            ['school_session_1', 'Sáng thứ năm 27/11/2025', '2025-11-27', 'morning', '07:30', '11:00'],
            ['school_session_2', 'Chiều thứ năm 27/11/2025', '2025-11-27', 'afternoon', '13:30', '17:00'],
            ['school_session_3', 'Sáng thứ sáu 28/11/2025', '2025-11-28', 'morning', '07:30', '11:00'],
            ['school_session_4', 'Chiều thứ sáu 28/11/2025', '2025-11-28', 'afternoon', '13:30', '17:00'],
            ['school_session_5', 'Sáng thứ bảy 29/11/2025', '2025-11-29', 'morning', '07:30', '11:00'],
            ['school_session_6', 'Chiều thứ bảy 29/11/2025', '2025-11-29', 'afternoon', '13:30', '17:00'],
        ];
        foreach ($schoolSessions as $item) {
            $session = $this->session($school, ...$item);
            $this->standardSlots($session, $item[2], $item[3], $item[0], []);
        }

        $wardSessions = [
            ['ward_session_1', 'Sáng thứ năm 08/01/2026', '2026-01-08', 'morning', '07:30', '11:00'],
            ['ward_session_2', 'Chiều thứ năm 08/01/2026', '2026-01-08', 'afternoon', '13:30', '17:00'],
            ['ward_session_3', 'Sáng thứ sáu 09/01/2026', '2026-01-09', 'morning', '07:30', '11:00'],
            ['ward_session_4', 'Sáng thứ bảy 10/01/2026', '2026-01-10', 'morning', '07:30', '11:00'],
        ];
        foreach ($wardSessions as $item) {
            $session = $this->session($ward, ...$item);
            $this->standardSlots($session, $item[2], $item[3], $item[0], []);
        }

        $importSession = $this->session(
            $ward,
            'ward_import_session_2026_01_12',
            'Ca import kết quả thực tế từ IOE - 12/01/2026',
            '2026-01-12',
            'afternoon',
            '13:30',
            '17:00',
            'completed',
            'imported_ioe_result_file',
            'date_mismatch_keep_raw_date',
            'Ngày trong file không trùng lịch BTC chính thức; giữ nguyên raw date và map theo pattern khối/giờ ca chiều.'
        );
        $this->slot($importSession, 'ward_import_2026_01_12_g10_1330', 'Khối 10 - 13:30', [10], '2026-01-12 13:30:00', '2026-01-12 14:00:00', true, 10, 'imported_ioe_result_file', 'date_mismatch_keep_raw_date');
        $this->slot($importSession, 'ward_import_2026_01_12_g11_1430', 'Khối 11 - 14:30', [11], '2026-01-12 14:30:00', '2026-01-12 15:00:00', true, 5, 'imported_ioe_result_file', 'date_mismatch_keep_raw_date');
        $this->slot($importSession, 'ward_import_2026_01_12_g12_1530', 'Khối 12 - 15:30', [12], '2026-01-12 15:30:00', '2026-01-12 16:00:00', true, 11, 'imported_ioe_result_file', 'date_mismatch_keep_raw_date');

        $provinceSessions = [
            ['province_session_1', 'Sáng thứ năm 05/03/2026', '2026-03-05', 'morning', '07:30', '11:00'],
            ['province_session_2', 'Chiều thứ năm 05/03/2026', '2026-03-05', 'afternoon', '13:30', '17:00'],
            ['province_session_3', 'Sáng thứ sáu 06/03/2026', '2026-03-06', 'morning', '07:30', '11:00'],
            ['province_session_4', 'Sáng thứ bảy 07/03/2026', '2026-03-07', 'morning', '07:30', '11:00'],
            ['province_session_5', 'Chiều thứ hai 09/03/2026', '2026-03-09', 'afternoon', '13:30', '17:00'],
        ];
        foreach ($provinceSessions as $item) {
            $session = $this->session($province, ...$item);
            $counts = $item[0] === 'province_session_5' ? ['10' => 10, '11' => 5, '12' => 7] : [];
            $this->standardSlots($session, $item[2], $item[3], $item[0], $counts);
        }

        $session1 = $this->session($national, 'national_session_1', 'Sáng thứ năm 02/04/2026', '2026-04-02', 'morning', '07:30', '10:00');
        $this->slot($session1, 'national_session_1_g3_0730', 'Khối 3 - 07:30', [3], '2026-04-02 07:30:00', '2026-04-02 08:00:00');
        $this->slot($session1, 'national_session_1_g4_0830', 'Khối 4 - 08:30', [4], '2026-04-02 08:30:00', '2026-04-02 09:00:00');
        $this->slot($session1, 'national_session_1_g5_0930', 'Khối 5 - 09:30', [5], '2026-04-02 09:30:00', '2026-04-02 10:00:00');

        $session2 = $this->session($national, 'national_session_2', 'Sáng thứ sáu 03/04/2026', '2026-04-03', 'morning', '07:30', '11:00');
        $this->slot($session2, 'national_session_2_g6_0730', 'Khối 6 - 07:30', [6], '2026-04-03 07:30:00', '2026-04-03 08:00:00');
        $this->slot($session2, 'national_session_2_g7_g11_0830', 'Khối 7, 11 - 08:30', [7, 11], '2026-04-03 08:30:00', '2026-04-03 09:00:00', true, 5);
        $this->slot($session2, 'national_session_2_g8_g10_0930', 'Khối 8, 10 - 09:30', [8, 10], '2026-04-03 09:30:00', '2026-04-03 10:00:00', true, 8);
        $this->slot($session2, 'national_session_2_g9_1030', 'Khối 9 - 10:30', [9], '2026-04-03 10:30:00', '2026-04-03 11:00:00');
    }

    private function exam(string $code, string $name, string $levelCode, array $targetGrades, bool $hasImportedResults): Exam
    {
        $level = ExamLevel::where('code', $levelCode)->firstOrFail();

        return $this->upsert(Exam::class, ['code' => $code], [
            'name' => $name,
            'school_year' => '2025-2026',
            'academic_year_id' => $this->year2025->id,
            'exam_level_id' => $level->id,
            'level' => $levelCode,
            'template_type' => match ($levelCode) {
                'ward' => 'xa_phuong',
                'province' => 'tinh',
                'national' => 'quoc_gia',
                default => 'truong',
            },
            'registration_mode' => 'admin_assign_session',
            'external_platform_name' => 'IOE',
            'organizer_scope' => $levelCode,
            'target_grades' => $targetGrades,
            'status' => 'completed',
            'timezone' => self::TZ,
            'source' => 'btc_official_schedule',
            'result_source' => 'imported_ioe_result_file',
            'has_imported_results' => $hasImportedResults,
            'description' => $levelCode === 'ward'
                ? 'Với khối THPT, dữ liệu kết quả/vinh danh trong bộ file được hiểu là nhóm THPT/toàn trường theo hướng dẫn cấp xã/phường.'
                : null,
        ], 'exams');
    }

    private function session(
        Exam $exam,
        string $code,
        string $name,
        string $date,
        string $period,
        string $start,
        string $end,
        string $status = 'completed',
        string $source = 'btc_official_schedule',
        ?string $mappingStatus = null,
        ?string $note = null
    ): ExamSession {
        return $this->upsert(ExamSession::class, ['code' => $code], [
            'exam_id' => $exam->id,
            'name' => $name,
            'session_name' => $name,
            'session_date' => $date,
            'session_period' => $period,
            'exam_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'starts_at' => "{$date} {$start}:00",
            'ends_at' => "{$date} {$end}:00",
            'status' => $status,
            'source' => $source,
            'mapping_status' => $mappingStatus,
            'import_note' => $note,
            'note' => $note,
            'session_code' => $code,
            'max_candidates' => 999,
        ], 'exam_sessions');
    }

    private function standardSlots(ExamSession $session, string $date, string $period, string $prefix, array $counts): void
    {
        $slots = $period === 'morning'
            ? [
                ['g3_g9_g10_0730', [3, 9, 10], '07:30', '08:00'],
                ['g1_g2_g6_g11_0830', [1, 2, 6, 11], '08:30', '09:00'],
                ['g4_g7_g12_0930', [4, 7, 12], '09:30', '10:00'],
                ['g5_g8_1030', [5, 8], '10:30', '11:00'],
            ]
            : [
                ['g3_g9_g10_1330', [3, 9, 10], '13:30', '14:00'],
                ['g1_g2_g6_g11_1430', [1, 2, 6, 11], '14:30', '15:00'],
                ['g4_g7_g12_1530', [4, 7, 12], '15:30', '16:00'],
                ['g5_g8_1630', [5, 8], '16:30', '17:00'],
            ];

        foreach ($slots as [$suffix, $grades, $start, $end]) {
            $studentCount = collect($grades)->sum(fn ($grade) => (int) ($counts[(string) $grade] ?? 0));
            $this->slot(
                $session,
                $prefix.'_'.$suffix,
                'Khối '.implode(', ', $grades).' - '.$start,
                $grades,
                "{$date} {$start}:00",
                "{$date} {$end}:00",
                $studentCount > 0,
                $studentCount
            );
        }
    }

    private function slot(
        ExamSession $session,
        string $code,
        string $name,
        array $grades,
        string $startsAt,
        string $endsAt,
        bool $hasStudents = false,
        int $studentCount = 0,
        string $source = 'btc_official_schedule',
        ?string $mappingStatus = null
    ): ExamTimeWindow {
        return $this->upsert(ExamTimeWindow::class, ['code' => $code], [
            'exam_session_id' => $session->id,
            'name' => $name,
            'grade_ids' => $grades,
            'grade_group' => 'Khối '.implode(', ', $grades),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => 30,
            'max_duration_minutes' => 30,
            'code_reveal_before_minutes' => 5,
            'code_hide_after_start_minutes' => 5,
            'has_students' => $hasStudents,
            'student_count' => $studentCount,
            'status' => 'completed',
            'source' => $source,
            'mapping_status' => $mappingStatus,
        ], 'exam_time_windows');
    }

    private function seedDefaultEligibilityRules(array $exams): void
    {
        foreach ($exams as $exam) {
            $level = $exam->examLevel;
            if (! $level) {
                continue;
            }

            ExamEligibilityRule::updateOrCreate(
                ['exam_id' => $exam->id, 'exam_level_id' => $level->id, 'grade_number' => null],
                [
                    'min_self_training_round' => $level->min_self_training_round,
                    'require_verified_account' => true,
                    'require_previous_exam_result' => $level->require_previous_level_result,
                    'previous_exam_level_id' => $level->previous_level_code ? ExamLevel::where('code', $level->previous_level_code)->value('id') : null,
                    'min_previous_score_percent' => $level->min_previous_score_percent,
                    'max_score' => 2000,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedDefaultAwardRules(array $exams): void
    {
        foreach ($exams as $exam) {
            foreach ([['school', 50, 20, 4], ['province', 50, 20, 2], ['national', 80, null, 1]] as [$scope, $minPercent, $topPercent, $priority]) {
                $rule = AwardRule::updateOrCreate(
                    ['exam_id' => $exam->id, 'scope' => $scope, 'grade_number' => null],
                    [
                        'name' => 'Mặc định '.$scope,
                        'min_score_percent' => $minPercent,
                        'top_percent' => $topPercent,
                        'priority_order' => $priority,
                        'is_active' => true,
                    ]
                );

                foreach ([['Nhất', 'first', 10, 1], ['Nhì', 'second', 20, 2], ['Ba', 'third', 30, 3], ['Khuyến khích', 'encouragement', 40, 4]] as [$name, $code, $ratio, $sort]) {
                    AwardRuleItem::updateOrCreate(
                        ['award_rule_id' => $rule->id, 'award_code' => $code],
                        ['award_name' => $name, 'ratio_percent' => $ratio, 'sort_order' => $sort]
                    );
                }
            }
        }
    }

    private function importSelfTraining(): void
    {
        foreach ($this->rows(self::SELF_TRAINING_CSV) as $row) {
            $student = $this->studentFromRow($row, true);
            $round = (int) $row['Vòng'];

            $this->upsert(SelfTrainingProgress::class, [
                'academic_year_id' => $this->year2025->id,
                'student_id' => $student->id,
                'source_key' => 'self_training_2025_2026',
            ], [
                'grade_number' => (int) $row['Khối'],
                'class_name' => trim($row['Lớp']),
                'round_number' => $round,
                'total_score' => (int) $row['Tổng điểm thi'],
                'total_duration_seconds' => (int) $row['Tổng thời gian tự luyện'],
                'imported_from_file' => self::SELF_TRAINING_FILE,
            ], 'self_training');

            if ($student->current_self_training_round < $round) {
                $student->update(['current_self_training_round' => $round]);
            }
        }
    }

    private function importScores(string $sourceKey, string $csv, string $examCode, string $mappingStatus, string $fileName): void
    {
        $exam = Exam::where('code', $examCode)->firstOrFail();

        foreach ($this->rows($csv) as $row) {
            $student = $this->studentFromRow($row, true);
            $grade = (int) $row['Khối'];
            $slot = $this->slotForScore($sourceKey, $grade);

            $examStudent = ExamStudent::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $student->id],
                [
                    'grade_number' => $grade,
                    'school_id' => $this->school->id,
                    'class_name' => trim($row['Lớp']),
                    'ioe_account_id' => trim($row['ID (Mã tài khoản)']),
                    'ioe_account_verified' => true,
                    'self_training_round' => max((int) $student->current_self_training_round, 0),
                    'status' => 'score_entered',
                    'eligibility_status' => 'eligible',
                    'registered_on_ioe' => true,
                    'registered_on_ioe_at' => $this->parseDateTime($row['Ngày thi']),
                    'assigned_time_slot_id' => $slot?->id,
                ]
            );

            $this->upsert(StudentScore::class, [
                'exam_id' => $exam->id,
                'student_id' => $student->id,
            ], [
                'exam_student_id' => $examStudent->id,
                'grade_number' => $grade,
                'school_id' => $this->school->id,
                'class_name' => trim($row['Lớp']),
                'score' => (float) $row['Điểm thi'],
                'max_score' => $this->maxScore($exam->level, $grade),
                'duration_seconds' => $this->parseDuration($row['Thời gian thi']),
                'raw_duration_text' => $row['Thời gian thi'],
                'raw_exam_taken_at' => $this->parseDateTime($row['Ngày thi']),
                'exam_session_id' => $slot?->exam_session_id,
                'exam_time_slot_id' => $slot?->id,
                'source_key' => $sourceKey,
                'mapping_status' => $mappingStatus,
                'imported_from_file' => $fileName,
                'status' => StudentScore::STATUS_LOCKED,
                'entered_at' => $this->parseDateTime($row['Ngày thi']),
            ], 'student_scores');
        }
    }

    private function importAwards(string $sourceKey, string $csv, string $scope, string $fileName): void
    {
        $exam = Exam::where('code', 'ioe_2025_2026_ward')->firstOrFail();

        foreach ($this->rows($csv) as $row) {
            $student = $this->studentFromRow([
                'ID (Mã tài khoản)' => $row['ID (Mã tài khoản)'],
                'Họ và Tên (Tên đầy đủ Tiếng Việt có dấu)' => $row['Họ và Tên (Tên đầy đủ Tiếng Việt có dấu)'],
                'Khối' => $row['Khối'],
                'Lớp' => null,
                'Ngày sinh' => $row['Ngày sinh (Ngày/tháng/năm)'],
            ], false);
            $duration = $this->parseDuration($row['Thời gian']);
            $grade = (int) $row['Khối'];
            $score = (float) $row['Điểm'];
            $studentScore = StudentScore::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->where('grade_number', $grade)
                ->where('score', $score)
                ->where('duration_seconds', $duration)
                ->first();
            [$awardName, $awardCode] = $this->awardFromText($row['Kết quả vinh danh']);

            $this->upsert(AwardRecord::class, [
                'source_key' => $sourceKey,
                'student_id' => $student->id,
                'award_scope' => $scope,
                'raw_award_text' => $row['Kết quả vinh danh'],
            ], [
                'academic_year_id' => $this->year2025->id,
                'exam_id' => $exam->id,
                'student_score_id' => $studentScore?->id,
                'grade_number' => $grade,
                'school_id' => $this->school->id,
                'award_name' => $awardName,
                'award_code' => $awardCode,
                'score' => $score,
                'duration_seconds' => $duration,
                'raw_duration_text' => $row['Thời gian'],
                'mapping_status' => $studentScore ? 'matched_by_score_and_raw_award_text' : 'pending_score_match',
                'imported_from_file' => $fileName,
                'status' => 'imported',
            ], 'award_records');
        }
    }

    private function markHighestImportedAwards(): void
    {
        AwardRecord::where('academic_year_id', $this->year2025->id)->update(['is_highest_award' => false]);
        $priority = ['national' => 1, 'province' => 2, 'ward' => 3, 'school' => 4];

        AwardRecord::where('academic_year_id', $this->year2025->id)
            ->get()
            ->groupBy('student_id')
            ->each(function ($records) use ($priority): void {
                $highest = $records->sortBy(fn (AwardRecord $record) => $priority[$record->award_scope] ?? 99)->first();
                $highest?->update(['is_highest_award' => true]);
            });
    }

    private function updateImportedCounts(): void
    {
        foreach (Exam::whereIn('code', ['ioe_2025_2026_school', 'ioe_2025_2026_ward', 'ioe_2025_2026_province', 'ioe_2025_2026_national'])->get() as $exam) {
            $count = StudentScore::where('exam_id', $exam->id)->count();
            $exam->update([
                'imported_results_count' => $count,
                'has_imported_results' => $count > 0,
            ]);
        }
    }

    private function studentFromRow(array $row, bool $updateClass): Student
    {
        $ioeId = trim((string) $row['ID (Mã tài khoản)']);
        $grade = (int) $row['Khối'];
        $className = trim((string) ($row['Lớp'] ?? '')) ?: null;
        $name = trim((string) $row['Họ và Tên (Tên đầy đủ Tiếng Việt có dấu)']);
        $birth = $this->parseBirthDate($row['Ngày sinh'] ?? null);

        $student = Student::firstOrNew(['student_code' => $ioeId]);
        $exists = $student->exists;
        $payload = [
            'full_name' => $name,
            'normalized_name' => Str::ascii(Str::lower($name)),
            'grade' => $updateClass || ! $student->grade ? $grade : $student->grade,
            'grade_id' => $this->gradeId($updateClass || ! $student->grade ? $grade : (int) $student->grade),
            'school_id' => $this->school?->id,
            'ioe_account_id' => $ioeId,
            'date_of_birth' => $birth ?: $student->date_of_birth,
            'class_name' => ($updateClass && $className) || ! $student->class_name ? ($className ?: $student->class_name) : $student->class_name,
            'is_verified' => true,
            'imported_from_ioe' => true,
            'source_academic_year' => '2025-2026',
            'status' => 'active',
        ];
        $student->fill($payload);

        if (! $exists) {
            $student->save();
            $this->summary['students_created']++;
        } elseif ($student->isDirty()) {
            $student->save();
            $this->summary['students_updated']++;
        } else {
            $this->summary['students_skipped']++;
        }

        return $student;
    }

    private function rows(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $headers = str_getcsv(array_shift($lines), ';');
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, ';');
            $rows[] = array_combine($headers, array_pad($values, count($headers), null));
        }

        return $rows;
    }

    private function upsert(string $modelClass, array $keys, array $values, string $counter): Model
    {
        /** @var Model $model */
        $model = $modelClass::firstOrNew($keys);
        $exists = $model->exists;
        $model->fill($values);

        if (! $exists) {
            $model->save();
            $this->summary[$counter.'_created']++;
        } elseif ($model->isDirty()) {
            $model->save();
            $this->summary[$counter.'_updated']++;
        } else {
            $this->summary[$counter.'_skipped']++;
        }

        return $model;
    }

    private function gradeId(int $gradeNumber): ?int
    {
        return Grade::firstOrCreate(
            ['grade_number' => $gradeNumber],
            [
                'numeric_level' => $gradeNumber,
                'education_stage' => $gradeNumber <= 5 ? 'tieu_hoc' : ($gradeNumber <= 9 ? 'trung_hoc_co_so' : 'trung_hoc_pho_thong'),
                'name' => 'Khối '.$gradeNumber,
                'status' => 'active',
            ]
        )->id;
    }

    private function parseBirthDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, self::TZ)->toDateString();
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value, self::TZ);
    }

    private function slotForScore(string $sourceKey, int $grade): ?ExamTimeWindow
    {
        $code = match ($sourceKey.'_'.$grade) {
            'result_2026_01_12_10' => 'ward_import_2026_01_12_g10_1330',
            'result_2026_01_12_11' => 'ward_import_2026_01_12_g11_1430',
            'result_2026_01_12_12' => 'ward_import_2026_01_12_g12_1530',
            'result_2026_03_09_10' => 'province_session_5_g3_g9_g10_1330',
            'result_2026_03_09_11' => 'province_session_5_g1_g2_g6_g11_1430',
            'result_2026_03_09_12' => 'province_session_5_g4_g7_g12_1530',
            'result_2026_04_03_10' => 'national_session_2_g8_g10_0930',
            'result_2026_04_03_11' => 'national_session_2_g7_g11_0830',
            default => null,
        };

        return $code ? ExamTimeWindow::where('code', $code)->first() : null;
    }

    private function maxScore(string $level, int $grade): int
    {
        return match ($level) {
            'national' => in_array($grade, [10, 11], true) ? 1000 : 2000,
            'school' => in_array($grade, [1, 2], true) ? 1000 : 2000,
            default => in_array($grade, [1, 2, 10, 11, 12], true) ? 1000 : 2000,
        };
    }

    private function maxScoreMap(string $level): array
    {
        $grades = $level === 'national' ? [3, 4, 5, 6, 7, 8, 9, 10, 11] : range(1, 12);

        return collect($grades)->mapWithKeys(fn ($grade) => [(string) $grade => $this->maxScore($level, $grade)])->all();
    }

    private function awardFromText(string $text): array
    {
        $normalized = Str::ascii(Str::lower($text));

        return match (true) {
            str_contains($normalized, 'khuyen') => ['Khuyến khích', 'encouragement'],
            str_contains($normalized, 'nhat') => ['Nhất', 'first'],
            str_contains($normalized, 'nhi') => ['Nhì', 'second'],
            str_contains($normalized, 'ba') => ['Ba', 'third'],
            str_contains($normalized, 'vang') => ['Vàng', 'gold'],
            str_contains($normalized, 'bac') => ['Bạc', 'silver'],
            str_contains($normalized, 'dong') => ['Đồng', 'bronze'],
            default => [$text, null],
        };
    }

    private const WARD_RESULT_FILE = '1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809132482074.XLSX';
    private const PROVINCE_RESULT_FILE = '1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809201595982.XLSX';
    private const NATIONAL_RESULT_FILE = '1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809278674163.XLSX';
    private const SELF_TRAINING_FILE = '1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234812143902125.XLSX';
    private const AWARD_PROVINCE_FILE = '30483_DS_hoc_sinh_vinh_danh_toan_truong_4a6495b5269cf245482890efd0bb0a5f_134234809669347358.XLSX';
    private const AWARD_SCHOOL_FILE = '30483_DS_hoc_sinh_vinh_danh_toan_truong_4a6495b5269cf245482890efd0bb0a5f_134234811052540823.XLSX';

    private const WARD_RESULT_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Khối;Lớp;Trường;Tỉnh thành;Điểm thi;Thời gian thi;Ngày sinh;Phường/Xã;Tỉnh/Thành phố;Ngày thi
1;1303944426;Trương Minh Khiêm;12;12A6;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;660;1783;27/01/2008;;Tỉnh Cà Mau;2026-01-12 15:59:58
2;1303961355;Đoàn Bảo Ngọc ;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;830;1222;23/07/2008;;Tỉnh Cà Mau;2026-01-12 15:50:34
3;1303995731;Châu Ngọc Kim Hân;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;870;1606;21/05/2010;;Tỉnh Cà Mau;2026-01-12 13:56:59
4;1304010080;Trần Mạc Phúc Đình;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;680;1800;04/04/2009;;Tỉnh Cà Mau;2026-01-12 15:00:13
5;1304010701;Cao Kim Khánh;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;630;1779;30/09/2010;;Tỉnh Cà Mau;2026-01-12 13:59:56
6;1304011579;Trần Mỹ Ngân;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;860;1247;03/09/2008;;Tỉnh Cà Mau;2026-01-12 15:51:14
7;1304018501;Lâm Mai Phương;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;610;1800;03/06/2010;;Tỉnh Cà Mau;2026-01-12 14:00:16
8;1304104380;Hà Triệu Phú;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;610;1800;12/09/2008;;Tỉnh Cà Mau;2026-01-12 16:00:19
9;1305772911;Hứa Hồ Ngọc Duyên;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;870;1489;15/08/2010;;Tỉnh Cà Mau;2026-01-12 13:55:05
10;1306670609;Nguyễn Trần Quỳnh Như;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;670;1779;25/08/2010;;Tỉnh Cà Mau;2026-01-12 13:59:54
11;1306966543;Nguyễn Phương Quyên;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;800;1665;11/09/2008;;Tỉnh Cà Mau;2026-01-12 15:58:00
12;1309279002;Nguyễn Thiên;10;10A2;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;680;1780;08/07/2010;;Tỉnh Cà Mau;2026-01-12 14:00:02
13;1309309841;Nguyễn Tuệ Mẫn;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;840;1750;03/03/2010;;Tỉnh Cà Mau;2026-01-12 14:00:07
14;1309351722;Quách Thanh Thế;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;760;1551;11/07/2010;;Tỉnh Cà Mau;2026-01-12 13:56:07
15;1311729048;Lưu Ngọc Xuân Nghi;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;720;1800;05/11/2008;;Tỉnh Cà Mau;2026-01-12 16:00:20
16;1311792230;Phạm Quế Hương ;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;860;1800;28/11/2009;;Tỉnh Cà Mau;2026-01-12 15:00:24
17;1311838010;DƯƠNG THOẠI NHƯ Ý;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;760;1797;15/10/2009;;Tỉnh Cà Mau;2026-01-12 15:00:12
18;1311845684;Tạ Thị Ngọc Lành ;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;580;1568;30/11/2008;;Tỉnh Cà Mau;2026-01-12 15:56:27
19;1311850655;Nguyễn Nhật Tịnh;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;820;1742;16/10/2008;;Tỉnh Cà Mau;2026-01-12 15:59:59
20;1311927181;Lê Trần Nhã Vy;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;760;1781;25/07/2010;;Tỉnh Cà Mau;2026-01-12 14:00:02
21;1312040786;Nguyễn Phi Lai;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;880;1307;03/08/2009;;Tỉnh Cà Mau;2026-01-12 14:52:07
22;1312642276;Trương Tố Huyền;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;670;1800;07/11/2009;;Tỉnh Cà Mau;2026-01-12 15:00:15
23;1312999062;Nguyễn Hông Phương Đan;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;480;1800;24/07/2010;;Tỉnh Cà Mau;2026-01-12 14:00:26
24;1313070370;Nguyễn Phi Lai;12;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;860;1439;03/08/2009;;Tỉnh Cà Mau;2026-01-12 15:54:12
25;1313071061;Nguyễn Phúc Thịnh;12;12A4;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;640;1800;09/06/2007;;Tỉnh Cà Mau;2026-01-12 16:01:04
26;1313072455;Bùi Ngọc Như Ý;12;12A3;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;640;1728;28/11/2008;;Tỉnh Cà Mau;2026-01-12 15:59:01
CSV;

    private const PROVINCE_RESULT_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Khối;Lớp;Trường;Tỉnh thành;Điểm thi;Thời gian thi;Ngày sinh;Phường/Xã;Tỉnh/Thành phố;Ngày thi
1;1303961355;Đoàn Bảo Ngọc ;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;820;1612;23/07/2008;;Tỉnh Cà Mau;2026-03-09 15:57:07
2;1303995731;Châu Ngọc Kim Hân;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;890;1384;21/05/2010;;Tỉnh Cà Mau;2026-03-09 13:53:15
3;1304010080;Trần Mạc Phúc Đình;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;750;1800;04/04/2009;;Tỉnh Cà Mau;2026-03-09 15:00:09
4;1304010701;Cao Kim Khánh;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;630;1800;30/09/2010;;Tỉnh Cà Mau;2026-03-09 14:00:15
5;1304011579;Trần Mỹ Ngân;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;860;1775;03/09/2008;;Tỉnh Cà Mau;2026-03-09 15:59:44
6;1304018501;Lâm Mai Phương;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;480;1800;03/06/2010;;Tỉnh Cà Mau;2026-03-09 14:00:10
7;1305772911;Hứa Hồ Ngọc Duyên;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;950;1089;15/08/2010;;Tỉnh Cà Mau;2026-03-09 13:48:28
8;1306670609;Nguyễn Trần Quỳnh Như;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;740;1800;25/08/2010;;Tỉnh Cà Mau;2026-03-09 14:00:18
9;1306966543;Nguyễn Phương Quyên;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;700;1800;11/09/2008;;Tỉnh Cà Mau;2026-03-09 16:00:37
10;1309279002;Nguyễn Thiên;10;10A2;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;630;1668;08/07/2010;;Tỉnh Cà Mau;2026-03-09 13:58:16
11;1309309841;Nguyễn Tuệ Mẫn;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;780;1765;03/03/2010;;Tỉnh Cà Mau;2026-03-09 13:59:43
12;1309351722;Quách Thanh Thế;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;800;1278;11/07/2010;;Tỉnh Cà Mau;2026-03-09 13:51:30
13;1311729048;Lưu Ngọc Xuân Nghi;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;720;1800;05/11/2008;;Tỉnh Cà Mau;2026-03-09 16:00:12
14;1311792230;Phạm Quế Hương ;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;850;1800;28/11/2009;;Tỉnh Cà Mau;2026-03-09 15:00:14
15;1311838010;DƯƠNG THOẠI NHƯ Ý;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;700;1800;15/10/2009;;Tỉnh Cà Mau;2026-03-09 15:00:14
16;1311850655;Nguyễn Nhật Tịnh;12;12A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;830;1800;16/10/2008;;Tỉnh Cà Mau;2026-03-09 16:00:20
17;1311927181;Lê Trần Nhã Vy;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;870;1387;25/07/2010;;Tỉnh Cà Mau;2026-03-09 13:53:19
18;1312040786;Nguyễn Phi Lai;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;900;1800;03/08/2009;;Tỉnh Cà Mau;2026-03-09 15:00:12
19;1312642276;Trương Tố Huyền;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;810;1800;07/11/2009;;Tỉnh Cà Mau;2026-03-09 15:00:13
20;1312999062;Nguyễn Hông Phương Đan;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;490;1800;24/07/2010;;Tỉnh Cà Mau;2026-03-09 14:00:46
21;1313070370;Nguyễn Phi Lai;12;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;880;1800;03/08/2009;;Tỉnh Cà Mau;2026-03-09 16:00:16
22;1313072455;Bùi Ngọc Như Ý;12;12A3;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;690;1773;28/11/2008;;Tỉnh Cà Mau;2026-03-09 15:59:53
CSV;

    private const NATIONAL_RESULT_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Khối;Lớp;Trường;Tỉnh thành;Điểm thi;Thời gian thi;Ngày sinh;Phường/Xã;Tỉnh/Thành phố;Ngày thi
1;1303995731;Châu Ngọc Kim Hân;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;800;1800;21/05/2010;;Tỉnh Cà Mau;2026-04-03 10:00:16
2;1304010080;Trần Mạc Phúc Đình;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;540;1800;04/04/2009;;Tỉnh Cà Mau;2026-04-03 09:00:15
3;1304010701;Cao Kim Khánh;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;550;1800;30/09/2010;;Tỉnh Cà Mau;2026-04-03 10:00:22
4;1305772911;Hứa Hồ Ngọc Duyên;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;890;1800;15/08/2010;;Tỉnh Cà Mau;2026-04-03 10:00:24
5;1306670609;Nguyễn Trần Quỳnh Như;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;680;1800;25/08/2010;;Tỉnh Cà Mau;2026-04-03 10:00:22
6;1309279002;Nguyễn Thiên;10;10A2;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;590;1725;08/07/2010;;Tỉnh Cà Mau;2026-04-03 10:00:37
7;1309309841;Nguyễn Tuệ Mẫn;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;650;1800;03/03/2010;;Tỉnh Cà Mau;2026-04-03 10:00:31
8;1309351722;Quách Thanh Thế;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;750;1712;11/07/2010;;Tỉnh Cà Mau;2026-04-03 09:58:45
9;1311792230;Phạm Quế Hương ;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;800;1800;28/11/2009;;Tỉnh Cà Mau;2026-04-03 09:00:40
10;1311838010;DƯƠNG THOẠI NHƯ Ý;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;580;1800;15/10/2009;;Tỉnh Cà Mau;2026-04-03 09:00:31
11;1311927181;Lê Trần Nhã Vy;10;10A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;690;1799;25/07/2010;;Tỉnh Cà Mau;2026-04-03 10:00:21
12;1312040786;Nguyễn Phi Lai;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;870;1800;03/08/2009;;Tỉnh Cà Mau;2026-04-03 09:00:26
13;1312642276;Trương Tố Huyền;11;11A1;Trường THPT Võ Văn Kiệt;Tỉnh Cà Mau;580;1775;07/11/2009;;Tỉnh Cà Mau;2026-04-03 09:04:13
CSV;

    private const SELF_TRAINING_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Khối;Lớp;Vòng;Tổng điểm thi;Tổng thời gian tự luyện;Trường;Phường/Xã;Tỉnh/Thành phố
1;1311838010;DƯƠNG THOẠI NHƯ Ý;11;11A1;30;10305;22368;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
2;1305772911;Hứa Hồ Ngọc Duyên;10;10A1;30;10255;13802;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
3;1311792230;Phạm Quế Hương ;11;11A1;30;9996;14387;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
4;1312040786;Nguyễn Phi Lai;11;11A1;30;9955;14326;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
5;1303995731;Châu Ngọc Kim Hân;10;10A1;30;9880;12833;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
6;1306670609;Nguyễn Trần Quỳnh Như;10;10A1;30;9845;19319;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
7;1313070370;Nguyễn Phi Lai;12;11A1;30;9745;13448;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
8;1304010701;Cao Kim Khánh;10;10A1;30;9665;28234;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
9;1303961355;Đoàn Bảo Ngọc ;12;12A1;27;9575;11375;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
10;1309351722;Quách Thanh Thế;10;10A1;30;9540;17408;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
11;1304010080;Trần Mạc Phúc Đình;11;11A1;30;9515;17322;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
12;1309309841;Nguyễn Tuệ Mẫn;10;10A1;30;9450;15359;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
13;1311927181;Lê Trần Nhã Vy;10;10A1;30;9425;14884;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
14;1312642276;Trương Tố Huyền;11;11A1;30;9360;17485;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
15;1311850655;Nguyễn Nhật Tịnh;12;12A1;26;9359;10029;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
16;1309279002;Nguyễn Thiên;10;10A2;30;9275;20690;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
17;1304011579;Trần Mỹ Ngân;12;12A1;25;8850;13311;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
18;1313072455;Bùi Ngọc Như Ý;12;12A3;25;8335;13145;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
19;1306966543;Nguyễn Phương Quyên;12;12A1;25;8280;15173;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
20;1304018501;Lâm Mai Phương;10;10A1;27;8200;22404;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
21;1311729048;Lưu Ngọc Xuân Nghi;12;12A1;25;8160;17843;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
22;1312999062;Nguyễn Hông Phương Đan;10;10A1;25;7765;17793;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
23;1311845684;Tạ Thị Ngọc Lành ;12;12A1;22;6825;13272;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
24;1303944426;Trương Minh Khiêm;12;12A6;20;6670;12307;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
25;1313071061;Nguyễn Phúc Thịnh;12;12A4;20;6595;11271;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
26;1304104380;Hà Triệu Phú;12;12A1;20;6040;10247;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
27;1311732555;Huỳnh Trần Gia Thịnh;12;12A1;9;3185;9526;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
28;1313031530;Phạm Thư Cát;10;10A1;5;1635;3908;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
29;1302232592;Lê Thiện Tân;10;10A1;5;1615;1556;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
30;1313126074;Lê Ngọc Như Ý;12;12A1;3;895;1623;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
31;1313085677;Huỳnh Phú Hào;11;11A2;1;360;373;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
32;1313212435;Trần Đình Khôi;11;11A1;1;360;523;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
33;1303992708;Nguyễn Mỹ Ngọc;10;10A1;1;340;347;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
34;1304885521;Võ Đăng Khoa;11;11A;1;330;2380;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
35;1311848010;Nguyễn Lê Trường An;10;10A1;1;310;481;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
36;1312814293;Phạm Thư Cát;10;10A1;1;300;660;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
37;1311848348;Võ Phan Ngân Đình;12;12A1;1;280;967;Trường THPT Võ Văn Kiệt;Khác;Tỉnh Cà Mau
CSV;

    private const AWARD_PROVINCE_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Ngày sinh (Ngày/tháng/năm);Khối;Điểm;Thời gian;Trường;Kết quả vinh danh
1;1304018501;Lâm Mai Phương;03/06/2010;10;610;30 phút;Trường THPT Võ Văn Kiệt;Đạt giải Khuyến Khích khối 10 toàn Tỉnh
2;1304010701;Cao Kim Khánh;30/09/2010;10;630;29 phút 39 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 10 toàn Tỉnh
3;1312642276;Trương Tố Huyền;07/11/2009;11;670;30 phút;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 11 toàn Tỉnh
4;1306670609;Nguyễn Trần Quỳnh Như;25/08/2010;10;670;29 phút 39 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 10 toàn Tỉnh
5;1304010080;Trần Mạc Phúc Đình;04/04/2009;11;680;30 phút;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 11 toàn Tỉnh
6;1309279002;Nguyễn Thiên;08/07/2010;10;680;29 phút 40 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 10 toàn Tỉnh
7;1311838010;DƯƠNG THOẠI NHƯ Ý;15/10/2009;11;760;29 phút 57 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 11 toàn Tỉnh
8;1311927181;Lê Trần Nhã Vy;25/07/2010;10;760;29 phút 41 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 10 toàn Tỉnh
9;1309351722;Quách Thanh Thế;11/07/2010;10;760;25 phút 51 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 10 toàn Tỉnh
10;1306966543;Nguyễn Phương Quyên;11/09/2008;12;800;27 phút 45 giây;Trường THPT Võ Văn Kiệt;Đạt giải Khuyến Khích khối 12 toàn Tỉnh
11;1311850655;Nguyễn Nhật Tịnh;16/10/2008;12;820;29 phút 2 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 12 toàn Tỉnh
12;1303961355;Đoàn Bảo Ngọc ;23/07/2008;12;830;20 phút 22 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 12 toàn Tỉnh
13;1309309841;Nguyễn Tuệ Mẫn;03/03/2010;10;840;29 phút 10 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 10 toàn Tỉnh
14;1311792230;Phạm Quế Hương ;28/11/2009;11;860;30 phút;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 11 toàn Tỉnh
15;1313070370;Nguyễn Phi Lai;03/08/2009;12;860;23 phút 59 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 12 toàn Tỉnh
16;1304011579;Trần Mỹ Ngân;03/09/2008;12;860;20 phút 47 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 12 toàn Tỉnh
17;1303995731;Châu Ngọc Kim Hân;21/05/2010;10;870;26 phút 46 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 10 toàn Tỉnh
18;1305772911;Hứa Hồ Ngọc Duyên;15/08/2010;10;870;24 phút 49 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 10 toàn Tỉnh
19;1312040786;Nguyễn Phi Lai;03/08/2009;11;880;21 phút 47 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 11 toàn Tỉnh
CSV;

    private const AWARD_SCHOOL_CSV = <<<'CSV'
STT;ID (Mã tài khoản);Họ và Tên (Tên đầy đủ Tiếng Việt có dấu);Ngày sinh (Ngày/tháng/năm);Khối;Điểm;Thời gian;Trường;Kết quả vinh danh
1;1303961355;Đoàn Bảo Ngọc ;23/07/2008;12;830;20 phút 22 giây;Trường THPT Võ Văn Kiệt;Đạt giải Ba khối 12 toàn trường
2;1313070370;Nguyễn Phi Lai;03/08/2009;12;860;23 phút 59 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 12 toàn trường
3;1304011579;Trần Mỹ Ngân;03/09/2008;12;860;20 phút 47 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 12 toàn trường
4;1303995731;Châu Ngọc Kim Hân;21/05/2010;10;870;26 phút 46 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhì khối 10 toàn trường
5;1305772911;Hứa Hồ Ngọc Duyên;15/08/2010;10;870;24 phút 49 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 10 toàn trường
6;1312040786;Nguyễn Phi Lai;03/08/2009;11;880;21 phút 47 giây;Trường THPT Võ Văn Kiệt;Đạt giải Nhất khối 11 toàn trường
CSV;
}
