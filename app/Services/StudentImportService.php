<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentImportService
{
    private const FIELD_ALIASES = [
        'full_name' => ['Họ và tên', 'Ho va ten', 'Tên học sinh', 'Ten hoc sinh'],
        'grade' => ['Khối học/Nhóm lớp', 'Khối', 'Khoi', 'Grade'],
        'class_name' => ['Lớp học', 'Lớp', 'Lop', 'Class'],
        'student_code' => ['Mã học sinh', 'Ma hoc sinh', 'Mã HS', 'Ma HS', 'Student code'],
        'identity_number' => ['Mã định danh bộ GD&ĐT', 'Mã định danh', 'Ma dinh danh', 'CCCD', 'Số CCCD'],
        'date_of_birth' => ['Ngày sinh', 'Ngay sinh', 'DOB'],
        'gender' => ['Giới tính', 'Gioi tinh', 'Gender'],
        'source_status' => ['Trạng thái', 'Trang thai', 'Status'],
    ];

    public function preview(UploadedFile $file, array $mapping = []): ImportBatch
    {
        return $this->createBatch($this->analyzePath(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $mapping
        ));
    }

    public function previewPath(string $path, ?string $fileName = null, array $mapping = []): ImportBatch
    {
        return $this->createBatch($this->analyzePath($path, $fileName ?? basename($path), $mapping));
    }

    public function analyzePath(string $path, ?string $fileName = null, array $mapping = []): array
    {
        $table = SpreadsheetTable::read($path, self::FIELD_ALIASES, 5);
        $headers = $table['headers'];
        $mapping = $mapping ?: $table['mapping'];
        $preview = [];
        $errors = [];
        $seenCodes = [];
        $seenIdentities = [];

        foreach ($table['rows'] as $row) {
            $payload = SpreadsheetTable::mapRow($headers, $row['values'], $mapping, array_keys(self::FIELD_ALIASES));

            if (SpreadsheetTable::emptyPayload($payload)) {
                continue;
            }

            $payload['date_of_birth'] = SpreadsheetTable::parseDate($payload['date_of_birth'] ?? null);
            $payload['grade'] = $this->grade($payload);
            $payload['status'] = SpreadsheetTable::activeStatusFromText($payload['source_status'] ?? null);
            unset($payload['source_status']);

            $rowNumber = $row['row_number'];
            $validator = $this->validator($payload);
            $rowErrors = $validator->errors()->all();

            if ($payload['student_code']) {
                if (isset($seenCodes[$payload['student_code']])) {
                    $rowErrors[] = 'Mã học sinh trùng với dòng '.$seenCodes[$payload['student_code']].'.';
                }
                $seenCodes[$payload['student_code']] = $rowNumber;
            }

            if ($payload['identity_number']) {
                if (isset($seenIdentities[$payload['identity_number']])) {
                    $rowErrors[] = 'CCCD/mã định danh trùng với dòng '.$seenIdentities[$payload['identity_number']].'.';
                }
                $seenIdentities[$payload['identity_number']] = $rowNumber;
            }

            $rowErrors = array_merge($rowErrors, $this->databaseConflictErrors($payload));

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
            'type' => 'students',
            'file_name' => $fileName ?? basename($path),
            'status' => 'preview',
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
                Student::updateOrCreate(
                    $this->lookupKey($row),
                    [
                        'full_name' => Arr::get($row, 'full_name'),
                        'grade' => (int) Arr::get($row, 'grade'),
                        'class_name' => Arr::get($row, 'class_name'),
                        'student_code' => Arr::get($row, 'student_code'),
                        'identity_number' => Arr::get($row, 'identity_number'),
                        'date_of_birth' => Arr::get($row, 'date_of_birth') ?: null,
                        'gender' => Arr::get($row, 'gender'),
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

    private function grade(array $payload): ?int
    {
        return SpreadsheetTable::gradeFromText($payload['grade'] ?? null)
            ?: SpreadsheetTable::gradeFromText($payload['class_name'] ?? null);
    }

    private function validator(array $payload): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($payload, [
            'full_name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'integer', 'in:10,11,12'],
            'student_code' => ['nullable', 'string', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            if (blank($payload['student_code'] ?? null) && blank($payload['identity_number'] ?? null)) {
                $validator->errors()->add('student_code', 'Mỗi dòng phải có mã học sinh hoặc CCCD/mã định danh.');
            }
        });

        return $validator;
    }

    private function databaseConflictErrors(array $payload): array
    {
        if (blank($payload['student_code'] ?? null) || blank($payload['identity_number'] ?? null)) {
            return [];
        }

        $studentByCode = Student::where('student_code', $payload['student_code'])->first();
        $studentByIdentity = Student::where('identity_number', $payload['identity_number'])->first();

        if ($studentByCode && $studentByIdentity && $studentByCode->isNot($studentByIdentity)) {
            return ['Mã học sinh và CCCD/mã định danh đang thuộc hai học sinh khác nhau trong hệ thống.'];
        }

        return [];
    }

    private function lookupKey(array $row): array
    {
        if (filled(Arr::get($row, 'student_code'))) {
            return ['student_code' => Arr::get($row, 'student_code')];
        }

        return ['identity_number' => Arr::get($row, 'identity_number')];
    }
}
