<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\ExamTimeWindow;
use App\Models\Student;
use App\Services\ExamStudentService;
use App\Services\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamStudentController extends Controller
{
    public function __construct(
        private readonly EligibilityService $eligibility,
        private readonly ExamStudentService $examStudents,
    ) {}

    public function index(Request $request, Exam $exam): View
    {
        $query = ExamStudent::where('exam_id', $exam->id)
            ->with(['student', 'assignedTimeSlot', 'studentScore'])
            ->orderBy('grade_number')
            ->orderBy('class_name');

        if ($request->grade) {
            $query->where('grade_number', $request->grade);
        }
        if ($request->eligibility) {
            $query->where('eligibility_status', $request->eligibility);
        }
        if ($request->search) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%'));
        }

        $examStudents = $query->paginate(50)->withQueryString();
        $students = Student::orderBy('grade')->orderBy('class_name')->orderBy('full_name')->get(['id', 'full_name', 'grade', 'class_name', 'student_code']);
        $timeSlots = ExamTimeWindow::whereIn('exam_session_id', $exam->sessions()->pluck('id'))
            ->orderBy('starts_at')
            ->get();

        return view('admin.exam-students.index', compact('exam', 'examStudents', 'students', 'timeSlots'));
    }

    public function store(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'grade_number'        => 'nullable|integer|min:1|max:12',
            'ioe_username'        => 'nullable|string|max:100',
            'ioe_account_id'      => 'nullable|string|max:100',
            'ioe_account_verified'=> 'boolean',
            'self_training_round' => 'nullable|integer|min:0',
        ]);

        // Prevent duplicate
        $existing = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', $data['student_id'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'Học sinh đã có trong danh sách kỳ thi này.');
        }

        $student = Student::findOrFail($data['student_id']);
        $data['ioe_account_verified'] = (bool) ($data['ioe_account_verified'] ?? false);
        $data['self_training_round'] = (int) ($data['self_training_round'] ?? $student->current_self_training_round ?? 0);

        $es = ExamStudent::create(array_merge($data, [
            'exam_id'    => $exam->id,
            'grade_number' => $data['grade_number'] ?? $student->grade,
            'class_name' => $student->class_name,
            'status'     => 'draft',
        ]));

        // Auto-check eligibility
        $this->eligibility->check($es);

        return back()->with('success', 'Đã thêm học sinh vào danh sách kỳ thi.');
    }

    public function update(Request $request, Exam $exam, ExamStudent $examStudent)
    {
        $data = $request->validate([
            'ioe_username'        => 'nullable|string|max:100',
            'ioe_account_id'      => 'nullable|string|max:100',
            'ioe_account_verified'=> 'boolean',
            'self_training_round' => 'nullable|integer|min:0',
            'assigned_time_slot_id'=> 'nullable|exists:exam_time_windows,id',
            'status'              => 'nullable|in:'.implode(',', ExamStudent::STATUSES),
            'note'                => 'nullable|string',
        ]);

        $data['ioe_account_verified'] = (bool) ($data['ioe_account_verified'] ?? false);
        $data['self_training_round'] = (int) ($data['self_training_round'] ?? $examStudent->student?->current_self_training_round ?? 0);

        $slotId = $data['assigned_time_slot_id'] ?? null;
        unset($data['assigned_time_slot_id']);

        $examStudent->update($data);

        if ($slotId) {
            $this->examStudents->assignToSlot($examStudent, \App\Models\ExamTimeWindow::findOrFail($slotId));
        }

        return back()->with('success', 'Đã cập nhật thông tin học sinh.');
    }

    public function destroy(Exam $exam, ExamStudent $examStudent)
    {
        $examStudent->update(['status' => 'cancelled']);

        return back()->with('success', 'Đã hủy học sinh khỏi danh sách.');
    }

    public function check(Exam $exam, ExamStudent $examStudent)
    {
        $result = $this->eligibility->check($examStudent);

        return back()->with(
            $result['eligible'] ? 'success' : 'warning',
            $result['eligible']
                ? 'Học sinh đủ điều kiện dự thi.'
                : 'Thiếu điều kiện: '.implode('; ', $result['reasons'])
        );
    }

    public function checkAll(Exam $exam)
    {
        $result = $this->eligibility->checkAll($exam);

        return back()->with('success', sprintf(
            'Đã kiểm tra %d học sinh: %d đủ điều kiện, %d thiếu điều kiện.',
            $result['total'], $result['eligible'], $result['ineligible']
        ));
    }

    public function markRegisteredOnIoe(Request $request, Exam $exam, ExamStudent $examStudent)
    {
        $this->examStudents->markRegisteredOnIoe($examStudent, $request->user());

        return back()->with('success', 'Đã đánh dấu học sinh đã đăng ký trên ioe.vn.');
    }
}
