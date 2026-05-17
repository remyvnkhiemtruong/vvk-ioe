<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\StaffProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StaffProfileImportService
{
    private const FIELD_ALIASES = [
        'staff_code' => ['Mã cán bộ', 'Ma can bo'],
        'identity_number' => ['Mã định danh bộ GD&ĐT', 'Mã định danh', 'Ma dinh danh', 'CCCD'],
        'full_name' => ['Họ và tên', 'Ho va ten'],
        'date_of_birth' => ['Ngày sinh', 'Ngay sinh'],
        'gender' => ['Giới tính', 'Gioi tinh'],
        'ethnicity' => ['Dân tộc', 'Dan toc'],
        'employment_status' => ['Trạng thái', 'Trang thai'],
        'staff_type' => ['Loại cán bộ', 'Loai can bo'],
        'position_group' => ['Nhóm chức vụ', 'Nhom chuc vu'],
        'contract_type' => ['Hình thức hợp đồng', 'Hinh thuc hop dong'],
        'qualification' => ['T.Độ chuyên môn nghiệp vụ', 'Trình độ chuyên môn nghiệp vụ', 'Trinh do chuyen mon nghiep vu'],
        'subject' => ['Môn dạy', 'Mon day'],
    ];

    public function previewPath(string $path, ?string $fileName = null, ?int $headerRow = null): ImportBatch
    {
        return $this->createBatch($this->analyzePath($path, $fileName ?? basename($path), $headerRow));
    }

    public function analyzePath(string $path, ?string $fileName = null, ?int $headerRow = null): array
    {
        $table = SpreadsheetTable::read($path, self::FIELD_ALIASES, 5, $headerRow);
        $headers = $table['headers'];
        $mapping = $table['mapping'];
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
            $payload['status'] = SpreadsheetTable::activeStatusFromText($payload['employment_status'] ?? null);
            $payload['suggested_role'] = $this->suggestRole($payload);

            $rowNumber = $row['row_number'];
            $validator = $this->validator($payload);
            $rowErrors = $validator->errors()->all();

            if ($payload['staff_code']) {
                if (isset($seenCodes[$payload['staff_code']])) {
                    $rowErrors[] = 'Mã cán bộ trùng với dòng '.$seenCodes[$payload['staff_code']].'.';
                }
                $seenCodes[$payload['staff_code']] = $rowNumber;
            }

            if ($payload['identity_number']) {
                if (isset($seenIdentities[$payload['identity_number']])) {
                    $rowErrors[] = 'Mã định danh cán bộ trùng với dòng '.$seenIdentities[$payload['identity_number']].'.';
                }
                $seenIdentities[$payload['identity_number']] = $rowNumber;
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
            'type' => 'staff_profiles',
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
                StaffProfile::updateOrCreate(
                    $this->lookupKey($row),
                    [
                        'staff_code' => Arr::get($row, 'staff_code'),
                        'identity_number' => Arr::get($row, 'identity_number'),
                        'ministry_identifier' => Arr::get($row, 'identity_number'),
                        'full_name' => Arr::get($row, 'full_name'),
                        'date_of_birth' => Arr::get($row, 'date_of_birth'),
                        'gender' => Arr::get($row, 'gender'),
                        'ethnicity' => Arr::get($row, 'ethnicity'),
                        'employment_status' => Arr::get($row, 'employment_status'),
                        'staff_type' => Arr::get($row, 'staff_type'),
                        'position_group' => Arr::get($row, 'position_group'),
                        'contract_type' => Arr::get($row, 'contract_type'),
                        'qualification' => Arr::get($row, 'qualification'),
                        'subject' => Arr::get($row, 'subject'),
                        'suggested_role' => Arr::get($row, 'suggested_role'),
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
        $validator = Validator::make($payload, [
            'staff_code' => ['nullable', 'string', 'max:150'],
            'identity_number' => ['nullable', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            if (blank($payload['staff_code'] ?? null) && blank($payload['identity_number'] ?? null) && blank($payload['full_name'] ?? null)) {
                $validator->errors()->add('staff_code', 'Mỗi dòng phải có mã cán bộ hoặc họ tên để đối chiếu.');
            }
        });

        return $validator;
    }

    private function lookupKey(array $row): array
    {
        if (filled(Arr::get($row, 'staff_code'))) {
            return ['staff_code' => Arr::get($row, 'staff_code')];
        }

        if (filled(Arr::get($row, 'identity_number'))) {
            return ['identity_number' => Arr::get($row, 'identity_number')];
        }

        return [
            'full_name' => Arr::get($row, 'full_name'),
            'date_of_birth' => Arr::get($row, 'date_of_birth'),
        ];
    }

    private function suggestRole(array $payload): ?string
    {
        $text = SpreadsheetTable::normalizeHeader(implode(' ', [
            $payload['staff_type'] ?? '',
            $payload['position_group'] ?? '',
            $payload['subject'] ?? '',
        ]));

        if (str_contains($text, 'giao vien') || filled($payload['subject'] ?? null)) {
            return 'teacher';
        }

        if (str_contains($text, 'giam thi')) {
            return 'proctor';
        }

        return null;
    }
}
