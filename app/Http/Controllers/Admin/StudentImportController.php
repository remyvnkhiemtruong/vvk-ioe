<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\BusinessDataResetImportService;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentImportController extends Controller
{
    public function create(): View
    {
        return view('admin.students.import', [
            'batches' => ImportBatch::latest()->paginate(10),
        ]);
    }

    public function preview(Request $request, StudentImportService $imports): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ], [
            'file.required' => 'Vui lòng chọn file Excel.',
            'file.mimes' => 'File import phải là xlsx, xls hoặc csv.',
        ]);

        try {
            $batch = $imports->preview($request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('admin.students.import.show', $batch);
    }

    public function show(ImportBatch $batch): View
    {
        return view('admin.students.import-preview', compact('batch'));
    }

    public function commit(ImportBatch $batch, StudentImportService $imports): RedirectResponse
    {
        $count = $imports->commit($batch);

        return redirect()->route('admin.students.index')->with('status', "Đã lưu {$count} học sinh từ file import.");
    }

    public function resetCreate(BusinessDataResetImportService $resetImports): View
    {
        return view('admin.students.reset-import', [
            'batches' => ImportBatch::where('type', 'reset_students')->latest()->paginate(10),
            'clearCounts' => $resetImports->businessCounts(),
        ]);
    }

    public function resetPreview(Request $request, BusinessDataResetImportService $resetImports): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'school_year' => ['required', 'string', 'max:20'],
            'reset_award_rules' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Vui lòng chọn file Excel.',
            'file.mimes' => 'File import phải là xlsx, xls hoặc csv.',
        ]);

        try {
            $batch = $resetImports->createPreviewBatch(
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalName(),
                $data['school_year'],
                $request->boolean('reset_award_rules')
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => 'Không đọc được file import: '.$exception->getMessage()]);
        }

        return redirect()->route('admin.students.reset_import.show', $batch);
    }

    public function resetShow(ImportBatch $batch): View
    {
        abort_unless($batch->type === 'reset_students', 404);

        return view('admin.students.reset-import-preview', compact('batch'));
    }

    public function resetCommit(Request $request, ImportBatch $batch, BusinessDataResetImportService $resetImports): RedirectResponse
    {
        abort_unless($batch->type === 'reset_students', 404);

        $request->validate([
            'confirm_reset_import' => ['accepted'],
        ], [
            'confirm_reset_import.accepted' => 'Vui lòng xác nhận đã hiểu thao tác Clear & Import trước khi thực hiện.',
        ]);

        try {
            $report = $resetImports->commitBatch(
                $batch,
                (string) data_get($batch->report, 'school_year', '2025-2026'),
                (bool) data_get($batch->report, 'reset_award_rules', false)
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => 'Clear & Import thất bại, dữ liệu đã rollback: '.$exception->getMessage()]);
        }

        return redirect()
            ->route('admin.students.reset_import.show', $batch)
            ->with('status', 'Đã clear dữ liệu nghiệp vụ và import '.$report['committed_rows'].' học sinh.');
    }
}
