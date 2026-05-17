<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AcademicYearStudent;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class AcademicYearRolloverService
{
    public function rollover(string $fromCode, string $toCode, bool $dryRun = false, bool $wrapTransaction = true, bool $includeAllStudents = false): array
    {
        $runner = function () use ($fromCode, $toCode, $includeAllStudents): array {
            return $this->writeRollover($fromCode, $toCode, $includeAllStudents);
        };

        if (! $wrapTransaction) {
            return $runner();
        }

        DB::beginTransaction();

        try {
            $summary = $runner();

            $dryRun ? DB::rollBack() : DB::commit();

            return array_merge($summary, ['dry_run' => $dryRun]);
        } catch (\Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
        }
    }

    public function rolloverAllStudents(string $fromCode, string $toCode, bool $dryRun = false, bool $wrapTransaction = true): array
    {
        return $this->rollover($fromCode, $toCode, $dryRun, $wrapTransaction, true);
    }

    private function writeRollover(string $fromCode, string $toCode, bool $includeAllStudents): array
    {
        $fromYear = AcademicYear::where('code', $fromCode)->firstOrFail();
        $toYear = AcademicYear::updateOrCreate(
            ['code' => $toCode],
            [
                'name' => 'Năm học '.str_replace('-', ' - ', $toCode),
                'start_date' => '2026-09-01',
                'end_date' => '2027-05-31',
                'starts_at' => '2026-09-01',
                'ends_at' => '2027-05-31',
                'status' => 'current',
                'is_current' => true,
                'is_active' => true,
            ]
        );

        AcademicYear::whereKeyNot($toYear->id)->update(['is_current' => false, 'is_active' => false]);

        $students = Student::query()
            ->where(function ($query) use ($fromCode, $fromYear, $includeAllStudents): void {
                $query->where('source_academic_year', $fromCode)
                    ->orWhereHas('selfTrainingProgress', fn ($progress) => $progress->where('academic_year_id', $fromYear->id));

                if ($includeAllStudents) {
                    $query->orWhere('academic_year_id', $fromYear->id);
                }
            })
            ->orderBy('grade')
            ->orderBy('full_name')
            ->get();

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'grade_10_to_11' => 0,
            'grade_11_to_12' => 0,
            'grade_12_graduated' => 0,
            'total' => $students->count(),
        ];

        foreach ($students as $student) {
            $previousGrade = (int) ($student->grade ?: $student->gradeModel?->grade_number);
            $currentGrade = match ($previousGrade) {
                10 => 11,
                11 => 12,
                default => null,
            };

            $status = $previousGrade === 12 ? 'graduated' : 'awaiting_announcement';
            $counter = match ($previousGrade) {
                10 => 'grade_10_to_11',
                11 => 'grade_11_to_12',
                12 => 'grade_12_graduated',
                default => null,
            };

            if ($counter) {
                $summary[$counter]++;
            }

            $model = AcademicYearStudent::firstOrNew([
                'academic_year_id' => $toYear->id,
                'student_id' => $student->id,
            ]);

            $exists = $model->exists;
            $model->fill([
                'previous_academic_year_id' => $fromYear->id,
                'previous_status' => $student->status,
                'carry_over_reason' => 'carried_over',
                'current_grade_id' => $currentGrade ? $this->gradeId($currentGrade) : null,
                'previous_grade_id' => $previousGrade ? $this->gradeId($previousGrade) : null,
                'current_grade_number' => $currentGrade,
                'previous_grade_number' => $previousGrade ?: null,
                'school_id' => $student->school_id,
                'class_name' => $student->class_name,
                'status' => $status,
                'eligibility_status' => 'pending_official_rules',
                'registration_status' => 'not_registered_yet',
                'score_status' => 'no_score',
                'award_status' => 'no_award',
                'note' => 'Chờ thông tin chính thức IOE năm học '.$toCode.'.',
            ]);

            if (! $exists) {
                $model->save();
                $summary['created']++;
            } elseif ($model->isDirty()) {
                $model->save();
                $summary['updated']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
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
}
