<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\SeatAssignment;
use App\Services\ProctorScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckinController extends Controller
{
    public function index(Request $request, ProctorScope $scope): View
    {
        $assignments = $scope->scopeAssignments(
            SeatAssignment::with(['registration', 'session', 'room', 'computer', 'checkin']),
            $request->user(),
        )->latest()->paginate(30);

        return view('admin.checkins.index', compact('assignments'));
    }

    public function update(Request $request, SeatAssignment $assignment, ProctorScope $scope): RedirectResponse
    {
        abort_unless($scope->canAccessAssignment($request->user(), $assignment), 403);

        $data = $request->validate([
            'status' => ['required', 'in:not_checked_in,present,absent,late,incident,completed'],
            'personal_device_present' => ['nullable', 'boolean'],
            'charger_present' => ['nullable', 'boolean'],
            'network_ok' => ['nullable', 'boolean'],
            'ioe_login_ok' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        Checkin::updateOrCreate([
            'seat_assignment_id' => $assignment->id,
        ], [
            ...$data,
            'checked_in_at' => now(),
            'checked_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Đã cập nhật check-in.');
    }
}
