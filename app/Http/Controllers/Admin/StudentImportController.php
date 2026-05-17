<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

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
}
