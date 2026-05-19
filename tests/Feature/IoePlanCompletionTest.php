<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AcademicYearStudent;
use App\Models\AwardRecord;
use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ImportBatch;
use App\Models\Ranking;
use App\Models\SelfTrainingProgress;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AwardService;
use App\Services\RankingService;
use App\Services\StudentImportService;
use App\Support\StudentNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IoePlanCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }

    public function test_student_registers_without_session_choice_in_admin_assign_mode(): void
    {
        $exam = Exam::factory()->create([
            'registration_mode' => 'admin_assign_session',
            'require_session_choice' => false,
            'target_grades' => [10],
        ]);
        $student = Student::factory()->create(['grade' => 10, 'class_name' => '10A1']);
        $user = $this->studentUser($student);

        $this->actingAs($user)
            ->get(route('student.registrations.create', $exam))
            ->assertOk()
            ->assertSee('Ban tổ chức sẽ phân ca, phòng thi, số máy sau khi duyệt danh sách. Học sinh không cần chọn ca thi ở bước đăng ký.');

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('exam_registrations', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'exam_session_id' => null,
            'ioe_id' => 'ioe-'.$student->student_code,
        ]);
    }

    public function test_student_registers_with_available_session_and_duplicate_is_blocked(): void
    {
        $exam = Exam::factory()->create([
            'registration_mode' => 'student_select_session',
            'require_session_choice' => true,
            'target_grades' => [11],
        ]);
        $student = Student::factory()->create(['grade' => 11, 'class_name' => '11A1']);
        $user = $this->studentUser($student);
        $session = \App\Models\ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 11,
            'max_candidates' => 2,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'exam_session_id' => $session->id,
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'exam_session_id' => $session->id,
                'ioe_id' => 'another-'.$student->student_code,
            ]))
            ->assertSessionHasErrors('identity_number');
    }

    public function test_student_cannot_choose_wrong_or_full_session(): void
    {
        $exam = Exam::factory()->create([
            'registration_mode' => 'student_select_session',
            'require_session_choice' => true,
            'target_grades' => [10, 11],
        ]);
        $student = Student::factory()->create(['grade' => 10, 'class_name' => '10A1']);
        $user = $this->studentUser($student);
        $wrongGrade = \App\Models\ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 11,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'exam_session_id' => $wrongGrade->id,
            ]))
            ->assertSessionHasErrors('exam_session_id');

        $full = \App\Models\ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 10,
            'max_candidates' => 1,
            'status' => 'open',
        ]);
        $other = Student::factory()->create(['grade' => 10, 'class_name' => '10A2']);
        ExamRegistration::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $other->id,
            'exam_session_id' => $full->id,
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'exam_session_id' => $full->id,
            ]))
            ->assertSessionHasErrors('exam_session_id');
    }

    public function test_student_account_can_be_created_by_code_or_ministry_identifier_and_duplicates_are_blocked(): void
    {
        SystemSetting::updateOrCreate(['key' => 'account.options'], [
            'value' => ['student_registration_enabled' => true],
        ]);
        $byCode = Student::factory()->create(['class_name' => '10A1', 'student_code' => 'HS-CODE']);
        $byIdentifier = Student::factory()->create([
            'class_name' => '10A2',
            'student_code' => null,
            'identity_number' => null,
            'ministry_identifier' => 'MID-001',
        ]);

        $this->post(route('register'), [
            'class_name' => '10A1',
            'credential' => 'HS-CODE',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();
        auth()->logout();

        $this->post(route('register'), [
            'class_name' => '10A2',
            'credential' => 'MID-001',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('student.dashboard'));
        auth()->logout();

        $this->post(route('register'), [
            'class_name' => '10A1',
            'credential' => 'HS-CODE',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('credential');

        $this->assertSame(1, $byCode->user()->count());
        $this->assertSame(1, $byIdentifier->user()->count());
    }

    public function test_student_code_lookup_handles_match_miss_multiple_masking_throttle_and_prefill_link(): void
    {
        $student = Student::factory()->create([
            'full_name' => 'Nguyen Van A',
            'normalized_name' => StudentNameNormalizer::normalize('Nguyen Van A'),
            'class_name' => '10A1',
            'student_code' => 'HS001',
            'identity_number' => '123456789012',
            'date_of_birth' => '2010-01-01',
        ]);

        $this->post(route('student_code.lookup.store'), [
            'full_name' => '  Nguyen   Van A ',
            'class_name' => '10A1',
            'date_of_birth' => '2010-01-01',
        ])
            ->assertOk()
            ->assertSee('HS001')
            ->assertSee('credential=HS001', false)
            ->assertSee('class_name=10A1', false)
            ->assertDontSee($student->identity_number);

        $this->post(route('student_code.lookup.store'), [
            'full_name' => 'Khong Ton Tai',
            'class_name' => '10A1',
            'date_of_birth' => '2010-01-01',
        ])
            ->assertOk()
            ->assertDontSee('HS001');

        Student::factory()->create([
            'full_name' => 'Nguyen Van A',
            'normalized_name' => StudentNameNormalizer::normalize('Nguyen Van A'),
            'class_name' => '10A1',
            'student_code' => 'HS002',
            'identity_number' => '123456789999',
            'date_of_birth' => '2010-01-01',
        ]);

        $this->post(route('student_code.lookup.store'), [
            'full_name' => 'Nguyen Van A',
            'class_name' => '10A1',
            'date_of_birth' => '2010-01-01',
        ])
            ->assertOk()
            ->assertDontSee('HS001')
            ->assertDontSee('HS002')
            ->assertDontSee('123456789999');

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
                ->post(route('student_code.lookup.store'), [
                    'full_name' => 'Throttle Test',
                    'class_name' => '10A1',
                    'date_of_birth' => '2010-01-01',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->post(route('student_code.lookup.store'), [
                'full_name' => 'Throttle Test',
                'class_name' => '10A1',
                'date_of_birth' => '2010-01-01',
            ])
            ->assertStatus(429);
    }

    public function test_public_leaderboard_respects_public_flags_filters_sorting_grouping_and_sensitive_data(): void
    {
        $exam = Exam::factory()->create([
            'publish_scores' => false,
            'show_public_stats' => false,
        ]);
        $alpha = $this->score($exam, 10, 980, 120, 'Alpha Student', '10A1');
        $beta = $this->score($exam, 10, 970, 100, 'Beta Student', '10A2');
        $gamma = $this->score($exam, 11, 990, 110, 'Gamma Student', '11A1');
        app(RankingService::class)->run($exam, 'school');
        Ranking::where('student_score_id', $alpha->id)->update(['award_name' => 'Giải Nhất', 'award_code' => 'first']);

        $this->get(route('public.leaderboard.exam', $exam))
            ->assertOk()
            ->assertDontSee('Alpha Student')
            ->assertDontSee('alpha@example.test');

        SystemSetting::updateOrCreate(['key' => 'score.options'], [
            'value' => ['public_scoreboard' => true, 'show_ranking' => true, 'ranking_scope' => 'school'],
        ]);

        $this->get(route('public.leaderboard.exam', $exam))
            ->assertOk()
            ->assertSee('Khối 10')
            ->assertSee('Khối 11')
            ->assertSeeInOrder(['Alpha Student', 'Beta Student'])
            ->assertSee('Gamma Student')
            ->assertDontSee('123456789012')
            ->assertDontSee('alpha@example.test')
            ->assertDontSee('0912345678')
            ->assertDontSee('Secret address');

        $this->get(route('public.leaderboard.exam', [$exam, 'grade' => 10, 'class_name' => '10A1', 'q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Student')
            ->assertDontSee('Beta Student')
            ->assertDontSee('Gamma Student');
    }

    public function test_reset_import_command_reports_rolls_back_invalid_files_and_preserves_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'student_id' => null]);
        $admin->assignRole('admin');
        SystemSetting::updateOrCreate(['key' => 'site.info'], ['value' => ['school_year' => '2025-2026']]);
        $exam = Exam::factory()->create();
        $oldStudent = Student::factory()->create(['student_code' => 'OLD001']);
        $studentUser = $this->studentUser($oldStudent);
        $registration = ExamRegistration::factory()->create(['exam_id' => $exam->id, 'student_id' => $oldStudent->id]);
        $year = AcademicYear::firstOrCreate(['code' => '2025-2026'], [
            'name' => 'Nam hoc 2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-05-31',
            'status' => 'current',
            'is_current' => true,
            'is_active' => true,
        ]);
        $score = StudentScore::create([
            'exam_id' => $exam->id,
            'student_id' => $oldStudent->id,
            'grade_number' => 10,
            'class_name' => '10A1',
            'score' => 900,
            'max_score' => 1000,
            'duration_seconds' => 120,
            'status' => 'locked',
        ]);
        Ranking::create([
            'exam_id' => $exam->id,
            'student_score_id' => $score->id,
            'student_id' => $oldStudent->id,
            'grade_number' => 10,
            'scope' => 'school',
            'rank' => 1,
            'score' => 900,
        ]);
        AwardRecord::create([
            'academic_year_id' => $year->id,
            'exam_id' => $exam->id,
            'student_id' => $oldStudent->id,
            'student_score_id' => $score->id,
            'grade_number' => 10,
            'award_scope' => 'school',
            'award_name' => 'Giải Nhất',
            'award_code' => 'first',
            'raw_award_text' => 'first',
            'source_key' => 'old',
        ]);
        AcademicYearStudent::create(['academic_year_id' => $year->id, 'student_id' => $oldStudent->id, 'current_grade_number' => 10, 'class_name' => '10A1']);
        SelfTrainingProgress::create(['academic_year_id' => $year->id, 'student_id' => $oldStudent->id, 'round_number' => 20, 'source_key' => 'old']);

        $invalid = $this->studentXlsx([
            ['Missing Identifier', '10A1', 10, null, null, '2010-01-01', 'Nam'],
        ]);
        $this->artisan('ioe:reset-import', [
            'file' => $invalid,
            '--school-year' => '2025-2026',
            '--confirm' => true,
        ])->assertExitCode(1);
        $this->assertDatabaseHas('students', ['id' => $oldStudent->id]);
        $this->assertDatabaseHas('rankings', ['student_score_id' => $score->id]);

        $valid = $this->studentXlsx([
            ['New Student', '10A1', 10, 'NEW001', '999999999999', '2010-02-02', 'Nu', 'new@example.test', '0911111111', 'New address', 'ioe-new', 25, 'Co'],
        ]);
        $this->artisan('ioe:reset-import', [
            'file' => $valid,
            '--school-year' => '2025-2026',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertSame(1, Role::where('name', 'admin')->count());
        $this->assertDatabaseHas('system_settings', ['key' => 'site.info']);
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('students', ['student_code' => 'OLD001']);
        $this->assertDatabaseHas('students', ['student_code' => 'NEW001', 'normalized_name' => 'new student']);
        $this->assertSame(0, Ranking::count());
        $this->assertSame(0, AwardRecord::count());
        $this->assertSame(0, StudentScore::count());
        $this->assertSame(0, ExamRegistration::count());
        $this->assertSame(0, AcademicYearStudent::count());
        $this->assertSame(0, SelfTrainingProgress::count());
        $this->assertSame(1, ImportBatch::where('type', 'reset_students')->where('status', 'committed')->count());
        $this->assertDatabaseMissing('exam_registrations', ['id' => $registration->id]);
    }

    public function test_student_import_validates_duplicates_missing_identifiers_and_update_or_create(): void
    {
        Student::factory()->create([
            'student_code' => 'HS001',
            'full_name' => 'Old Name',
            'identity_number' => '111111111111',
            'class_name' => '10A1',
            'grade' => 10,
        ]);

        $duplicate = app(StudentImportService::class)->analyzePath($this->studentXlsx([
            ['Student A', '10A1', 10, 'DUP001', '222222222222', '2010-01-01', 'Nam'],
            ['Student B', '10A2', 10, 'DUP001', '333333333333', '2010-01-02', 'Nam'],
            ['Student C', '10A3', 10, null, null, '2010-01-03', 'Nam'],
        ]), 'students.xlsx');

        $this->assertSame(2, $duplicate['invalid_rows']);

        $report = app(StudentImportService::class)->importRows([
            [
                'full_name' => 'Updated Name',
                'grade' => 10,
                'class_name' => '10A9',
                'student_code' => 'HS001',
                'identity_number' => '111111111111',
                'date_of_birth' => '2010-01-01',
                'gender' => 'Nam',
                'email' => 'updated@example.test',
                'phone' => '0912345678',
                'address' => 'Updated address',
                'ioe_account_id' => 'ioe-updated',
                'current_self_training_round' => 20,
                'is_verified' => true,
                'status' => 'active',
            ],
            [
                'full_name' => 'Created Name',
                'grade' => 11,
                'class_name' => '11A1',
                'student_code' => 'HS002',
                'identity_number' => '444444444444',
                'date_of_birth' => '2009-01-01',
                'gender' => 'Nu',
                'status' => 'active',
            ],
        ], null, '2025-2026');

        $this->assertSame(['committed_rows' => 2, 'created' => 1, 'updated' => 1, 'school_year' => '2025-2026'], $report);
        $this->assertDatabaseHas('students', ['student_code' => 'HS001', 'full_name' => 'Updated Name', 'normalized_name' => 'updated name']);
        $this->assertDatabaseHas('students', ['student_code' => 'HS002', 'full_name' => 'Created Name']);
    }

    public function test_ranking_is_per_exam_scope_and_grade_idempotent_with_competition_ranks(): void
    {
        $exam = Exam::factory()->create();
        $otherExam = Exam::factory()->create();
        $this->score($exam, 10, 1000, 120, 'Grade10 A', '10A1');
        $this->score($exam, 10, 1000, 120, 'Grade10 B', '10A2');
        $this->score($exam, 10, 990, 100, 'Grade10 C', '10A3');
        $grade11 = $this->score($exam, 11, 800, 100, 'Grade11 A', '11A1');
        $this->score($exam, 12, 700, null, 'Grade12 A', '12A1');
        $this->score($otherExam, 10, 1, 1, 'Other Exam', '10Z1');

        $report = app(RankingService::class)->run($exam, 'school');
        $this->assertSame(5, $report['total_ranked']);
        $this->assertSame([1, 1, 3], Ranking::where('exam_id', $exam->id)->where('grade_number', 10)->orderBy('student_id')->pluck('rank')->sort()->values()->all());
        $this->assertSame(1, Ranking::where('exam_id', $exam->id)->where('grade_number', 11)->value('rank'));
        $this->assertSame(1, Ranking::where('exam_id', $exam->id)->where('grade_number', 12)->value('rank'));
        $this->assertSame(0, Ranking::where('exam_id', $otherExam->id)->count());

        app(RankingService::class)->run($exam, 'school');
        $this->assertSame(5, Ranking::where('exam_id', $exam->id)->where('scope', 'school')->count());

        $grade11->update(['needs_rerank' => true, 'status' => 'submitted']);
        $grade10Report = app(RankingService::class)->run($exam, 'school', 10);
        $this->assertSame(3, $grade10Report['total_ranked']);
        $this->assertTrue($grade11->refresh()->needs_rerank);
    }

    public function test_awards_apply_grade_null_rules_per_grade_tie_cutoff_scope_and_highest_priority(): void
    {
        $exam = Exam::factory()->create();
        $g10a = $this->score($exam, 10, 1000, 100, 'G10 A', '10A1');
        $g10b = $this->score($exam, 10, 990, 100, 'G10 B', '10A2');
        $g10c = $this->score($exam, 10, 990, 100, 'G10 C', '10A3');
        $this->score($exam, 10, 970, 100, 'G10 D', '10A4');
        $g11a = $this->score($exam, 11, 500, 100, 'G11 A', '11A1');
        $this->score($exam, 11, 490, 100, 'G11 B', '11A2');

        app(RankingService::class)->run($exam, 'school');
        app(RankingService::class)->run($exam, 'national');

        $schoolRule = $this->awardRule($exam, 'school', null, 50, 4, 'first', 100);
        $nationalRule = $this->awardRule($exam, 'national', 10, null, 1, 'gold', 100);

        $report = app(AwardService::class)->run($exam, null, 'school');
        $this->assertSame(4, $report['total_awarded']);
        $this->assertSame(3, $report['awarded_by_grade'][10]);
        $this->assertSame(1, $report['awarded_by_grade'][11]);
        $this->assertTrue(Ranking::where('student_score_id', $g11a->id)->where('scope', 'school')->whereNotNull('award_code')->exists());
        $this->assertTrue(Ranking::where('student_score_id', $g10c->id)->where('scope', 'school')->whereNotNull('award_code')->exists());

        $nationalReport = app(AwardService::class)->run($exam, 10, 'national');
        $this->assertSame(4, $nationalReport['total_awarded']);
        $this->assertSame(0, Ranking::where('scope', 'national')->where('grade_number', 11)->whereNotNull('award_code')->count());
        $this->assertTrue(Ranking::where('student_score_id', $g10a->id)->where('scope', 'national')->first()->is_highest_award);
        $this->assertFalse(Ranking::where('student_score_id', $g10a->id)->where('scope', 'school')->first()->is_highest_award);

        $schoolRule->items()->update(['award_code' => 'second', 'award_name' => 'Giải Nhì']);
        $this->awardRule($exam, 'school', null, 50, 9, 'gold', 100);
        $rerun = app(AwardService::class)->run($exam, null, 'school');
        $this->assertSame(6, $rerun['total_awarded']);
        $this->assertSame(0, Ranking::where('scope', 'school')->where('award_code', 'first')->count());
        $this->assertSame('second', Ranking::where('student_score_id', $g10a->id)->where('scope', 'school')->value('award_code'));
        $this->assertDatabaseHas('award_rules', ['id' => $nationalRule->id]);
    }

    public function test_award_blocks_when_scores_need_rerank(): void
    {
        $exam = Exam::factory()->create();
        $score = $this->score($exam, 10, 900, 100, 'Needs Rerank', '10A1');
        app(RankingService::class)->run($exam, 'school');
        $this->awardRule($exam, 'school', null, null, 4, 'first', 100);
        $score->update(['needs_rerank' => true]);

        $this->expectException(ValidationException::class);
        app(AwardService::class)->run($exam, null, 'school');
    }

    private function score(Exam $exam, int $grade, ?int $score, ?int $duration, string $name, string $className): StudentScore
    {
        $student = Student::factory()->create([
            'full_name' => $name,
            'grade' => $grade,
            'class_name' => $className,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'phone' => '0912345678',
            'address' => 'Secret address',
            'identity_number' => '1234567890'.str_pad((string) Student::count(), 2, '0', STR_PAD_LEFT),
        ]);

        return StudentScore::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'grade_number' => $grade,
            'class_name' => $className,
            'score' => $score,
            'max_score' => 1000,
            'duration_seconds' => $duration,
            'status' => 'locked',
        ]);
    }

    private function awardRule(Exam $exam, string $scope, ?int $gradeNumber, ?int $topPercent, int $priority, string $code, int $ratio): AwardRule
    {
        $rule = AwardRule::create([
            'exam_id' => $exam->id,
            'name' => $scope.' rule',
            'scope' => $scope,
            'grade_number' => $gradeNumber,
            'min_score_percent' => 0,
            'top_percent' => $topPercent,
            'priority_order' => $priority,
            'is_active' => true,
        ]);

        AwardRuleItem::create([
            'award_rule_id' => $rule->id,
            'award_name' => 'Giải',
            'award_code' => $code,
            'ratio_percent' => $ratio,
            'sort_order' => 1,
        ]);

        return $rule;
    }

    private function studentUser(Student $student): User
    {
        $user = User::factory()->create([
            'role' => 'student',
            'student_id' => $student->id,
            'username' => $student->student_code ?: 'student'.$student->id,
            'email' => 'user'.$student->id.'@example.test',
        ]);
        $user->assignRole('student');

        return $user;
    }

    private function registrationPayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $student->full_name,
            'ioe_id' => 'ioe-'.$student->student_code,
            'date_of_birth' => $student->date_of_birth->format('Y-m-d'),
            'gender' => $student->gender,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address,
            'phone' => '0912345678',
            'email' => 'student'.$student->id.'@example.test',
            'uses_personal_computer' => 0,
            'confirm_information' => 1,
        ], $overrides);
    }

    private function studentXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Ho va ten', 'Lop', 'Khoi', 'Ma hoc sinh', 'CCCD', 'Ngay sinh', 'Gioi tinh', 'Email', 'So dien thoai', 'Dia chi', 'Tai khoan IOE', 'Vong tu luyen', 'Da xac thuc tai khoan IOE'],
            ...$rows,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function permissions(): array
    {
        return [
            'dashboard.view',
            'students.view',
            'students.view_sensitive',
            'students.import',
            'students.export',
            'exams.manage',
            'form.manage',
            'registrations.view',
            'registrations.approve',
            'registrations.update',
            'devices.approve',
            'rooms.manage',
            'sessions.manage',
            'assignments.manage',
            'checkins.manage',
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'scores.unlock',
            'exports.manage',
            'research.manage',
            'users.manage',
            'activity.view',
            'settings.manage',
        ];
    }
}
