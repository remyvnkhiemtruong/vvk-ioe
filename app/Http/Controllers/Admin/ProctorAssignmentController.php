<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ProctorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProctorAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $examId = $request->integer('exam_id');

        $assignments = ProctorAssignment::with(['user', 'session.exam', 'room'])
            ->when($examId, fn ($query) => $query->whereHas('session', fn ($sessions) => $sessions->where('exam_id', $examId)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.proctors.index', [
            'assignments' => $assignments,
            'exams' => Exam::where('level', 'school')->latest()->get(),
            'sessions' => ExamSession::with(['exam', 'room'])
                ->when($examId, fn ($query) => $query->where('exam_id', $examId))
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->get(),
            'rooms' => ExamRoom::where('status', 'active')->orderBy('room_name')->get(),
            'proctors' => User::where('status', 'active')
                ->where(function ($query) {
                    $query->where('role', 'proctor')
                        ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'proctor'));
                })
                ->orderBy('name')
                ->get(),
            'selectedExamId' => $examId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'exam_session_id' => ['required', 'exists:exam_sessions,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'role' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->validate([
            'user_id' => [
                Rule::unique('proctor_assignments')->where(fn ($query) => $query
                    ->where('exam_session_id', $data['exam_session_id'])
                    ->where('exam_room_id', $data['exam_room_id'])),
            ],
        ], [
            'user_id.unique' => 'Giám thị này đã được phân công cho cùng ca và phòng thi.',
        ]);

        $session = ExamSession::findOrFail($data['exam_session_id']);

        ProctorAssignment::create([
            ...$data,
            'exam_id' => $session->exam_id,
            'role_in_room' => $data['role'],
            'status' => 'active',
        ]);

        return back()->with('success', 'Đã phân công giám thị.');
    }

    public function destroy(ProctorAssignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return back()->with('success', 'Đã xóa phân công giám thị.');
    }
}
