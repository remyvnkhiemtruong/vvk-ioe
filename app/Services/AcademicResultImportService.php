<?php

namespace App\Services;

use App\Models\AcademicResult;
use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AcademicResultImportService
{
    private const FIELD_ALIASES = [
        'grade' => ['Khối học/Nhóm tuổi', 'Khối học/Nhóm lớp', 'Khối', 'Khoi'],
        'class_name' => ['Tên lớp học', 'Lớp học', 'Lop hoc'],
        'full_name' => ['Họ và tên', 'Ho va ten'],
        'student_code' => ['Mã học sinh', 'Ma hoc sinh'],
        'ministry_identifier' => ['Mã định danh Bộ GD&ĐT', 'Mã định danh bộ GD&ĐT', 'Mã định danh', 'Ma dinh danh'],
        'status' => ['Trạng thái', 'Trang thai'],
        'final_score' => ['Điểm tổng kết', 'Diem tong ket'],
        'school_year' => ['Năm học', 'Nam hoc'],
        'semester' => ['Học kỳ', 'Hoc ky'],
        'stage' => ['Giai đoạn', 'Giai doan'],
        'academic_performance' => ['Học lực', 'Hoc luc'],
        'conduct' => ['Hạnh kiểm', 'Hanh kiem'],
        'title' => ['Danh hiệu', 'Danh hieu'],
        'learning_result' => ['Kết quả học tập', 'Ket qua hoc tap'],
        'training_result' => ['Kết quả rèn luyện', 'Ket qua ren luyen'],
        'external_summary_id' => ['Id Tổng kết hs', 'ID Tổng kết hs', 'Id Tong ket hs'],
    ];

    public function previewPath(string $path, ?string $fileName = null, ?int $headerRow = null): ImportBatch
    {
        return $this->createBatch($this->analyzePath($path, $fileName ?? basename($path), $headerRow));
    }

    public function analyzePath(string $path, ?string $fileName = null, ?int $headerRow = null): array
    {
        $table = SpreadsheetTable::read($path, self::FIELD_ALIASES, 6, $headerRow);
        $headers = $table['headers'];
        $mapping = $table['mapping'];
        $preview = [];
        $errors = [];
        $seen = [];

        foreach ($table['rows'] as $row) {
            $payload = SpreadsheetTable::mapRow($headers, $row['values'], $mapping, array_keys(self::FIELD_ALIASES));

            if (SpreadsheetTable::emptyPayload($payload)) {
                continue;
            }

            $payload['grade'] = SpreadsheetTable::gradeFromText($payload['grade'] ?? null)
                ?: SpreadsheetTable::gradeFromText($payload['class_name'] ?? null);
            $payload['school_year'] = $this->normalizeSchoolYear($payload['school_year'] ?? null);
            $payload['final_score'] = $this->number($payload['final_score'] ?? null);

            $rowNumber = $row['row_number'];
            $validator = $this->validator($payload);
            $rowErrors = $validator->errors()->all();
            $key = implode('|', [
                $payload['student_code'] ?? '',
                $payload['school_year'] ?? '',
                $payload['semester'] ?? '',
                $payload['stage'] ?? '',
            ]);

            if (isset($seen[$key])) {
                $rowErrors[] = 'Dòng kết quả học tập trùng với dòng '.$seen[$key].'.';
            }
            $seen[$key] = $rowNumber;

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors, 'data' => $payload];
            }

            $preview[] = [
                'row' => $rowNumber,
                'valid' => $rowErrors === [],
                'data' => $payload,
                'errors' => $rowErrors,
            ];
        }

        return [
            'type' => 'academic_results',
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
        $rows = collect($batch->preview_rows ?? [])->where('valid', true)->pluck('data')->values();

        return DB::transaction(function () use ($batch, $rows) {
            $count = 0;

            foreach ($rows as $row) {
                $student = Student::where('student_code', Arr::get($row, 'student_code'))->first();
                $schoolYear = Arr::get($row, 'school_year', '2025-2026');
                $academicYear = $this->academicYear($schoolYear);

                AcademicResult::updateOrCreate(
                    [
                        'student_code' => Arr::get($row, 'student_code'),
                        'school_year' => $schoolYear,
                        'semester' => Arr::get($row, 'semester'),
                        'stage' => Arr::get($row, 'stage'),
                    ],
                    [
                        'student_id' => $student?->id,
                        'ministry_identifier' => Arr::get($row, 'ministry_identifier'),
                        'academic_year_id' => $academicYear?->id,
                        'grade' => Arr::get($row, 'grade'),
                        'class_name' => Arr::get($row, 'class_name'),
                        'full_name' => Arr::get($row, 'full_name'),
                        'status' => Arr::get($row, 'status'),
                        'final_score' => Arr::get($row, 'final_score'),
                        'academic_performance' => Arr::get($row, 'academic_performance'),
                        'conduct' => Arr::get($row, 'conduct'),
                        'title' => Arr::get($row, 'title'),
                        'learning_result' => Arr::get($row, 'learning_result'),
                        'training_result' => Arr::get($row, 'training_result'),
                        'external_summary_id' => Arr::get($row, 'external_summary_id'),
                        'import_batch_id' => $batch->id,
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
        return ImportBatch::create([...$analysis, 'created_by' => auth()->id()]);
    }

    private function validator(array $payload): \Illuminate\Validation\Validator
    {
        return Validator::make($payload, [
            'student_code' => ['required', 'string', 'max:100'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:100'],
            'final_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'full_name' => ['required', 'string', 'max:255'],
        ]);
    }

    private function normalizeSchoolYear(?string $value): string
    {
        $value = trim((string) $value);

        if (preg_match('/20\d{2}\s*[-–]\s*20\d{2}/', $value, $matches)) {
            return str_replace([' ', '–'], ['', '-'], $matches[0]);
        }

        if (preg_match('/20\d{2}/', $value, $matches)) {
            $start = (int) $matches[0];

            return $start.'-'.($start + 1);
        }

        return '2025-2026';
    }

    private function number(?string $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private function academicYear(string $code): ?AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['code' => $code],
            ['start_date' => substr($code, 0, 4).'-09-01', 'end_date' => substr($code, -4).'-05-31', 'is_current' => $code === '2025-2026'],
        );
    }
}
