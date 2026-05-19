<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AcademicYearDataResetService
{
    public function dryRun(string $yearCode): array
    {
        return $this->buildReport($yearCode, false);
    }

    public function execute(string $yearCode, bool $deleteStudentUsers = false): array
    {
        return DB::transaction(function () use ($yearCode, $deleteStudentUsers): array {
            $before = $this->buildReport($yearCode, false);
            $scope = $this->scope($yearCode);

            if ($deleteStudentUsers) {
                $this->deleteWhereIn('users', 'student_id', $scope['student_ids']);
            } else {
                $this->detachStudentUsers($scope['student_ids']);
            }

            foreach ($this->deletePlan($scope) as $step) {
                [$table, $column, $ids] = $step;
                $this->deleteWhereIn($table, $column, $ids);
            }

            if ($scope['year_id']) {
                $this->deleteWhereIn('academic_year_students', 'academic_year_id', [$scope['year_id']]);
                $this->deleteWhereIn('self_training_progress', 'academic_year_id', [$scope['year_id']]);
                $this->deleteWhereIn('award_records', 'academic_year_id', [$scope['year_id']]);
                $this->deleteWhereIn('academic_results', 'academic_year_id', [$scope['year_id']]);
            }

            $this->deleteWhereIn('academic_results', 'student_id', $scope['student_ids']);
            $this->deleteWhereIn('academic_year_students', 'student_id', $scope['student_ids']);
            $this->deleteWhereIn('self_training_progress', 'student_id', $scope['student_ids']);
            $this->deleteWhereIn('students', 'id', $scope['student_ids']);
            $this->deleteWhereIn('import_batches', 'id', $scope['import_batch_ids']);

            $after = $this->buildReport($yearCode, false);

            return [
                ...$before,
                'dry_run' => false,
                'after' => $after['counts'],
                'deleted' => collect($before['counts'])
                    ->map(fn (int $count, string $table) => max($count - (int) ($after['counts'][$table] ?? 0), 0))
                    ->all(),
            ];
        });
    }

    public function buildReport(string $yearCode, bool $dryRun = true): array
    {
        $scope = $this->scope($yearCode);
        $counts = [];

        foreach ($this->reportTables() as $table) {
            $counts[$table] = $this->countTable($table, $scope);
        }

        $counts['student_users_to_detach'] = $this->countWhereIn('users', 'student_id', $scope['student_ids']);

        return [
            'dry_run' => $dryRun,
            'year_code' => $yearCode,
            'academic_year_id' => $scope['year_id'],
            'exam_ids' => $scope['exam_ids'],
            'student_ids' => $scope['student_ids'],
            'counts' => $counts,
            'total' => array_sum($counts),
        ];
    }

    private function scope(string $yearCode): array
    {
        if (! Schema::hasTable('academic_years')) {
            throw new RuntimeException('Bảng academic_years chưa tồn tại.');
        }

        $year = AcademicYear::where('code', $yearCode)->first();
        $yearId = $year?->id;

        $examIds = Schema::hasTable('exams')
            ? DB::table('exams')
                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId)->orWhere('school_year', $yearCode), fn ($query) => $query->where('school_year', $yearCode))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $sessionIds = $this->ids('exam_sessions', 'exam_id', $examIds);
        $timeWindowIds = $this->ids('exam_time_windows', 'exam_session_id', $sessionIds);
        $registrationIds = $this->ids('exam_registrations', 'exam_id', $examIds);
        $seatAssignmentIds = $this->ids('seat_assignments', 'exam_registration_id', $registrationIds);
        $examScoreIds = $this->ids('exam_scores', 'exam_registration_id', $registrationIds);
        $awardRuleIds = $this->ids('award_rules', 'exam_id', $examIds);
        $studentScoreIds = $this->ids('student_scores', 'exam_id', $examIds);

        $studentIds = [];
        if (Schema::hasTable('students')) {
            $studentQuery = DB::table('students');
            $studentQuery->where(function ($query) use ($yearCode, $yearId, $registrationIds, $studentScoreIds) {
                if ($yearId && Schema::hasColumn('students', 'academic_year_id')) {
                    $query->orWhere('academic_year_id', $yearId);
                }
                if (Schema::hasColumn('students', 'source_academic_year')) {
                    $query->orWhere('source_academic_year', $yearCode);
                }
                if ($registrationIds !== [] && Schema::hasTable('exam_registrations')) {
                    $ids = DB::table('exam_registrations')->whereIn('id', $registrationIds)->pluck('student_id')->all();
                    $query->orWhereIn('id', $ids);
                }
                if ($studentScoreIds !== [] && Schema::hasTable('student_scores')) {
                    $ids = DB::table('student_scores')->whereIn('id', $studentScoreIds)->pluck('student_id')->all();
                    $query->orWhereIn('id', $ids);
                }
            });
            $studentIds = $studentQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $classBatchIds = Schema::hasTable('school_classes')
            ? DB::table('school_classes')
                ->when($yearId && Schema::hasColumn('school_classes', 'academic_year_id'), fn ($query) => $query->where('academic_year_id', $yearId))
                ->when(Schema::hasColumn('school_classes', 'school_year'), fn ($query) => $query->orWhere('school_year', $yearCode))
                ->whereNotNull('import_batch_id')
                ->pluck('import_batch_id')
                ->all()
            : [];

        $studentBatchIds = Schema::hasTable('students')
            ? DB::table('students')->whereIn('id', $studentIds)->whereNotNull('import_batch_id')->pluck('import_batch_id')->all()
            : [];

        return [
            'year_id' => $yearId,
            'exam_ids' => array_values(array_unique($examIds)),
            'session_ids' => array_values(array_unique($sessionIds)),
            'time_window_ids' => array_values(array_unique($timeWindowIds)),
            'registration_ids' => array_values(array_unique($registrationIds)),
            'seat_assignment_ids' => array_values(array_unique($seatAssignmentIds)),
            'exam_score_ids' => array_values(array_unique($examScoreIds)),
            'award_rule_ids' => array_values(array_unique($awardRuleIds)),
            'student_score_ids' => array_values(array_unique($studentScoreIds)),
            'student_ids' => array_values(array_unique($studentIds)),
            'import_batch_ids' => array_values(array_unique(array_filter([...$classBatchIds, ...$studentBatchIds]))),
        ];
    }

    private function deletePlan(array $scope): array
    {
        return [
            ['checkins', 'seat_assignment_id', $scope['seat_assignment_ids']],
            ['score_logs', 'exam_score_id', $scope['exam_score_ids']],
            ['exam_attendance', 'exam_registration_id', $scope['registration_ids']],
            ['exam_scores', 'exam_registration_id', $scope['registration_ids']],
            ['exam_results', 'exam_id', $scope['exam_ids']],
            ['incidents', 'exam_id', $scope['exam_ids']],
            ['incidents', 'exam_registration_id', $scope['registration_ids']],
            ['incidents', 'seat_assignment_id', $scope['seat_assignment_ids']],
            ['seat_assignments', 'id', $scope['seat_assignment_ids']],
            ['exam_checklists', 'exam_id', $scope['exam_ids']],
            ['exam_minutes', 'exam_id', $scope['exam_ids']],
            ['video_evidence', 'exam_id', $scope['exam_ids']],
            ['proctor_assignments', 'exam_id', $scope['exam_ids']],
            ['proctor_assignments', 'exam_session_id', $scope['session_ids']],
            ['exam_codes', 'exam_id', $scope['exam_ids']],
            ['live_screens', 'exam_id', $scope['exam_ids']],
            ['award_records', 'exam_id', $scope['exam_ids']],
            ['rankings', 'exam_id', $scope['exam_ids']],
            ['award_rule_items', 'award_rule_id', $scope['award_rule_ids']],
            ['award_rules', 'id', $scope['award_rule_ids']],
            ['student_scores', 'id', $scope['student_score_ids']],
            ['exam_students', 'exam_id', $scope['exam_ids']],
            ['exam_registrations', 'id', $scope['registration_ids']],
            ['exam_time_windows', 'id', $scope['time_window_ids']],
            ['exam_sessions', 'id', $scope['session_ids']],
            ['exams', 'id', $scope['exam_ids']],
        ];
    }

    private function reportTables(): array
    {
        return [
            'rankings',
            'award_records',
            'award_rule_items',
            'award_rules',
            'student_scores',
            'exam_students',
            'exam_registrations',
            'seat_assignments',
            'checkins',
            'incidents',
            'proctor_assignments',
            'exam_codes',
            'live_screens',
            'self_training_progress',
            'academic_year_students',
            'import_batches',
            'exam_time_windows',
            'exam_sessions',
            'exams',
            'students',
            'exam_scores',
            'score_logs',
            'exam_results',
            'exam_attendance',
            'exam_minutes',
            'exam_checklists',
            'video_evidence',
            'academic_results',
        ];
    }

    private function countTable(string $table, array $scope): int
    {
        return match ($table) {
            'rankings' => $this->countWhereIn($table, 'exam_id', $scope['exam_ids']),
            'award_records' => $this->countScopedAwards($scope),
            'award_rule_items' => $this->countWhereIn($table, 'award_rule_id', $scope['award_rule_ids']),
            'award_rules' => $this->countWhereIn($table, 'id', $scope['award_rule_ids']),
            'student_scores' => $this->countWhereIn($table, 'id', $scope['student_score_ids']),
            'exam_students' => $this->countWhereIn($table, 'exam_id', $scope['exam_ids']),
            'exam_registrations' => $this->countWhereIn($table, 'id', $scope['registration_ids']),
            'seat_assignments' => $this->countWhereIn($table, 'id', $scope['seat_assignment_ids']),
            'checkins' => $this->countWhereIn($table, 'seat_assignment_id', $scope['seat_assignment_ids']),
            'incidents' => $this->countIncidents($scope),
            'proctor_assignments' => $this->countWhereIn($table, 'exam_session_id', $scope['session_ids']),
            'exam_codes', 'live_screens', 'exam_results', 'exam_minutes', 'exam_checklists', 'video_evidence' => $this->countWhereIn($table, 'exam_id', $scope['exam_ids']),
            'self_training_progress' => $this->countByYearOrStudents($table, $scope),
            'academic_year_students' => $this->countByYearOrStudents($table, $scope),
            'academic_results' => $this->countByYearOrStudents($table, $scope),
            'import_batches' => $this->countWhereIn($table, 'id', $scope['import_batch_ids']),
            'exam_time_windows' => $this->countWhereIn($table, 'id', $scope['time_window_ids']),
            'exam_sessions' => $this->countWhereIn($table, 'id', $scope['session_ids']),
            'exams' => $this->countWhereIn($table, 'id', $scope['exam_ids']),
            'students' => $this->countWhereIn($table, 'id', $scope['student_ids']),
            'exam_scores' => $this->countWhereIn($table, 'id', $scope['exam_score_ids']),
            'score_logs' => $this->countWhereIn($table, 'exam_score_id', $scope['exam_score_ids']),
            'exam_attendance' => $this->countWhereIn($table, 'exam_registration_id', $scope['registration_ids']),
            default => 0,
        };
    }

    private function countScopedAwards(array $scope): int
    {
        if (! Schema::hasTable('award_records')) {
            return 0;
        }

        return DB::table('award_records')
            ->where(function ($query) use ($scope) {
                $query->whereIn('exam_id', $scope['exam_ids']);
                if ($scope['year_id']) {
                    $query->orWhere('academic_year_id', $scope['year_id']);
                }
                $query->orWhereIn('student_id', $scope['student_ids']);
            })
            ->count();
    }

    private function countIncidents(array $scope): int
    {
        if (! Schema::hasTable('incidents')) {
            return 0;
        }

        return DB::table('incidents')->where(function ($query) use ($scope) {
            if (Schema::hasColumn('incidents', 'exam_id')) {
                $query->orWhereIn('exam_id', $scope['exam_ids']);
            }
            if (Schema::hasColumn('incidents', 'exam_registration_id')) {
                $query->orWhereIn('exam_registration_id', $scope['registration_ids']);
            }
            if (Schema::hasColumn('incidents', 'seat_assignment_id')) {
                $query->orWhereIn('seat_assignment_id', $scope['seat_assignment_ids']);
            }
        })->count();
    }

    private function countByYearOrStudents(string $table, array $scope): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where(function ($query) use ($table, $scope) {
            if ($scope['year_id'] && Schema::hasColumn($table, 'academic_year_id')) {
                $query->orWhere('academic_year_id', $scope['year_id']);
            }
            if (Schema::hasColumn($table, 'student_id')) {
                $query->orWhereIn('student_id', $scope['student_ids']);
            }
        })->count();
    }

    private function ids(string $table, string $column, array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function countWhereIn(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    private function deleteWhereIn(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function detachStudentUsers(array $studentIds): int
    {
        if ($studentIds === [] || ! Schema::hasTable('users') || ! Schema::hasColumn('users', 'student_id')) {
            return 0;
        }

        return DB::table('users')->whereIn('student_id', $studentIds)->update(['student_id' => null]);
    }
}
