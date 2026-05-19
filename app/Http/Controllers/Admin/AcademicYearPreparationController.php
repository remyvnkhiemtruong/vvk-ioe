<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicYearDataResetService;
use App\Services\AcademicYearRosterImportService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AcademicYearPreparationController extends Controller
{
    public function create(AcademicYearDataResetService $reset): View
    {
        $year = '2026-2027';
        $academicYear = AcademicYear::where('code', $year)->first();

        return view('admin.academic-years.prepare', [
            'year' => $year,
            'deleteYear' => '2025-2026',
            'resetReport' => $reset->dryRun('2025-2026'),
            'batches' => ImportBatch::whereIn('type', ['academic_year_roster', 'academic_year_prepare'])->latest()->take(10)->get(),
            'stats' => [
                'students' => Student::when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))->count(),
                'classes' => SchoolClass::when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))->count(),
                'grades' => Student::when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))->distinct('grade')->count('grade'),
                'accounts' => User::whereNotNull('student_id')->count(),
                'exams' => Exam::where('school_year', $year)->count(),
            ],
        ]);
    }

    public function preview(Request $request, AcademicYearRosterImportService $roster): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'year' => ['required', 'string', 'max:20'],
        ]);

        $dir = storage_path('app/imports/'.$request->input('year').'/preview-'.now()->format('YmdHis'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($request->file('files') as $file) {
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $extension = $file->getClientOriginalExtension();
            $file->move($dir, $name.'-'.Str::random(6).'.'.$extension);
        }

        try {
            $batch = $roster->createPreviewBatch($dir, (string) $request->input('year'), $request->user()?->id);
        } catch (Throwable $throwable) {
            return back()->withErrors(['files' => 'Không đọc được file import: '.$throwable->getMessage()]);
        }

        return redirect()
            ->route('admin.academic-years.prepare')
            ->with('status', 'Đã tạo preview roster. Vui lòng xem report và chạy dry-run trước khi xác nhận.')
            ->with('preview_batch_id', $batch->id);
    }

    public function run(
        Request $request,
        AcademicYearDataResetService $reset,
        AcademicYearRosterImportService $roster,
        SystemSettingService $settings
    ): RedirectResponse {
        $data = $request->validate([
            'year' => ['required', 'string', 'max:20'],
            'delete_year' => ['required', 'string', 'max:20'],
            'batch_id' => ['nullable', 'integer', 'exists:import_batches,id'],
            'path' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'confirm_understanding' => ['accepted'],
            'confirm_text' => ['required', 'string', 'in:XOA-2025-2026'],
        ], [
            'confirm_understanding.accepted' => 'Vui lòng tick xác nhận đã hiểu thao tác xóa dữ liệu nghiệp vụ.',
            'confirm_text.in' => 'Chuỗi xác nhận phải là XOA-2025-2026.',
        ]);

        $path = $data['path'] ?? null;
        if (! $path && filled($data['batch_id'] ?? null)) {
            $path = ImportBatch::find($data['batch_id'])?->file_name;
        }
        $path ??= storage_path('app/imports/'.$data['year']);

        try {
            $report = DB::transaction(function () use ($request, $reset, $roster, $settings, $data, $path): array {
                $resetReport = $reset->execute($data['delete_year']);
                $rosterReport = $roster->importPath($path, $data['year'], false, $request->user()?->id);

                if ($request->hasFile('logo')) {
                    $settings->storeLogo($request->file('logo'));
                }

                return [
                    'year' => $data['year'],
                    'delete_year' => $data['delete_year'],
                    'reset' => $resetReport,
                    'roster' => $rosterReport,
                    'created_exams' => 0,
                    'finished_at' => now()->toIso8601String(),
                ];
            });
        } catch (Throwable $throwable) {
            return back()->withErrors(['run' => 'Prepare thất bại, transaction đã rollback: '.$throwable->getMessage()]);
        }

        $batch = ImportBatch::create([
            'type' => 'academic_year_prepare',
            'file_name' => $path,
            'status' => 'committed',
            'total_rows' => (int) data_get($report, 'roster.total_rows', 0),
            'valid_rows' => (int) data_get($report, 'roster.valid_rows', 0),
            'invalid_rows' => (int) data_get($report, 'roster.invalid_rows', 0),
            'report' => $report,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.academic-years.prepare')
            ->with('status', 'Đã xóa dữ liệu nghiệp vụ 2025-2026 và import roster 2026-2027. Không tạo kỳ thi mới.')
            ->with('report_batch_id', $batch->id);
    }

    public function report(ImportBatch $batch)
    {
        abort_unless(in_array($batch->type, ['academic_year_roster', 'academic_year_prepare'], true), 404);

        return response()->json($batch->report ?: []);
    }
}
