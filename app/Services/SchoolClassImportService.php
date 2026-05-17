<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\StaffProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SchoolClassImportService
{
    private const FIELD_ALIASES = [
        'class_code' => ['Mã lớp', 'Ma lop'],
        'identity_code' => ['Mã định danh', 'Ma dinh danh'],
        'class_name' => ['Tên lớp học', 'Ten lop hoc', 'Lớp học', 'Lop hoc'],
        'grade' => ['Khối học/Nhóm lớp', 'Khối học', 'Khoi hoc', 'Khối'],
        'homeroom_teacher' => ['Giáo viên chủ nhiệm', 'Giao vien chu nhiem'],
        'study_shift' => ['Buổi học', 'Buoi hoc'],
        'foreign_language_1' => ['Học ngoại ngữ 1', 'Hoc ngoai ngu 1'],
        'foreign_language_2' => ['Học ngoại ngữ 2', 'Hoc ngoai ngu 2'],
        'track' => ['Phân ban', 'Phan ban'],
        'is_specialized' => ['Lớp chuyên', 'Lop chuyen'],
        'has_vocational_students' => ['Học sinh học nghề', 'Hoc sinh hoc nghe'],
        'is_combined' => ['Lớp ghép', 'Lop ghep'],
        'combined_into_class' => ['Ghép vào lớp', 'Ghep vao lop'],
        'is_boarding' => ['Lớp bán trú', 'Lop ban tru'],
        'weekly_sessions' => ['Số buổi học trên tuần', 'So buoi hoc tren tuan'],
    ];

    public function previewPath(string $path, ?string $fileName = null, string $schoolYear = '2025-2026', ?int $headerRow = null): ImportBatch
    {
        return $this->createBatch($this->analyzePath($path, $fileName ?? basename($path), $schoolYear, $headerRow));
    }

    public function analyzePath(string $path, ?string $fileName = null, string $schoolYear = '2025-2026', ?int $headerRow = null): array
    {
        $table = SpreadsheetTable::read($path, self::FIELD_ALIASES, 3, $headerRow);
        $headers = $table['headers'];
        $mapping = $table['mapping'];
        $preview = [];
        $errors = [];
        $seenNames = [];
        $seenCodes = [];

        foreach ($table['rows'] as $row) {
            $payload = SpreadsheetTable::mapRow($headers, $row['values'], $mapping, array_keys(self::FIELD_ALIASES));

            if (SpreadsheetTable::emptyPayload($payload)) {
                continue;
            }

            $payload['grade'] = SpreadsheetTable::gradeFromText($payload['grade'] ?? null)
                ?: SpreadsheetTable::gradeFromText($payload['class_name'] ?? null);
            $payload['is_specialized'] = SpreadsheetTable::booleanFromText($payload['is_specialized'] ?? null);
            $payload['has_vocational_students'] = SpreadsheetTable::booleanFromText($payload['has_vocational_students'] ?? null);
            $payload['is_combined'] = SpreadsheetTable::booleanFromText($payload['is_combined'] ?? null);
            $payload['is_boarding'] = SpreadsheetTable::booleanFromText($payload['is_boarding'] ?? null);
            $payload['weekly_sessions'] = $payload['weekly_sessions'] !== null ? (int) $payload['weekly_sessions'] : null;
            $payload['school_year'] = $schoolYear;
            $payload['status'] = 'active';

            $rowNumber = $row['row_number'];
            $validator = $this->validator($payload);
            $rowErrors = $validator->errors()->all();

            if ($payload['class_name']) {
                $classKey = mb_strtolower($payload['class_name']);
                if (isset($seenNames[$classKey])) {
                    $rowErrors[] = 'Tên lớp trùng với dòng '.$seenNames[$classKey].'.';
                }
                $seenNames[$classKey] = $rowNumber;
            }

            if ($payload['class_code']) {
                if (isset($seenCodes[$payload['class_code']])) {
                    $rowErrors[] = 'Mã lớp trùng với dòng '.$seenCodes[$payload['class_code']].'.';
                }
                $seenCodes[$payload['class_code']] = $rowNumber;
            }

            if ($rowErrors !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => $rowErrors,
                    'data' => $payload,
                ];
            }

            $preview[] = [
                'row' => $rowNumber,
                'valid' => $rowErrors === [],
                'data' => $payload,
                'errors' => $rowErrors,
            ];
        }

        return [
            'type' => 'school_classes',
            'file_name' => $fileName ?? basename($path),
            'status' => 'preview',
            'header_row' => $table['header_row'],
            'total_rows' => count($preview),
            'valid_rows' => count($preview) - count($errors),
            'invalid_rows' => count($errors),
            'mapping' => $mapping,
            'preview_rows' => $preview,
            'errors' => $errors,
        ];
    }

    public function commit(ImportBatch $batch): int
    {
        $rows = collect($batch->preview_rows ?? [])
            ->where('valid', true)
            ->pluck('data')
            ->values();

        return DB::transaction(function () use ($batch, $rows) {
            $count = 0;

            foreach ($rows as $row) {
                SchoolClass::updateOrCreate(
                    $this->lookupKey($row),
                    [
                        'class_code' => Arr::get($row, 'class_code'),
                        'identity_code' => Arr::get($row, 'identity_code'),
                        'class_name' => Arr::get($row, 'class_name'),
                        'grade' => (int) Arr::get($row, 'grade'),
                        'grade_id' => $this->gradeId((int) Arr::get($row, 'grade')),
                        'homeroom_teacher' => Arr::get($row, 'homeroom_teacher'),
                        'homeroom_teacher_id' => $this->homeroomTeacherId(Arr::get($row, 'homeroom_teacher')),
                        'homeroom_teacher_resolution_status' => $this->homeroomResolutionStatus(Arr::get($row, 'homeroom_teacher')),
                        'study_shift' => Arr::get($row, 'study_shift'),
                        'foreign_language_1' => Arr::get($row, 'foreign_language_1'),
                        'foreign_language_2' => Arr::get($row, 'foreign_language_2'),
                        'track' => Arr::get($row, 'track'),
                        'is_specialized' => Arr::get($row, 'is_specialized'),
                        'has_vocational_students' => Arr::get($row, 'has_vocational_students'),
                        'is_combined' => Arr::get($row, 'is_combined'),
                        'combined_into_class' => Arr::get($row, 'combined_into_class'),
                        'is_boarding' => Arr::get($row, 'is_boarding'),
                        'weekly_sessions' => Arr::get($row, 'weekly_sessions'),
                        'school_year' => Arr::get($row, 'school_year', '2025-2026'),
                        'academic_year_id' => $this->academicYearId(Arr::get($row, 'school_year', '2025-2026')),
                        'import_batch_id' => $batch->id,
                        'status' => Arr::get($row, 'status', 'active'),
                    ]
                );
                $count++;
            }

            $batch->update(['status' => 'committed']);

            return $count;
        });
    }

    private function createBatch(array $analysis): ImportBatch
    {
        return ImportBatch::create([
            ...$analysis,
            'created_by' => auth()->id(),
        ]);
    }

    private function validator(array $payload): \Illuminate\Validation\Validator
    {
        return Validator::make($payload, [
            'class_code' => ['nullable', 'string', 'max:150'],
            'class_name' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'integer', 'in:10,11,12'],
            'school_year' => ['required', 'string', 'max:20'],
            'weekly_sessions' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);
    }

    private function lookupKey(array $row): array
    {
        if (filled(Arr::get($row, 'class_code'))) {
            return ['class_code' => Arr::get($row, 'class_code')];
        }

        return [
            'school_year' => Arr::get($row, 'school_year', '2025-2026'),
            'class_name' => Arr::get($row, 'class_name'),
        ];
    }

    private function academicYearId(string $code): ?int
    {
        return AcademicYear::firstOrCreate(
            ['code' => $code],
            ['start_date' => '2025-09-01', 'end_date' => '2026-05-31', 'is_current' => true],
        )->id;
    }

    private function gradeId(int $grade): ?int
    {
        if (! $grade) {
            return null;
        }

        return Grade::firstOrCreate(
            ['grade_number' => $grade],
            ['name' => 'Khối '.$grade, 'status' => 'active'],
        )->id;
    }

    private function homeroomTeacherId(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $matches = StaffProfile::where('full_name', $name)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first()->id : null;
    }

    private function homeroomResolutionStatus(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'missing';
        }

        $count = StaffProfile::where('full_name', $name)->limit(2)->count();

        return match ($count) {
            0 => 'not_found',
            1 => 'resolved',
            default => 'needs_manual_review',
        };
    }
}
