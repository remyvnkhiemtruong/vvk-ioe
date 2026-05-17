<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\StudentScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScoreEntryController extends Controller
{
    public function index(Request $request, Exam $exam): View
    {
        $query = StudentScore::where('exam_id', $exam->id)
            ->with(['student', 'examStudent', 'enteredBy'])
            ->orderBy('grade_number')
            ->orderBy('class_name');

        if ($request->grade) {
            $query->where('grade_number', $request->grade);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%'));
        }

        $scores = $query->paginate(50)->withQueryString();

        // Học sinh chưa có điểm trong kỳ thi
        $unscored = ExamStudent::where('exam_id', $exam->id)
            ->whereIn('status', ['selected', 'registered_on_ioe', 'assigned_to_slot', 'completed_exam'])
            ->whereDoesntHave('studentScore')
            ->with('student')
            ->get();

        $grades = StudentScore::where('exam_id', $exam->id)
            ->distinct()->pluck('grade_number')->sort()->values();

        return view('admin.score-entry.index', compact('exam', 'scores', 'unscored', 'grades'));
    }

    public function store(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'exam_student_id' => 'required|exists:exam_students,id',
            'score'           => 'required|numeric|min:0',
            'max_score'       => 'nullable|numeric|min:0',
            'duration_seconds'=> 'nullable|integer|min:0',
            'note'            => 'nullable|string',
        ]);

        $examStudent = ExamStudent::with('exam')->findOrFail($data['exam_student_id']);
        $this->validateScore($data, $examStudent->exam);

        // Prevent duplicate
        if (StudentScore::where('exam_id', $exam->id)->where('student_id', $examStudent->student_id)->exists()) {
            return back()->with('error', 'Học sinh đã có điểm trong kỳ thi này. Dùng chức năng Sửa.');
        }

        StudentScore::create([
            'exam_id'         => $exam->id,
            'exam_student_id' => $examStudent->id,
            'student_id'      => $examStudent->student_id,
            'grade_number'    => $examStudent->grade_number,
            'class_name'      => $examStudent->class_name,
            'score'           => $data['score'],
            'max_score'       => $data['max_score'] ?? 2000,
            'duration_seconds'=> $data['duration_seconds'] ?? null,
            'entered_by'      => auth()->id(),
            'entered_at'      => now(),
            'status'          => 'draft',
            'note'            => $data['note'] ?? null,
        ]);

        // Update ExamStudent status
        $examStudent->update(['status' => 'score_entered']);

        return back()->with('success', 'Đã lưu điểm cho học sinh.');
    }

    public function update(Request $request, Exam $exam, StudentScore $studentScore)
    {
        if ($studentScore->isLocked() && ! auth()->user()->can('scores.lock')) {
            return back()->with('error', 'Điểm đã khóa. Chỉ người có quyền mới được sửa.');
        }

        $data = $request->validate([
            'score'           => 'required|numeric|min:0',
            'max_score'       => 'nullable|numeric|min:0',
            'duration_seconds'=> 'nullable|integer|min:0',
            'note'            => 'nullable|string',
        ]);

        $this->validateScore($data, $exam);

        $studentScore->update(array_merge($data, [
            'entered_by'  => auth()->id(),
            'entered_at'  => now(),
            // Nếu đã xếp giải thì đánh dấu cần chạy lại
            'needs_rerank' => $studentScore->status === 'ranked',
            'status'      => $studentScore->status === 'ranked' ? 'submitted' : $studentScore->status,
        ]));

        return back()->with('success', 'Đã cập nhật điểm.');
    }

    public function submit(Exam $exam, StudentScore $studentScore)
    {
        $studentScore->update(['status' => StudentScore::STATUS_SUBMITTED]);

        return back()->with('success', 'Đã gửi điểm.');
    }

    public function lock(Exam $exam, StudentScore $studentScore)
    {
        $studentScore->update([
            'status'    => StudentScore::STATUS_LOCKED,
            'locked_by' => auth()->id(),
            'locked_at' => now(),
        ]);

        return back()->with('success', 'Đã khóa điểm.');
    }

    public function unlock(Exam $exam, StudentScore $studentScore)
    {
        $studentScore->update([
            'status'    => StudentScore::STATUS_SUBMITTED,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        return back()->with('success', 'Đã mở khóa điểm.');
    }

    private function validateScore(array $data, Exam $exam): void
    {
        $maxScore = $data['max_score'] ?? 2000;

        if ($data['score'] > $maxScore) {
            throw ValidationException::withMessages([
                'score' => "Điểm ({$data['score']}) vượt quá điểm tối đa ({$maxScore}).",
            ]);
        }
    }
}
