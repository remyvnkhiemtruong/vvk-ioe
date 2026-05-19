<?php

namespace App\Services;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessDataResetImportService
{
    public function __construct(private readonly StudentImportService $studentImports) {}

    public function dryRunFromPath(string $path, string $schoolYear = '2025-2026', bool $resetAwardRules = false): array
    {
        $analysis = $this->studentImports->analyzePath($path, basename($path));

        return $this->buildReport($analysis, $this->businessCounts($resetAwardRules), [
            'committed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'school_year' => $schoolYear,
        ], true, $resetAwardRules);
    }

    public function createPreviewBatch(string $path, string $fileName, string $schoolYear = '2025-2026', bool $resetAwardRules = false): ImportBatch
    {
        $analysis = $this->studentImports->analyzePath($path, $fileName);
        $report = $this->buildReport($analysis, $this->businessCounts($resetAwardRules), [
            'committed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'school_year' => $schoolYear,
        ], true, $resetAwardRules);

        return ImportBatch::create([
            ...$analysis,
            'type' => 'reset_students',
            'status' => 'preview',
            'report' => $report,
            'created_by' => auth()->id(),
        ]);
    }

    public function commitBatch(ImportBatch $batch, string $schoolYear = '2025-2026', bool $resetAwardRules = false): array
    {
        if ($batch->invalid_rows > 0) {
            throw ValidationException::withMessages([
                'file' => 'File còn dòng lỗi, vui lòng sửa file và preview lại trước khi Clear & Import.',
            ]);
        }

        $rows = collect($batch->preview_rows ?? [])
            ->where('valid', true)
            ->pluck('data')
            ->values()
            ->all();

        return DB::transaction(function () use ($batch, $rows, $schoolYear, $resetAwardRules): array {
            $cleared = $this->clearBusinessData($resetAwardRules, $batch->id);
            $importReport = $this->studentImports->importRows($rows, $batch, $schoolYear);

            $report = $this->buildReport($batch->toArray(), $cleared, $importReport, false, $resetAwardRules);
            $batch->update([
                'status' => 'committed',
                'report' => $report,
            ]);

            return $report;
        });
    }

    public function resetAndImportPath(string $path, string $schoolYear = '2025-2026', bool $resetAwardRules = false): array
    {
        $analysis = $this->studentImports->analyzePath($path, basename($path));

        if (($analysis['invalid_rows'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'file' => 'File còn dòng lỗi, thao tác reset/import đã bị hủy.',
            ]);
        }

        return DB::transaction(function () use ($analysis, $schoolYear, $resetAwardRules): array {
            $batch = ImportBatch::create([
                ...$analysis,
                'type' => 'reset_students',
                'status' => 'committing',
                'created_by' => auth()->id(),
            ]);

            $cleared = $this->clearBusinessData($resetAwardRules, $batch->id);
            $rows = collect($analysis['preview_rows'] ?? [])->where('valid', true)->pluck('data')->values()->all();
            $importReport = $this->studentImports->importRows($rows, $batch, $schoolYear);
            $report = $this->buildReport($analysis, $cleared, $importReport, false, $resetAwardRules);
            $batch->update(['status' => 'committed', 'report' => $report]);

            return $report;
        });
    }

    public function businessCounts(bool $resetAwardRules = false): array
    {
        $counts = [];

        foreach ($this->clearableTables($resetAwardRules) as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        $counts['student_users'] = Schema::hasTable('users')
            ? DB::table('users')->whereNotNull('student_id')->count()
            : 0;

        return $counts;
    }

    private function clearBusinessData(bool $resetAwardRules, ?int $preserveBatchId = null): array
    {
        $deleted = [];

        $deleted['student_users'] = Schema::hasTable('users')
            ? DB::table('users')->whereNotNull('student_id')->delete()
            : 0;

        foreach ($this->clearableTables($resetAwardRules) as $table) {
            if (! Schema::hasTable($table)) {
                $deleted[$table] = 0;
                continue;
            }

            $query = DB::table($table);

            if ($table === 'import_batches' && $preserveBatchId) {
                $query->where('id', '!=', $preserveBatchId);
            }

            $deleted[$table] = $query->delete();
        }

        return $deleted;
    }

    private function clearableTables(bool $resetAwardRules): array
    {
        $tables = [
            'score_logs',
            'exam_scores',
            'exam_results',
            'exam_attendance',
            'checkins',
            'incidents',
            'seat_assignments',
            'proctor_assignments',
            'exam_codes',
            'live_screens',
            'rankings',
            'award_records',
            'student_scores',
            'exam_students',
            'exam_registrations',
            'exam_minutes',
            'exam_checklists',
            'video_evidence',
            'ioe_potential_students',
            'password_reset_requests',
            'academic_results',
            'academic_year_students',
            'self_training_progress',
        ];

        if ($resetAwardRules) {
            $tables[] = 'award_rule_items';
            $tables[] = 'award_rules';
        }

        $tables[] = 'students';
        $tables[] = 'import_batches';

        return $tables;
    }

    private function buildReport(array $analysis, array $cleared, array $importReport, bool $dryRun, bool $resetAwardRules): array
    {
        return [
            'dry_run' => $dryRun,
            'reset_award_rules' => $resetAwardRules,
            'total_rows' => (int) ($analysis['total_rows'] ?? 0),
            'valid_rows' => (int) ($analysis['valid_rows'] ?? 0),
            'invalid_rows' => (int) ($analysis['invalid_rows'] ?? 0),
            'created' => (int) ($importReport['created'] ?? 0),
            'updated' => (int) ($importReport['updated'] ?? 0),
            'committed_rows' => (int) ($importReport['committed_rows'] ?? 0),
            'school_year' => $importReport['school_year'] ?? null,
            'cleared_total' => array_sum($cleared),
            'cleared' => $cleared,
        ];
    }
}
