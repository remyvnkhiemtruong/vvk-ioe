<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\SchoolClassImportService;
use App\Services\StaffProfileImportService;
use App\Services\StudentImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ImportSchoolDataCommand extends Command
{
    protected $signature = 'ioe:import-school-data
        {--students= : Đường dẫn file Excel danh sách học sinh}
        {--staff= : Đường dẫn file Excel danh sách cán bộ}
        {--classes= : Đường dẫn file Excel danh sách lớp học}
        {--logo= : Đường dẫn file logo trường}
        {--school-year=2025-2026 : Năm học của danh sách lớp}
        {--commit : Ghi dữ liệu vào database và storage}
        {--dry-run : Chỉ kiểm tra, không ghi dữ liệu}';

    protected $description = 'Import logo, danh sách học sinh, cán bộ và lớp học Trường THPT Võ Văn Kiệt.';

    public function handle(
        StudentImportService $students,
        StaffProfileImportService $staff,
        SchoolClassImportService $classes
    ): int {
        $commit = (bool) $this->option('commit') && ! (bool) $this->option('dry-run');

        if (! $this->hasAnyInput()) {
            $this->error('Vui lòng truyền ít nhất một file bằng --students, --staff, --classes hoặc --logo.');

            return self::FAILURE;
        }

        try {
            $jobs = [
                'classes' => ['label' => 'Lớp học', 'path' => $this->option('classes'), 'service' => $classes],
                'staff' => ['label' => 'Cán bộ', 'path' => $this->option('staff'), 'service' => $staff],
                'students' => ['label' => 'Học sinh', 'path' => $this->option('students'), 'service' => $students],
            ];

            foreach ($jobs as $type => $job) {
                if (! $job['path']) {
                    continue;
                }

                $this->ensureReadable($job['path']);
                $analysis = $type === 'classes'
                    ? $job['service']->analyzePath($job['path'], basename($job['path']), (string) $this->option('school-year'))
                    : $job['service']->analyzePath($job['path'], basename($job['path']));

                $this->reportAnalysis($job['label'], $analysis);

                if ($analysis['invalid_rows'] > 0) {
                    $this->error("File {$job['label']} còn dòng lỗi, vui lòng xử lý trước khi --commit.");

                    return self::FAILURE;
                }

                if ($commit) {
                    $batch = $type === 'classes'
                        ? $job['service']->previewPath($job['path'], basename($job['path']), (string) $this->option('school-year'))
                        : $job['service']->previewPath($job['path'], basename($job['path']));
                    $count = $job['service']->commit($batch);
                    $this->info("Đã import {$count} dòng {$job['label']}.");
                }
            }

            if ($logo = $this->option('logo')) {
                $this->importLogo($logo, $commit);
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($commit ? 'Import hoàn tất.' : 'Dry-run hoàn tất, chưa ghi dữ liệu.');

        return self::SUCCESS;
    }

    private function hasAnyInput(): bool
    {
        return (bool) ($this->option('students') || $this->option('staff') || $this->option('classes') || $this->option('logo'));
    }

    private function ensureReadable(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Không đọc được file: {$path}");
        }
    }

    private function reportAnalysis(string $label, array $analysis): void
    {
        $this->line("{$label}: {$analysis['valid_rows']}/{$analysis['total_rows']} dòng hợp lệ, {$analysis['invalid_rows']} dòng lỗi.");

        foreach (array_slice($analysis['errors'] ?? [], 0, 5) as $error) {
            $messages = implode('; ', $error['messages'] ?? []);
            $this->warn("  Dòng {$error['row']}: {$messages}");
        }
    }

    private function importLogo(string $path, bool $commit): void
    {
        $this->ensureReadable($path);

        $info = getimagesize($path);
        if (! $info || ! str_starts_with((string) $info['mime'], 'image/')) {
            throw new InvalidArgumentException('File logo không phải ảnh hợp lệ.');
        }

        $this->line("Logo: {$info[0]}x{$info[1]} px, {$info['mime']}.");

        if (! $commit) {
            return;
        }

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

        $this->info('Đã lưu logo vào storage/app/public/'.$destination.'.');
    }
}
