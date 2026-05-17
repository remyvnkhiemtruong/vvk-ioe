<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AwardRecord;
use App\Models\Exam;
use App\Models\IoeResearchDocument;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IoeAttachedDataImportService
{
    public function __construct(
        private readonly SchoolClassImportService $classes,
        private readonly StaffProfileImportService $staff,
        private readonly StudentImportService $students,
        private readonly AcademicResultImportService $academicResults,
        private readonly HistoricalIoeImportService $historical,
        private readonly AcademicYearRolloverService $rollover,
    ) {}

    public function defaultPaths(string $basePath): array
    {
        $path = fn (string $file): string => rtrim($basePath, "\\/").DIRECTORY_SEPARATOR.$file;

        return [
            'classes' => $path('95000712_Danh_sach_lop_hoc_14052026.xlsx'),
            'staff' => $path('95000712_Danh_sach_can_bo_14052026.xlsx'),
            'students' => $path('95000712_Danh_sach_hoc_sinh_14052026.xlsx'),
            'academic_results' => [
                $path('95000712_Danh_sach_kqht_hoc_sinh_14052026.xlsx'),
                $path('95000712_Danh_sach_kqht_hoc_sinh_14052026 (1).xlsx'),
            ],
            'logo' => $path('LogoVVK (1).png'),
            'summary_workbook' => $path('Tong_hop_IOE_2025_2026_import_vinh_danh_anh.xlsx'),
            'documents' => [
                ['path' => $path('HD_IOE2526_ThiQG_word(2).docx'), 'title' => 'HD IOE 2025-2026 - Thi quoc gia', 'level' => 'national'],
                ['path' => $path('HD_IOE2526_ThiTinh_word.docx'), 'title' => 'HD IOE 2025-2026 - Thi tinh/thanh pho', 'level' => 'provincial'],
                ['path' => $path('HD_IOE2526_ThiTruong_final.docx'), 'title' => 'HD IOE 2025-2026 - Thi cap truong', 'level' => 'school'],
                ['path' => $path('HD_IOE2526_ThiXaPhuong.docx'), 'title' => 'HD IOE 2025-2026 - Thi xa/phuong/dac khu', 'level' => 'general'],
                ['path' => $path('HƯỚNG DẪN GIÁO VIÊN QUẢN LÝ HỌC SINH THI IOE.pdf'), 'title' => 'Huong dan giao vien quan ly hoc sinh thi IOE', 'level' => 'general'],
                ['path' => $path('HƯỚNG DẪN GIÁO VIÊN THEO DÕI HỌC SINH THI IOE.pdf'), 'title' => 'Huong dan giao vien theo doi hoc sinh thi IOE', 'level' => 'general'],
                ['path' => $path('HƯỚNG DẪN HỌC SINH ĐĂNG KÝ TÀI KHOẢN THI IOE.pdf'), 'title' => 'Huong dan hoc sinh dang ky tai khoan thi IOE', 'level' => 'general'],
                ['path' => $path('Tong_hop_IOE_2025_2026_import_vinh_danh_anh.xlsx'), 'title' => 'Tong hop IOE 2025-2026 import vinh danh anh', 'level' => 'general'],
                ['path' => $path('30483_DS_hoc_sinh_vinh_danh_toan_truong_4a6495b5269cf245482890efd0bb0a5f_134234809669347358.XLSX'), 'title' => 'IOE 2025-2026 - Vinh danh toan tinh', 'level' => 'general'],
                ['path' => $path('30483_DS_hoc_sinh_vinh_danh_toan_truong_4a6495b5269cf245482890efd0bb0a5f_134234811052540823.XLSX'), 'title' => 'IOE 2025-2026 - Vinh danh toan truong', 'level' => 'general'],
                ['path' => $path('1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809132482074.XLSX'), 'title' => 'IOE 2025-2026 - Ket qua 12/01/2026', 'level' => 'general'],
                ['path' => $path('1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809201595982.XLSX'), 'title' => 'IOE 2025-2026 - Ket qua 09/03/2026', 'level' => 'general'],
                ['path' => $path('1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234809278674163.XLSX'), 'title' => 'IOE 2025-2026 - Ket qua 03/04/2026', 'level' => 'general'],
                ['path' => $path('1313056684_Danh sách học sinh toàn trường_84ad099fc72096e0e38279e42b76238e_134234812143902125.XLSX'), 'title' => 'IOE 2025-2026 - Tu luyen', 'level' => 'general'],
            ],
        ];
    }

    public function import(array $paths, bool $dryRun = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'classes' => $this->importRows('classes', $paths['classes'], $dryRun),
            'staff' => $this->importRows('staff', $paths['staff'], $dryRun),
            'students' => $this->importRows('students', $paths['students'], $dryRun),
            'logo' => $this->importLogo($paths['logo'], $dryRun),
            'academic_results' => [],
        ];
        gc_collect_cycles();

        foreach ($paths['academic_results'] as $path) {
            $summary['academic_results'][] = $this->importRows('academic_results', $path, $dryRun);
            gc_collect_cycles();
        }

        $summary['historical_ioe'] = $this->historical->import($dryRun);
        gc_collect_cycles();
        $summary['summary_awards'] = $this->importSummaryAwards($paths['summary_workbook'], $dryRun);
        gc_collect_cycles();
        $summary['documents'] = $this->importDocuments($paths['documents'], $dryRun);
        $summary['rollover_all_students'] = $dryRun && ! AcademicYear::where('code', '2025-2026')->exists()
            ? ['dry_run' => true, 'skipped' => true, 'reason' => 'source_year_not_persisted_in_dry_run']
            : $this->rollover->rolloverAllStudents('2025-2026', '2026-2027', $dryRun);

        return $summary;
    }

    private function importRows(string $type, string $path, bool $dryRun): array
    {
        $this->ensureReadable($path);

        return match ($type) {
            'classes' => $this->analyzeCommit(
                $this->classes->analyzePath($path, basename($path), '2025-2026'),
                fn () => $this->classes->commit($this->classes->previewPath($path, basename($path), '2025-2026')),
                $dryRun
            ),
            'staff' => $this->analyzeCommit(
                $this->staff->analyzePath($path, basename($path)),
                fn () => $this->staff->commit($this->staff->previewPath($path, basename($path))),
                $dryRun
            ),
            'students' => $this->analyzeCommit(
                $this->students->analyzePath($path, basename($path)),
                fn () => $this->students->commit($this->students->previewPath($path, basename($path))),
                $dryRun
            ),
            'academic_results' => $this->analyzeCommit(
                $this->academicResults->analyzePath($path, basename($path)),
                fn () => $this->academicResults->commit($this->academicResults->previewPath($path, basename($path))),
                $dryRun
            ),
        };
    }

    private function analyzeCommit(array $analysis, callable $commit, bool $dryRun): array
    {
        if ($analysis['invalid_rows'] > 0) {
            throw new InvalidArgumentException(($analysis['file_name'] ?? 'Excel').' has invalid rows.');
        }

        return [
            'file_name' => $analysis['file_name'] ?? null,
            'valid_rows' => $analysis['valid_rows'],
            'invalid_rows' => $analysis['invalid_rows'],
            'committed_rows' => $dryRun ? 0 : $commit(),
        ];
    }

    private function importLogo(string $path, bool $dryRun): array
    {
        $this->ensureReadable($path);
        $info = getimagesize($path);

        if (! $info || ! str_starts_with((string) $info['mime'], 'image/')) {
            throw new InvalidArgumentException('Invalid logo image: '.$path);
        }

        if (! $dryRun) {
            $destination = 'school/logo-vvk.png';
            Storage::disk('public')->put($destination, file_get_contents($path));
            SystemSetting::updateOrCreate(
                ['key' => 'school.logo_path'],
                ['value' => [
                    'disk' => 'public',
                    'path' => $destination,
                    'original_name' => basename($path),
                    'imported_at' => now()->toIso8601String(),
                ]]
            );
        }

        return ['width' => $info[0], 'height' => $info[1], 'mime' => $info['mime'], 'stored' => ! $dryRun];
    }

    private function importSummaryAwards(string $path, bool $dryRun): array
    {
        $this->ensureReadable($path);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Vinh_danh_tu_anh');

        if (! $sheet) {
            throw new InvalidArgumentException('Summary workbook is missing sheet Vinh_danh_tu_anh.');
        }

        $year = AcademicYear::where('code', '2025-2026')->first();
        $school = School::where('ioe_management_id', '1313056684')->first();
        $summary = ['rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'matched_scores' => 0];

        foreach ($sheet->rangeToArray('A2:R'.$sheet->getHighestDataRow(), null, true, true, true) as $row) {
            $ioeId = trim((string) ($row['I'] ?? ''));
            if ($ioeId === '') {
                continue;
            }

            $summary['rows']++;

            if ($dryRun || ! $year) {
                continue;
            }

            $sourceKey = trim((string) ($row['C'] ?? 'screen_award'));
            $scope = trim((string) ($row['G'] ?? 'national')) ?: 'national';
            $rank = trim((string) ($row['H'] ?? ''));
            $className = trim((string) ($row['K'] ?? ''));
            $grade = $this->gradeFromClass($className);
            $exam = Exam::where('code', str_contains($sourceKey, 'national_exam') ? 'ioe_2025_2026_national' : 'ioe_2025_2026_province')->first();
            $student = $this->studentFromSummaryRow($ioeId, trim((string) ($row['J'] ?? '')), $grade, $className, $school?->id);
            $duration = (int) ($row['N'] ?: $this->parseDuration((string) ($row['M'] ?? '')));
            $score = is_numeric($row['L'] ?? null) ? (float) $row['L'] : null;
            $rawAwardText = trim((string) ($row['Q'] ?? '')) ?: 'Xep hang '.$rank.' - '.$scope;
            $studentScore = $exam && $score !== null
                ? StudentScore::where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->where('score', $score)
                    ->where('duration_seconds', $duration)
                    ->first()
                : null;

            if ($studentScore) {
                $summary['matched_scores']++;
            }

            $record = AwardRecord::firstOrNew([
                'source_key' => $sourceKey,
                'student_id' => $student->id,
                'award_scope' => $scope,
                'raw_award_text' => $rawAwardText,
            ]);
            $exists = $record->exists;
            $record->fill([
                'academic_year_id' => $year->id,
                'exam_id' => $exam?->id,
                'student_score_id' => $studentScore?->id,
                'grade_number' => $grade,
                'school_id' => $school?->id,
                'award_name' => 'Xep hang '.($rank ?: '?'),
                'award_code' => 'rank_'.($rank ?: 'unknown'),
                'score' => $score,
                'duration_seconds' => $duration,
                'raw_duration_text' => trim((string) ($row['M'] ?? '')),
                'mapping_status' => $studentScore ? 'matched_by_score_duration_from_summary_workbook' : 'pending_score_match',
                'imported_from_file' => basename($path),
                'status' => 'imported',
            ]);

            if (! $exists) {
                $record->save();
                $summary['created']++;
            } elseif ($record->isDirty()) {
                $record->save();
                $summary['updated']++;
            } else {
                $summary['skipped']++;
            }
        }

        if (! $dryRun && $year) {
            $this->markHighestAwards($year->id);
        }

        return $summary;
    }

    private function importDocuments(array $documents, bool $dryRun): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($documents as $document) {
            $this->ensureReadable($document['path']);
            $extension = strtolower(pathinfo($document['path'], PATHINFO_EXTENSION));
            $destination = 'ioe-documents/2025-2026/'.Str::slug(pathinfo($document['path'], PATHINFO_FILENAME)).'.'.$extension;

            if ($dryRun) {
                $summary['skipped']++;
                continue;
            }

            Storage::disk('public')->put($destination, file_get_contents($document['path']));
            $model = IoeResearchDocument::firstOrNew(['file_path' => $destination]);
            $exists = $model->exists;
            $model->fill([
                'title' => $document['title'],
                'level' => $document['level'],
                'school_year' => '2025-2026',
                'note' => 'Seed tu file dinh kem ngay 2026-05-17. Luu noi bo de tra cuu, khong thay the thao tac chinh thuc tren ioe.vn.',
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

    private function studentFromSummaryRow(string $ioeId, string $name, ?int $grade, string $className, ?int $schoolId): Student
    {
        $student = Student::firstOrNew(['student_code' => $ioeId]);
        $student->fill([
            'full_name' => $name ?: $student->full_name,
            'normalized_name' => $name ? Str::ascii(Str::lower($name)) : $student->normalized_name,
            'grade' => $grade ?: $student->grade,
            'class_name' => $className ?: $student->class_name,
            'school_id' => $schoolId ?: $student->school_id,
            'ioe_account_id' => $ioeId,
            'is_verified' => true,
            'imported_from_ioe' => true,
            'source_academic_year' => '2025-2026',
            'status' => 'active',
        ]);
        $student->save();

        return $student;
    }

    private function markHighestAwards(int $academicYearId): void
    {
        AwardRecord::where('academic_year_id', $academicYearId)->update(['is_highest_award' => false]);
        $priority = ['national' => 1, 'province' => 2, 'ward' => 3, 'school' => 4];

        AwardRecord::where('academic_year_id', $academicYearId)
            ->get()
            ->groupBy('student_id')
            ->each(function ($records) use ($priority): void {
                $records
                    ->sortBy(fn (AwardRecord $record) => $priority[$record->award_scope] ?? 99)
                    ->first()
                    ?->update(['is_highest_award' => true]);
            });
    }

    private function parseDuration(string $value): int
    {
        return app(HistoricalIoeImportService::class)->parseDuration($value) ?? 0;
    }

    private function gradeFromClass(string $className): ?int
    {
        preg_match('/(10|11|12)/', $className, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function ensureReadable(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Cannot read file: '.$path);
        }
    }
}
