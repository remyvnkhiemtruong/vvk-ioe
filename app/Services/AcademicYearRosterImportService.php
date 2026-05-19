<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AcademicYearStudent;
use App\Models\Grade;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Support\StudentNameNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AcademicYearRosterImportService
{
    private const FIELD_ALIASES = [
        'full_name' => ['Họ và tên', 'Họ và Tên', 'Họ và Tên (Tên đầy đủ Tiếng Việt có dấu)', 'Tên học sinh', 'Ho va ten', 'Ten hoc sinh'],
        'class_name' => ['Lớp', 'Lớp học', 'Tên lớp học', 'Lop', 'Lop hoc', 'Class'],
        'grade' => ['Khối', 'Khối học/Nhóm lớp', 'Khoi', 'Khoi hoc', 'Grade'],
        'student_code' => ['Mã học sinh', 'Mã HS', 'Ma hoc sinh', 'Ma HS', 'Student code'],
        'identity_number' => ['Mã định danh', 'Mã định danh Bộ GD&ĐT', 'Mã định danh bộ GD&ĐT', 'CCCD', 'Số CCCD', 'Ma dinh danh', 'So CCCD'],
        'date_of_birth' => ['Ngày sinh', 'Ngay sinh', 'DOB'],
        'gender' => ['Giới tính', 'Gioi tinh', 'Gender'],
        'ethnicity' => ['Dân tộc', 'Dan toc'],
        'source_status' => ['Trạng thái', 'Trang thai', 'Status'],
        'email' => ['Email', 'Địa chỉ email', 'Dia chi email'],
        'phone' => ['Số điện thoại', 'Điện thoại', 'So dien thoai', 'Phone'],
        'address' => ['Địa chỉ', 'Nơi ở', 'Dia chi', 'Noi o', 'Address'],
        'ioe_account_id' => ['ID tài khoản IOE', 'ID (Mã tài khoản)', 'Tài khoản IOE', 'ID IOE', 'Tai khoan IOE'],
        'school_name' => ['Trường', 'Truong'],
        'province_name' => ['Tỉnh thành', 'Tỉnh/Thành phố', 'Tinh thanh', 'Tinh/Thanh pho'],
        'ward_name' => ['Phường/Xã', 'Phuong/Xa'],
        'health_check' => ['Kiểm tra sức khỏe dinh dưỡng', 'Kiem tra suc khoe dinh duong'],
        'height_1' => ['Chiều cao kì 1', 'Chiều cao kỳ 1', 'Chieu cao ki 1'],
        'height_2' => ['Chiều cao kì 2', 'Chiều cao kỳ 2', 'Chieu cao ki 2'],
        'weight_1' => ['Cân nặng kì 1', 'Cân nặng kỳ 1', 'Can nang ki 1'],
        'weight_2' => ['Cân nặng kì 2', 'Cân nặng kỳ 2', 'Can nang ki 2'],
        'eye_disease' => ['Bệnh về mắt', 'Benh ve mat'],
        'current_self_training_round' => ['Vòng', 'Vòng tự luyện', 'Vong tu luyen'],
    ];

    public function previewPath(string $path, string $yearCode = '2026-2027'): array
    {
        $files = $this->files($path);
        $report = $this->emptyReport($yearCode, true);
        $report['total_files'] = count($files);

        foreach ($files as $file) {
            $fileReport = $this->analyzeFile($file, $yearCode);
            $report = $this->mergeReport($report, $fileReport);
        }

        return $report;
    }

    public function createPreviewBatch(string $path, string $yearCode = '2026-2027', ?int $userId = null): ImportBatch
    {
        $report = $this->previewPath($path, $yearCode);

        return ImportBatch::create([
            'type' => 'academic_year_roster',
            'file_name' => is_dir($path) ? $path : basename($path),
            'status' => 'preview',
            'total_rows' => $report['total_rows'],
            'valid_rows' => $report['valid_rows'],
            'invalid_rows' => $report['invalid_rows'],
            'preview_rows' => array_slice($report['rows'], 0, 200),
            'errors' => $report['errors'],
            'report' => $report,
            'created_by' => $userId,
        ]);
    }

    public function importPath(string $path, string $yearCode = '2026-2027', bool $dryRun = true, ?int $userId = null): array
    {
        $analysis = $this->previewPath($path, $yearCode);

        if ($dryRun) {
            return $analysis;
        }

        if ($analysis['invalid_rows'] > 0) {
            throw new InvalidArgumentException('Import bị hủy vì file còn dòng lỗi. Vui lòng xem report trước khi chạy --confirm.');
        }

        return DB::transaction(function () use ($analysis, $path, $yearCode, $userId): array {
            $year = $this->ensureAcademicYear($yearCode);
            $batch = ImportBatch::create([
                'type' => 'academic_year_roster',
                'file_name' => is_dir($path) ? $path : basename($path),
                'status' => 'committing',
                'total_rows' => $analysis['total_rows'],
                'valid_rows' => $analysis['valid_rows'],
                'invalid_rows' => $analysis['invalid_rows'],
                'preview_rows' => array_slice($analysis['rows'], 0, 200),
                'errors' => [],
                'created_by' => $userId,
            ]);

            $report = $analysis;
            $report['dry_run'] = false;
            $report['created_students'] = 0;
            $report['updated_students'] = 0;
            $report['created_classes'] = 0;
            $report['created_grades'] = 0;
            $report['duplicates_merged'] = 0;

            foreach ($analysis['rows'] as $row) {
                if (! ($row['valid'] ?? false)) {
                    continue;
                }

                $result = $this->upsertStudent($row['data'], $year, $batch);
                $report['created_students'] += $result['student_created'] ? 1 : 0;
                $report['updated_students'] += $result['student_created'] ? 0 : 1;
                $report['created_classes'] += $result['class_created'] ? 1 : 0;
                $report['created_grades'] += $result['grade_created'] ? 1 : 0;
                $report['duplicates_merged'] += $result['student_created'] ? 0 : 1;
            }

            $this->updateSettings($yearCode);

            $batch->update([
                'status' => 'committed',
                'report' => $report,
            ]);

            return $report;
        });
    }

    public function ensureAcademicYear(string $yearCode): AcademicYear
    {
        $startYear = substr($yearCode, 0, 4);
        $endYear = substr($yearCode, -4);

        return DB::transaction(function () use ($yearCode, $startYear, $endYear): AcademicYear {
            AcademicYear::query()->update([
                'is_current' => false,
                'is_active' => false,
            ]);

            return AcademicYear::updateOrCreate(
                ['code' => $yearCode],
                [
                    'name' => 'Năm học '.$startYear.' - '.$endYear,
                    'start_date' => $startYear.'-09-01',
                    'end_date' => $endYear.'-05-31',
                    'starts_at' => $startYear.'-09-01',
                    'ends_at' => $endYear.'-05-31',
                    'status' => 'current',
                    'is_current' => true,
                    'is_active' => true,
                ]
            );
        });
    }

    private function analyzeFile(string $file, string $yearCode): array
    {
        $report = $this->emptyReport($yearCode, true);
        $fileSummary = [
            'file_name' => basename($file),
            'path' => $file,
            'sheets' => [],
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ];

        $spreadsheet = IOFactory::load($file);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $headerIndex = $this->detectHeaderIndex($rows);

            if ($headerIndex === null) {
                continue;
            }

            $headers = array_map(fn ($value) => SpreadsheetTable::normalizeHeader((string) $value), $rows[$headerIndex] ?? []);
            $mapping = $this->guessMapping($headers);
            $sheetRows = 0;
            $sheetValid = 0;
            $sheetInvalid = 0;

            foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $rawRow) {
                $payload = SpreadsheetTable::mapRow($headers, $rawRow, $mapping, array_keys(self::FIELD_ALIASES));

                if (SpreadsheetTable::emptyPayload($payload) || $this->looksLikeRepeatedHeader($payload)) {
                    continue;
                }

                $sheetRows++;
                $row = $this->normalizePayload($payload, basename($file), $sheet->getTitle(), $index + 1);
                $errors = $this->validatePayload($row);
                $valid = $errors === [];

                $record = [
                    'file_name' => basename($file),
                    'sheet_name' => $sheet->getTitle(),
                    'row_number' => $index + 1,
                    'valid' => $valid,
                    'data' => $row,
                    'errors' => $errors,
                ];

                $report['rows'][] = $record;
                $valid ? $sheetValid++ : $sheetInvalid++;

                foreach ($errors as $field => $messages) {
                    foreach ((array) $messages as $message) {
                        $report['errors'][] = [
                            'file_name' => basename($file),
                            'sheet_name' => $sheet->getTitle(),
                            'row_number' => $index + 1,
                            'field' => $field,
                            'message' => $message,
                            'raw_data' => $payload,
                            'normalized_data' => $row,
                        ];
                    }
                }
            }

            $fileSummary['sheets'][] = [
                'sheet_name' => $sheet->getTitle(),
                'header_row' => $headerIndex + 1,
                'mapped_fields' => array_keys($mapping),
                'total_rows' => $sheetRows,
                'valid_rows' => $sheetValid,
                'invalid_rows' => $sheetInvalid,
            ];
            $fileSummary['total_rows'] += $sheetRows;
            $fileSummary['valid_rows'] += $sheetValid;
            $fileSummary['invalid_rows'] += $sheetInvalid;
        }

        if ($fileSummary['sheets'] === []) {
            $report['errors'][] = [
                'file_name' => basename($file),
                'sheet_name' => null,
                'row_number' => null,
                'field' => 'header',
                'message' => 'Không nhận diện được sheet/header danh sách học sinh.',
                'raw_data' => [],
                'normalized_data' => [],
            ];
            $fileSummary['invalid_rows']++;
        }

        $report['files'][] = $fileSummary;
        $report['total_sheets'] += count($fileSummary['sheets']);
        $report['total_rows'] += $fileSummary['total_rows'];
        $report['valid_rows'] += $fileSummary['valid_rows'];
        $report['invalid_rows'] += $fileSummary['invalid_rows'];

        return $report;
    }

    private function upsertStudent(array $row, AcademicYear $year, ImportBatch $batch): array
    {
        $grade = $this->grade($row);
        [$gradeModel, $gradeCreated] = $this->gradeModel($grade);
        [$schoolClass, $classCreated] = $this->schoolClass($row['class_name'], $grade, $gradeModel?->id, $year, $batch);

        $student = $this->findStudent($row, $year) ?: new Student;
        $wasNew = ! $student->exists;
        $student->fill($this->studentAttributes($row, $year, $grade, $gradeModel?->id, $schoolClass?->id, $batch, $student));
        $student->save();

        AcademicYearStudent::updateOrCreate(
            ['academic_year_id' => $year->id, 'student_id' => $student->id],
            [
                'current_grade_id' => $gradeModel?->id,
                'current_grade_number' => $grade,
                'school_id' => $student->school_id,
                'class_name' => $row['class_name'],
                'status' => 'active',
                'eligibility_status' => 'pending_official_rules',
                'registration_status' => 'not_registered_yet',
                'score_status' => 'no_score',
                'award_status' => 'no_award',
                'note' => 'Imported roster '.$year->code,
            ]
        );

        return [
            'student_created' => $wasNew,
            'class_created' => $classCreated,
            'grade_created' => $gradeCreated,
        ];
    }

    private function studentAttributes(array $row, AcademicYear $year, int $grade, ?int $gradeId, ?int $schoolClassId, ImportBatch $batch, Student $student): array
    {
        $attrs = [
            'full_name' => $row['full_name'],
            'normalized_name' => StudentNameNormalizer::normalize($row['full_name']),
            'grade' => $grade,
            'grade_id' => $gradeId,
            'class_name' => $row['class_name'],
            'school_class_id' => $schoolClassId,
            'academic_year_id' => $year->id,
            'source_academic_year' => $year->code,
            'student_code' => $row['student_code'] ?? null,
            'ioe_account_id' => $row['ioe_account_id'] ?? null,
            'identity_number' => $row['identity_number'] ?? null,
            'ministry_identifier' => $row['identity_number'] ?? $row['ministry_identifier'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? null,
            'ethnicity' => $row['ethnicity'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'current_self_training_round' => $row['current_self_training_round'] ?? $student->current_self_training_round ?? 0,
            'health_metadata' => $this->mergedMetadata($student, $row),
            'import_batch_id' => $batch->id,
            'status' => 'active',
        ];

        return collect($attrs)
            ->reject(fn ($value, string $key) => $value === null && $student->exists && ! in_array($key, ['health_metadata'], true))
            ->all();
    }

    private function findStudent(array $row, AcademicYear $year): ?Student
    {
        foreach (['ioe_account_id', 'student_code', 'identity_number'] as $field) {
            if (filled($row[$field] ?? null)) {
                $student = Student::where($field, $row[$field])->first();
                if ($student) {
                    return $student;
                }
            }
        }

        if (filled($row['date_of_birth'] ?? null)) {
            return Student::where('academic_year_id', $year->id)
                ->where('normalized_name', StudentNameNormalizer::normalize($row['full_name']))
                ->whereDate('date_of_birth', $row['date_of_birth'])
                ->where('class_name', $row['class_name'])
                ->first();
        }

        return null;
    }

    private function gradeModel(int $grade): array
    {
        $model = Grade::firstOrCreate(
            ['grade_number' => $grade],
            ['name' => 'Khối '.$grade, 'status' => 'active']
        );

        return [$model, $model->wasRecentlyCreated];
    }

    private function schoolClass(string $className, int $grade, ?int $gradeId, AcademicYear $year, ImportBatch $batch): array
    {
        $class = SchoolClass::firstOrNew([
            'school_year' => $year->code,
            'class_name' => $className,
        ]);
        $wasNew = ! $class->exists;
        $class->fill([
            'grade' => $grade,
            'grade_id' => $gradeId,
            'academic_year_id' => $year->id,
            'import_batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $class->save();

        return [$class, $wasNew];
    }

    private function normalizePayload(array $payload, string $fileName, string $sheetName, int $rowNumber): array
    {
        $payload = collect($payload)
            ->map(fn ($value) => $this->clean($value))
            ->all();

        $payload['class_name'] = $this->cleanClassName($payload['class_name'] ?? null);
        $payload['date_of_birth'] = SpreadsheetTable::parseDate($payload['date_of_birth'] ?? null);
        $payload['grade'] = $this->grade($payload);
        $payload['status'] = SpreadsheetTable::activeStatusFromText($payload['source_status'] ?? null);
        $payload['current_self_training_round'] = $this->integerValue($payload['current_self_training_round'] ?? null);
        $payload['health_metadata'] = $this->metadata($payload, $fileName, $sheetName, $rowNumber);
        unset($payload['source_status']);

        return $payload;
    }

    private function validatePayload(array $row): array
    {
        $validator = Validator::make($row, [
            'full_name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'integer', 'in:10,11,12'],
            'student_code' => ['nullable', 'string', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:30'],
            'ioe_account_id' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $validator->after(function ($validator) use ($row): void {
            $hasIdentifier = filled($row['ioe_account_id'] ?? null)
                || filled($row['student_code'] ?? null)
                || filled($row['identity_number'] ?? null);

            if (! $hasIdentifier && blank($row['date_of_birth'] ?? null)) {
                $validator->errors()->add('identity', 'Dòng thiếu định danh và ngày sinh nên không đủ an toàn để import.');
            }
        });

        return $validator->errors()->toArray();
    }

    private function detectHeaderIndex(array $rows): ?int
    {
        $bestIndex = null;
        $bestScore = 0;

        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($value) => SpreadsheetTable::normalizeHeader((string) $value), $row);
            $mapping = $this->guessMapping($headers);
            $score = count(array_intersect(['full_name', 'class_name'], array_keys($mapping))) * 4 + count($mapping);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestScore >= 9 ? $bestIndex : null;
    }

    private function guessMapping(array $headers): array
    {
        $mapping = [];

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = SpreadsheetTable::normalizeHeader($alias);

                foreach ($headers as $header) {
                    if ($header === '') {
                        continue;
                    }

                    if ($header === $normalizedAlias || str_contains($header, $normalizedAlias)) {
                        $mapping[$field] = $header;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    private function files(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            throw new InvalidArgumentException('Không tìm thấy file/thư mục import: '.$path);
        }

        $files = [];
        foreach (['xlsx', 'xls', 'csv'] as $extension) {
            $files = [...$files, ...glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.'.$extension) ?: []];
            $files = [...$files, ...glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.'.strtoupper($extension)) ?: []];
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        if ($files === []) {
            throw new InvalidArgumentException('Thư mục import không có file xlsx, xls hoặc csv.');
        }

        return array_values(array_unique($files));
    }

    private function grade(array $row): int
    {
        return SpreadsheetTable::gradeFromText((string) ($row['grade'] ?? ''))
            ?: SpreadsheetTable::gradeFromText((string) ($row['class_name'] ?? ''))
            ?: 0;
    }

    private function metadata(array $payload, string $fileName, string $sheetName, int $rowNumber): array
    {
        return collect($payload)
            ->only(['health_check', 'height_1', 'height_2', 'weight_1', 'weight_2', 'eye_disease', 'school_name', 'province_name', 'ward_name'])
            ->filter(fn ($value) => filled($value))
            ->merge([
                'imported_from_file' => $fileName,
                'imported_from_sheet' => $sheetName,
                'imported_from_row' => $rowNumber,
            ])
            ->all();
    }

    private function mergedMetadata(Student $student, array $row): array
    {
        return array_replace($student->health_metadata ?? [], $row['health_metadata'] ?? []);
    }

    private function integerValue(mixed $value): int
    {
        $value = $this->clean($value);

        if ($value === null) {
            return 0;
        }

        preg_match('/\d+/', $value, $matches);

        return isset($matches[0]) ? (int) $matches[0] : 0;
    }

    private function clean(mixed $value): ?string
    {
        $cleaned = SpreadsheetTable::cleanValue($value);

        if ($cleaned === null) {
            return null;
        }

        $normalized = SpreadsheetTable::normalizeHeader($cleaned);

        return in_array($normalized, ['null', 'n a', 'na', 'none', 'khong co'], true) ? null : trim($cleaned);
    }

    private function cleanClassName(?string $value): ?string
    {
        $value = $this->clean($value);

        return $value ? strtoupper(preg_replace('/\s+/', '', $value) ?: $value) : null;
    }

    private function looksLikeRepeatedHeader(array $payload): bool
    {
        $fullName = SpreadsheetTable::normalizeHeader((string) ($payload['full_name'] ?? ''));

        return in_array($fullName, ['ho va ten', 'ten hoc sinh'], true);
    }

    private function updateSettings(string $yearCode): void
    {
        $site = SystemSetting::where('key', 'site.info')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'site.info'], [
            'value' => array_replace([
                'site_name' => 'IOE nội bộ',
                'contest_name' => 'IOE nội bộ Trường THPT Võ Văn Kiệt',
            ], $site, ['school_year' => $yearCode]),
        ]);

        $account = SystemSetting::where('key', 'account.options')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'account.options'], [
            'value' => array_replace([
                'student_registration_enabled' => true,
                'allow_ioe_id_as_credential' => false,
                'student_code_lookup_url' => route('student_code.lookup', [], false),
                'student_code_help' => 'Nếu chưa biết mã học sinh, hãy dùng trang tra cứu hoặc liên hệ giáo viên phụ trách.',
            ], $account),
        ]);

        $score = SystemSetting::where('key', 'score.options')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'score.options'], [
            'value' => array_replace($score, [
                'public_scoreboard' => false,
                'show_ranking' => false,
            ]),
        ]);
    }

    private function emptyReport(string $yearCode, bool $dryRun): array
    {
        return [
            'type' => 'academic_year_roster',
            'school_year' => $yearCode,
            'dry_run' => $dryRun,
            'total_files' => 0,
            'total_sheets' => 0,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'created_students' => 0,
            'updated_students' => 0,
            'created_classes' => 0,
            'created_grades' => 0,
            'duplicates_merged' => 0,
            'skipped_rows' => 0,
            'files' => [],
            'rows' => [],
            'errors' => [],
        ];
    }

    private function mergeReport(array $report, array $fileReport): array
    {
        foreach (['total_sheets', 'total_rows', 'valid_rows', 'invalid_rows'] as $key) {
            $report[$key] += $fileReport[$key] ?? 0;
        }

        $report['files'] = [...$report['files'], ...($fileReport['files'] ?? [])];
        $report['rows'] = [...$report['rows'], ...($fileReport['rows'] ?? [])];
        $report['errors'] = [...$report['errors'], ...($fileReport['errors'] ?? [])];

        return $report;
    }
}
