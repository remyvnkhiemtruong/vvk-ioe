<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\SeatAssignment;
use App\Services\ProctorScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(ProctorScope $scope): View
    {
        $user = request()->user();

        return view('admin.incidents.index', [
            'incidents' => $scope->scopeIncidents(
                Incident::with(['registration', 'assignment.session', 'assignment.room']),
                $user,
            )->latest()->paginate(20),
            'assignments' => $scope->scopeAssignments(
                SeatAssignment::with(['registration', 'session', 'room', 'computer']),
                $user,
            )->latest()->get(),
        ]);
    }

    public function store(Request $request, ProctorScope $scope): RedirectResponse
    {
        $seatAssignmentRule = $scope->needsScope($request->user())
            ? ['required', 'exists:seat_assignments,id']
            : ['nullable', 'exists:seat_assignments,id'];

        $data = $request->validate([
            'seat_assignment_id' => $seatAssignmentRule,
            'incident_type' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'solution' => ['nullable', 'string'],
            'result_impact' => ['nullable', 'in:none,retry_same_session,move_session,use_backup_account,invalid_score,disqualified'],
        ]);

        $assignment = isset($data['seat_assignment_id']) ? SeatAssignment::find($data['seat_assignment_id']) : null;
        abort_unless(! $assignment || $scope->canAccessAssignment($request->user(), $assignment), 403);

        Incident::create([
            ...$data,
            'exam_registration_id' => $assignment?->exam_registration_id,
            'exam_id' => $assignment?->registration?->exam_id,
            'exam_room_id' => $assignment?->exam_room_id,
            'exam_session_id' => $assignment?->exam_session_id,
            'result_impact' => $data['result_impact'] ?? 'none',
            'reported_by' => $request->user()->id,
            'reported_at' => now(),
        ]);

        return back()->with('status', 'Đã ghi nhận sự cố.');
    }
}
