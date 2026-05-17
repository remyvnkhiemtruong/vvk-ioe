<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCode;
use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamCodeController extends Controller
{
    public function index(Exam $exam): View
    {
        $codes = ExamCode::where('exam_id', $exam->id)
            ->with(['session', 'timeSlot', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        $sessions = ExamSession::where('exam_id', $exam->id)->get();
        $timeSlots = ExamTimeWindow::whereIn('exam_session_id', $sessions->pluck('id'))->get();

        return view('admin.exam-codes.index', compact('exam', 'codes', 'sessions', 'timeSlots'));
    }

    public function store(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'code'              => 'required|string|max:100',
            'label'             => 'nullable|string|max:200',
            'exam_session_id'   => 'nullable|exists:exam_sessions,id',
            'exam_time_slot_id' => 'nullable|exists:exam_time_windows,id',
            'applied_grade_ids' => 'nullable|array',
            'applied_grade_ids.*' => 'integer|min:1|max:12',
        ]);

        $code = ExamCode::create(array_merge($data, [
            'exam_id'    => $exam->id,
            'source'     => 'manual_from_ioe',
            'is_active'  => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        // Audit log: Ghi nhận tạo mã
        ActivityLogger::log('exam_code.created', $code, [], $code->toArray());

        return back()->with('success', 'Đã lưu mã ca thi: '.$data['code']);
    }

    public function update(Request $request, Exam $exam, ExamCode $examCode)
    {
        $old = $examCode->toArray();
        $data = $request->validate([
            'code'              => 'required|string|max:100',
            'label'             => 'nullable|string|max:200',
            'exam_session_id'   => 'nullable|exists:exam_sessions,id',
            'exam_time_slot_id' => 'nullable|exists:exam_time_windows,id',
            'applied_grade_ids' => 'nullable|array',
            'applied_grade_ids.*' => 'integer|min:1|max:12',
            'is_active'         => 'boolean',
        ]);

        $examCode->update(array_merge($data, ['updated_by' => auth()->id()]));

        // Audit log: Ghi nhận sửa mã (quan trọng vì có thể ảnh hưởng live)
        ActivityLogger::log('exam_code.updated', $examCode, $old, $examCode->fresh()->toArray());

        return back()->with('success', 'Đã cập nhật mã ca thi.');
    }

    public function destroy(Exam $exam, ExamCode $examCode)
    {
        ActivityLogger::log('exam_code.deleted', $examCode, $examCode->toArray(), []);
        $examCode->delete();

        return back()->with('success', 'Đã xóa mã ca thi.');
    }
}
