<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\RoomComputer;
use App\Models\SeatAssignment;
use App\Services\SeatAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        return view('admin.assignments.index', [
            'assignments' => SeatAssignment::with(['registration.chosenSession', 'session', 'room', 'computer'])->latest()->paginate(20),
            'sessions' => ExamSession::with('exam')->latest()->get(),
            'rooms' => ExamRoom::orderBy('room_name')->get(),
            'registrations' => ExamRegistration::with(['chosenSession'])
                ->where('status', 'approved')
                ->doesntHave('seatAssignment')
                ->orderBy('class_name')
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(Request $request, SeatAssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'exam_session_id' => ['required', 'exists:exam_sessions,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'registration_ids' => ['required', 'array'],
            'registration_ids.*' => ['distinct', 'exists:exam_registrations,id'],
            'method' => ['required', 'in:grade,class,registered_at,name,balanced,random,manual'],
        ]);

        $count = $service->assign(
            ExamSession::findOrFail($data['exam_session_id']),
            ExamRoom::findOrFail($data['exam_room_id']),
            ExamRegistration::whereIn('id', $data['registration_ids'])->get(),
            $data['method'],
            $request->user(),
        );

        return back()->with('status', "Đã phân phòng/máy cho {$count} thí sinh.");
    }

    public function move(Request $request, SeatAssignment $assignment, SeatAssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'new_computer_id' => ['required', 'exists:room_computers,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->moveToBackup($assignment, RoomComputer::findOrFail($data['new_computer_id']), $data['reason'], $request->user());

        return back()->with('status', 'Đã chuyển máy và ghi sự cố.');
    }
}
