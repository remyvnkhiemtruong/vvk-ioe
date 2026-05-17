<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AcademicYearStudent;
use App\Models\AwardRecord;
use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\ExamCode;
use App\Models\ExamLevel;
use App\Models\ExamSession;
use App\Models\ExamStudent;
use App\Models\ExamTimeWindow;
use App\Models\LiveScreen;
use App\Models\Ranking;
use App\Models\SelfTrainingProgress;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\User;
use App\Services\AcademicYearRolloverService;
use App\Services\AwardService;
use App\Services\EligibilityService;
use App\Services\HistoricalIoeImportService;
use App\Services\LiveScreenService;
use App\Services\RankingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IoeInternalV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['dashboard.view', 'exams.manage', 'scores.enter', 'scores.lock', 'students.view', 'registrations.view', 'sessions.manage'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }

    public function test_eligibility_rejects_missing_round_unverified_wrong_grade_and_low_previous_score(): void
    {
        $schoolExam = $this->exam('school');
        $student = Student::factory()->create(['grade' => 10, 'is_verified' => false]);
        $examStudent = ExamStudent::create([
            'exam_id' => $schoolExam->id,
            'student_id' => $student->id,
            'grade_number' => 10,
            'class_name' => '10A1',
            'ioe_account_verified' => false,
            'self_training_round' => 10,
            'status' => 'draft',
        ]);

        $result = app(EligibilityService::class)->check($examStudent);
        $this->assertFalse($result['eligible']);
        $this->assertCount(3, $result['reasons']);
        $this->assertSame('ineligible', $examStudent->refresh()->eligibility_status);

        $nationalExam = $this->exam('national');
        $grade12 = Student::factory()->create(['grade' => 12, 'is_verified' => true]);
        $nationalStudent = ExamStudent::create([
            'exam_id' => $nationalExam->id,
            'student_id' => $grade12->id,
            'grade_number' => 12,
            'class_name' => '12A1',
            'ioe_account_verified' => true,
            'self_training_round' => 30,
        ]);
        $this->assertFalse(app(EligibilityService::class)->check($nationalStudent)['eligible']);

        $provinceExam = $this->exam('province', $nationalExam->academic_year_id);
        $grade10 = Student::factory()->create(['grade' => 10, 'is_verified' => true]);
        StudentScore::create([
            'exam_id' => $provinceExam->id,
            'student_id' => $grade10->id,
            'grade_number' => 10,
            'score' => 490,
            'max_score' => 1000,
            'status' => 'locked',
        ]);
        $needsProvinceScore = ExamStudent::create([
            'exam_id' => $nationalExam->id,
            'student_id' => $grade10->id,
            'grade_number' => 10,
            'class_name' => '10A1',
            'ioe_account_verified' => true,
            'self_training_round' => 30,
        ]);
        $this->assertFalse(app(EligibilityService::class)->check($needsProvinceScore)['eligible']);

        StudentScore::where('student_id', $grade10->id)->update(['score' => 500]);
        $this->assertTrue(app(EligibilityService::class)->check($needsProvinceScore->refresh())['eligible']);
    }

    public function test_exam_student_store_defaults_blank_self_training_round_without_server_error(): void
    {
        $admin = $this->adminUser();
        $exam = $this->exam('school');
        $student = Student::factory()->create([
            'grade' => 10,
            'class_name' => '10A1',
            'current_self_training_round' => 7,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.exam-students.store', $exam), [
                'student_id' => $student->id,
                'grade_number' => 10,
                'ioe_account_id' => '',
                'self_training_round' => '',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $examStudent = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertSame(7, $examStudent->self_training_round);
        $this->assertFalse($examStudent->ioe_account_verified);
    }

    public function test_live_state_never_returns_code_outside_visible_window_and_moves_between_slots(): void
    {
        [$exam, $session, $slot] = $this->liveFixture('2026-05-20 10:00:00', 5);
        ExamCode::create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'exam_time_slot_id' => $slot->id,
            'code' => 'IOE-123',
            'source' => 'manual_from_ioe',
            'is_active' => true,
        ]);
        $screen = LiveScreen::create(['exam_id' => $exam->id, 'is_enabled' => true]);
        $service = app(LiveScreenService::class);

        $beforeReveal = $service->getCurrentLiveState($screen, Carbon::parse('2026-05-20 09:54:00', 'Asia/Ho_Chi_Minh'));
        $this->assertFalse($beforeReveal['show_code']);
        $this->assertArrayNotHasKey('code', $beforeReveal);

        $atReveal = $service->getCurrentLiveState($screen, Carbon::parse('2026-05-20 09:55:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame('code_visible_before_start', $atReveal['status']);
        $this->assertSame('IOE-123', $atReveal['code']);

        $afterStart = $service->getCurrentLiveState($screen, Carbon::parse('2026-05-20 10:01:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame('code_visible_after_start', $afterStart['status']);
        $this->assertSame('IOE-123', $afterStart['code']);

        $afterHide = $service->getCurrentLiveState($screen, Carbon::parse('2026-05-20 10:05:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame('all_finished', $afterHide['status']);
        $this->assertArrayNotHasKey('code', $afterHide);

        $this->getJson(route('live.state', $screen->token))
            ->assertOk()
            ->assertJsonMissing(['code' => 'IOE-123']);

        $emptySlot = ExamTimeWindow::create([
            'exam_session_id' => $session->id,
            'name' => 'Khung rỗng',
            'grade_ids' => [10],
            'starts_at' => '2026-05-20 10:30:00',
            'ends_at' => '2026-05-20 11:00:00',
            'duration_minutes' => 30,
            'max_duration_minutes' => 30,
            'has_students' => false,
            'student_count' => 0,
            'status' => 'ready',
        ]);
        ExamCode::create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'exam_time_slot_id' => $emptySlot->id,
            'code' => 'EMPTY',
            'source' => 'manual_from_ioe',
            'is_active' => true,
        ]);

        $nextSlot = ExamTimeWindow::create([
            'exam_session_id' => $session->id,
            'name' => 'Khối 11',
            'grade_ids' => [11],
            'starts_at' => '2026-05-20 11:30:00',
            'ends_at' => '2026-05-20 12:00:00',
            'duration_minutes' => 30,
            'max_duration_minutes' => 30,
            'has_students' => true,
            'student_count' => 3,
            'status' => 'ready',
        ]);
        $state = $service->getCurrentLiveState($screen, Carbon::parse('2026-05-20 10:20:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame('waiting_next_slot', $state['status']);
        $this->assertSame($nextSlot->id, $state['next_slot']['id']);
    }

    public function test_live_reports_missing_code_and_uses_manual_ioe_codes_only(): void
    {
        [$exam, , $slot] = $this->liveFixture('2026-05-20 10:00:00', 4);
        $screen = LiveScreen::create(['exam_id' => $exam->id, 'is_enabled' => true]);

        $state = app(LiveScreenService::class)
            ->getCurrentLiveState($screen, Carbon::parse('2026-05-20 09:56:00', 'Asia/Ho_Chi_Minh'));

        $this->assertSame('missing_code', $state['status']);
        $this->assertFalse($state['show_code']);
        $this->assertSame(0, ExamCode::where('exam_time_slot_id', $slot->id)->count());

        ExamCode::create([
            'exam_id' => $exam->id,
            'exam_session_id' => $slot->exam_session_id,
            'exam_time_slot_id' => $slot->id,
            'code' => 'MANUAL-IOE',
            'source' => 'manual_from_ioe',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('exam_codes', [
            'code' => 'MANUAL-IOE',
            'source' => 'manual_from_ioe',
        ]);
    }

    public function test_score_ranking_awards_and_rerank_rules(): void
    {
        $exam = $this->exam('ward');
        $admin = $this->adminUser();
        $slot = $this->liveFixture('2026-05-20 10:00:00', 1, $exam)[2];
        $examStudent = $this->examStudent($exam, 10, ['assigned_time_slot_id' => $slot->id]);

        $this->actingAs($admin)
            ->post(route('admin.score-entry.store', $exam), [
                'exam_student_id' => $examStudent->id,
                'score' => 1001,
                'max_score' => 1000,
                'duration_seconds' => 120,
            ])
            ->assertSessionHasErrors('score');

        $scores = collect([
            $this->score($exam, 900, 120),
            $this->score($exam, 900, 120),
            $this->score($exam, 900, 100),
            $this->score($exam, 850, 100),
            $this->score($exam, null, null, 'draft'),
        ]);

        $ranked = app(RankingService::class)->run($exam, 'school');
        $this->assertSame(4, $ranked);
        $this->assertSame([1, 2, 2, 4], Ranking::where('scope', 'school')->orderBy('rank')->pluck('rank')->all());

        $rankedScore = $scores->first()->refresh();
        $this->actingAs($admin)
            ->put(route('admin.score-entry.update', [$exam, $rankedScore]), [
                'score' => 880,
                'max_score' => 1000,
                'duration_seconds' => 130,
            ])
            ->assertRedirect();
        $this->assertTrue($rankedScore->refresh()->needs_rerank);
        $this->assertSame('submitted', $rankedScore->status);

        StudentScore::where('exam_id', $exam->id)->delete();
        Ranking::where('exam_id', $exam->id)->delete();
        for ($i = 0; $i < 10; $i++) {
            $this->score($exam, 950 - $i * 10, 100 + $i, 'locked', 1000);
        }
        app(RankingService::class)->run($exam, 'school');
        $rule = AwardRule::create([
            'exam_id' => $exam->id,
            'name' => 'Trường 50%',
            'scope' => 'school',
            'min_score_percent' => 50,
            'priority_order' => 4,
            'is_active' => true,
        ]);
        foreach ([['first', 10, 1], ['second', 20, 2], ['third', 30, 3], ['encouragement', 40, 4]] as [$code, $ratio, $sort]) {
            AwardRuleItem::create([
                'award_rule_id' => $rule->id,
                'award_name' => $code,
                'award_code' => $code,
                'ratio_percent' => $ratio,
                'sort_order' => $sort,
            ]);
        }

        $this->assertSame(10, app(AwardService::class)->run($exam));
        $this->assertSame([
            'encouragement' => 4,
            'first' => 1,
            'second' => 2,
            'third' => 3,
        ], Ranking::whereNotNull('award_code')->selectRaw('award_code, count(*) as total')->groupBy('award_code')->orderBy('award_code')->pluck('total', 'award_code')->all());
    }

    public function test_award_thresholds_and_highest_award_priority_work(): void
    {
        $exam = $this->exam('province');
        $score800 = $this->score($exam, 800, 100, 'locked', 1000);
        $this->score($exam, 500, 110, 'locked', 1000);

        app(RankingService::class)->run($exam, 'school');
        app(RankingService::class)->run($exam, 'national');

        $schoolRule = $this->awardRule($exam, 'school', 50, 4);
        $nationalRule = $this->awardRule($exam, 'national', 80, 1);

        $this->assertSame(3, app(AwardService::class)->run($exam));

        $this->assertSame(2, Ranking::where('scope', 'school')->whereNotNull('award_code')->count());
        $this->assertSame(1, Ranking::where('scope', 'national')->whereNotNull('award_code')->count());
        $this->assertTrue(Ranking::where('student_score_id', $score800->id)->where('scope', 'national')->first()->is_highest_award);
        $this->assertFalse(Ranking::where('student_score_id', $score800->id)->where('scope', 'school')->first()->is_highest_award);

        $this->assertDatabaseHas('award_rules', ['id' => $schoolRule->id]);
        $this->assertDatabaseHas('award_rules', ['id' => $nationalRule->id]);
    }

    public function test_historical_seed_imports_expected_counts_mappings_and_is_idempotent(): void
    {
        $this->artisan('ioe:seed-2025-2026', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, Student::count());

        $this->artisan('ioe:seed-2025-2026')->assertExitCode(0);

        $this->assertSame(37, Student::where('source_academic_year', '2025-2026')->count());
        $this->assertSame(37, SelfTrainingProgress::where('source_key', 'self_training_2025_2026')->count());
        $this->assertSame(26, StudentScore::where('source_key', 'result_2026_01_12')->count());
        $this->assertSame(22, StudentScore::where('source_key', 'result_2026_03_09')->count());
        $this->assertSame(13, StudentScore::where('source_key', 'result_2026_04_03')->count());
        $this->assertSame(19, AwardRecord::where('source_key', 'award_province_2025_2026')->count());
        $this->assertSame(6, AwardRecord::where('source_key', 'award_school_2025_2026')->count());
        $this->assertSame(37, AcademicYearStudent::count());

        $this->assertSame(6, Exam::where('code', 'ioe_2025_2026_school')->first()->sessions()->count());
        $this->assertSame(24, ExamTimeWindow::whereIn('exam_session_id', Exam::where('code', 'ioe_2025_2026_school')->first()->sessions()->pluck('id'))->count());
        $this->assertSame(4, Exam::where('code', 'ioe_2025_2026_ward')->first()->sessions()->where('source', 'btc_official_schedule')->count());
        $this->assertDatabaseHas('exam_sessions', [
            'code' => 'ward_import_session_2026_01_12',
            'mapping_status' => 'date_mismatch_keep_raw_date',
        ]);
        $this->assertSame(10, ExamTimeWindow::where('code', 'ward_import_2026_01_12_g10_1330')->value('student_count'));
        $this->assertSame(5, ExamTimeWindow::where('code', 'ward_import_2026_01_12_g11_1430')->value('student_count'));
        $this->assertSame(11, ExamTimeWindow::where('code', 'ward_import_2026_01_12_g12_1530')->value('student_count'));
        $this->assertDatabaseHas('student_scores', [
            'source_key' => 'result_2026_01_12',
            'mapping_status' => 'date_mismatch_keep_raw_date',
        ]);
        $this->assertSame('2026-01-12', StudentScore::where('source_key', 'result_2026_01_12')->first()->raw_exam_taken_at->toDateString());

        $this->assertSame(5, Exam::where('code', 'ioe_2025_2026_province')->first()->sessions()->count());
        $this->assertSame(20, ExamTimeWindow::whereIn('exam_session_id', Exam::where('code', 'ioe_2025_2026_province')->first()->sessions()->pluck('id'))->count());
        $this->assertSame(10, StudentScore::where('source_key', 'result_2026_03_09')->where('grade_number', 10)->whereHas('timeSlot', fn ($q) => $q->where('code', 'province_session_5_g3_g9_g10_1330'))->count());
        $this->assertSame(5, StudentScore::where('source_key', 'result_2026_03_09')->where('grade_number', 11)->whereHas('timeSlot', fn ($q) => $q->where('code', 'province_session_5_g1_g2_g6_g11_1430'))->count());
        $this->assertSame(7, StudentScore::where('source_key', 'result_2026_03_09')->where('grade_number', 12)->whereHas('timeSlot', fn ($q) => $q->where('code', 'province_session_5_g4_g7_g12_1530'))->count());

        $this->assertSame(2, Exam::where('code', 'ioe_2025_2026_national')->first()->sessions()->count());
        $this->assertSame(7, ExamTimeWindow::whereIn('exam_session_id', Exam::where('code', 'ioe_2025_2026_national')->first()->sessions()->pluck('id'))->count());
        $this->assertSame(5, StudentScore::where('source_key', 'result_2026_04_03')->where('grade_number', 11)->whereHas('timeSlot', fn ($q) => $q->where('code', 'national_session_2_g7_g11_0830'))->count());
        $this->assertSame(8, StudentScore::where('source_key', 'result_2026_04_03')->where('grade_number', 10)->whereHas('timeSlot', fn ($q) => $q->where('code', 'national_session_2_g8_g10_0930'))->count());

        $this->assertSame(2, Student::where('full_name', 'Nguyễn Phi Lai')->count());
        $this->assertSame(2, Student::where('full_name', 'Phạm Thư Cát')->count());
        $this->assertGreaterThanOrEqual(25, AwardRecord::whereNotNull('student_score_id')->count());
        $this->assertSame(1800, app(HistoricalIoeImportService::class)->parseDuration('30 phút'));
        $this->assertSame(1089, app(HistoricalIoeImportService::class)->parseDuration('18 phút 9 giây'));
        $this->assertSame(1439, app(HistoricalIoeImportService::class)->parseDuration('23 phút 59 giây'));

        $counts = [
            Student::count(),
            StudentScore::count(),
            AwardRecord::count(),
            Exam::count(),
            ExamSession::count(),
            ExamTimeWindow::count(),
            AcademicYearStudent::count(),
        ];
        $this->artisan('ioe:seed-2025-2026')->assertExitCode(0);
        $this->assertSame($counts, [
            Student::count(),
            StudentScore::count(),
            AwardRecord::count(),
            Exam::count(),
            ExamSession::count(),
            ExamTimeWindow::count(),
            AcademicYearStudent::count(),
        ]);

        $this->artisan('db:seed', ['--class' => 'IoeHistorical20252026Seeder'])->assertExitCode(0);
        $this->assertSame($counts, [
            Student::count(),
            StudentScore::count(),
            AwardRecord::count(),
            Exam::count(),
            ExamSession::count(),
            ExamTimeWindow::count(),
            AcademicYearStudent::count(),
        ]);
    }

    public function test_rollover_creates_waiting_2026_2027_records_without_new_exam_data(): void
    {
        $this->artisan('ioe:seed-2025-2026')->assertExitCode(0);
        $this->artisan('ioe:rollover-year', [
            'from' => '2025-2026',
            'to' => '2026-2027',
            '--dry-run' => true,
        ])->assertExitCode(0);
        $this->assertSame(37, AcademicYearStudent::count());

        $summary = app(AcademicYearRolloverService::class)->rollover('2025-2026', '2026-2027');
        $this->assertSame(37, $summary['total']);
        $this->assertSame(15, $summary['grade_10_to_11']);
        $this->assertSame(8, $summary['grade_11_to_12']);
        $this->assertSame(14, $summary['grade_12_graduated']);

        $this->assertDatabaseHas('academic_years', [
            'code' => '2026-2027',
            'is_active' => true,
        ]);
        $this->assertSame(15, AcademicYearStudent::where('previous_grade_number', 10)->where('current_grade_number', 11)->count());
        $this->assertSame(8, AcademicYearStudent::where('previous_grade_number', 11)->where('current_grade_number', 12)->count());
        $this->assertSame(14, AcademicYearStudent::where('previous_grade_number', 12)->where('status', 'graduated')->count());
        $this->assertSame(37, AcademicYearStudent::where('eligibility_status', 'pending_official_rules')->where('registration_status', 'not_registered_yet')->count());
        $this->assertSame(0, Exam::where('school_year', '2026-2027')->count());
        $this->assertSame(0, StudentScore::whereHas('exam', fn ($q) => $q->where('school_year', '2026-2027'))->count());

        $before = AcademicYearStudent::count();
        app(AcademicYearRolloverService::class)->rollover('2025-2026', '2026-2027');
        $this->assertSame($before, AcademicYearStudent::count());
    }

    public function test_rollover_all_students_includes_school_profile_students(): void
    {
        $year = AcademicYear::firstOrCreate(['code' => '2025-2026'], [
            'name' => 'Nam hoc 2025 - 2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-05-31',
            'starts_at' => '2025-09-01',
            'ends_at' => '2026-05-31',
            'status' => 'archived',
            'is_current' => false,
            'is_active' => false,
        ]);
        Student::factory()->create([
            'academic_year_id' => $year->id,
            'grade' => 10,
            'class_name' => '10A1',
            'source_academic_year' => null,
        ]);
        Student::factory()->create([
            'grade' => 11,
            'class_name' => '11A1',
            'source_academic_year' => '2025-2026',
        ]);

        $summary = app(AcademicYearRolloverService::class)->rolloverAllStudents('2025-2026', '2026-2027');

        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, AcademicYearStudent::count());
        $this->assertSame(1, AcademicYearStudent::where('previous_grade_number', 10)->where('current_grade_number', 11)->count());
        $this->assertSame(1, AcademicYearStudent::where('previous_grade_number', 11)->where('current_grade_number', 12)->count());

        $this->artisan('ioe:rollover-year', [
            'from' => '2025-2026',
            'to' => '2026-2027',
            '--all-students' => true,
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    public function test_main_navigation_does_not_require_minutes_video_or_incident_workflow(): void
    {
        $admin = $this->adminUser();
        $this->exam('school');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Thiếu BBT')
            ->assertDontSee('Thiếu video')
            ->assertDontSee('Nộp biên bản sự cố');
    }

    private function exam(string $levelCode, ?int $academicYearId = null): Exam
    {
        $year = $academicYearId
            ? AcademicYear::find($academicYearId)
            : AcademicYear::firstOrCreate(['code' => '2025-2026'], [
                'name' => 'Năm học 2025 - 2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-05-31',
                'starts_at' => '2025-09-01',
                'ends_at' => '2026-05-31',
                'status' => 'current',
                'is_current' => true,
                'is_active' => true,
            ]);
        $level = ExamLevel::where('code', $levelCode)->firstOrFail();

        return Exam::create([
            'code' => 'test_'.$levelCode.'_'.uniqid(),
            'name' => 'Test '.$levelCode,
            'school_year' => $year->code,
            'academic_year_id' => $year->id,
            'exam_level_id' => $level->id,
            'level' => $levelCode,
            'registration_mode' => 'admin_assign_session',
            'target_grades' => $level->allowed_grades,
            'status' => 'draft',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
    }

    private function liveFixture(string $start, int $studentCount, ?Exam $exam = null): array
    {
        $exam ??= $this->exam('school');
        $startAt = Carbon::parse($start, 'Asia/Ho_Chi_Minh');
        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'name' => 'Ca test',
            'session_name' => 'Ca test',
            'exam_date' => $startAt->toDateString(),
            'session_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i'),
            'end_time' => $startAt->copy()->addMinutes(30)->format('H:i'),
            'starts_at' => $startAt,
            'ends_at' => $startAt->copy()->addMinutes(30),
            'status' => 'ready',
        ]);
        $slot = ExamTimeWindow::create([
            'exam_session_id' => $session->id,
            'name' => 'Khối 10',
            'grade_ids' => [10],
            'starts_at' => $startAt,
            'ends_at' => $startAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'max_duration_minutes' => 30,
            'code_reveal_before_minutes' => 5,
            'code_hide_after_start_minutes' => 5,
            'has_students' => $studentCount > 0,
            'student_count' => $studentCount,
            'status' => 'ready',
        ]);

        return [$exam, $session, $slot];
    }

    private function examStudent(Exam $exam, int $grade, array $overrides = []): ExamStudent
    {
        $student = Student::factory()->create([
            'grade' => $grade,
            'class_name' => $grade.'A1',
            'is_verified' => true,
            'current_self_training_round' => 30,
        ]);

        return ExamStudent::create(array_merge([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'grade_number' => $grade,
            'class_name' => $student->class_name,
            'ioe_account_verified' => true,
            'self_training_round' => 30,
            'status' => 'assigned_to_slot',
            'eligibility_status' => 'eligible',
        ], $overrides));
    }

    private function score(Exam $exam, ?int $score, ?int $duration, string $status = 'locked', int $maxScore = 1000): StudentScore
    {
        $examStudent = $this->examStudent($exam, 10);

        return StudentScore::create([
            'exam_id' => $exam->id,
            'exam_student_id' => $examStudent->id,
            'student_id' => $examStudent->student_id,
            'grade_number' => 10,
            'class_name' => '10A1',
            'score' => $score,
            'max_score' => $maxScore,
            'duration_seconds' => $duration,
            'status' => $status,
        ]);
    }

    private function awardRule(Exam $exam, string $scope, int $minPercent, int $priority): AwardRule
    {
        $rule = AwardRule::create([
            'exam_id' => $exam->id,
            'name' => $scope,
            'scope' => $scope,
            'min_score_percent' => $minPercent,
            'priority_order' => $priority,
            'is_active' => true,
        ]);
        AwardRuleItem::create([
            'award_rule_id' => $rule->id,
            'award_name' => 'Đạt giải',
            'award_code' => $scope.'_award',
            'ratio_percent' => 100,
            'sort_order' => 1,
        ]);

        return $rule;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }
}
