<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnterScoreRequest;
use App\Models\ExamRegistration;
use App\Models\ExamScore;
use App\Services\ProctorScope;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScoreController extends Controller
{
    public function index(ProctorScope $scope): View
    {
        $user = request()->user();

        return view('admin.scores.index', [
            'registrations' => $scope->scopeRegistrations(
                ExamRegistration::with(['seatAssignment.session', 'seatAssignment.room', 'score']),
                $user,
            )->latest()->paginate(30),
        ]);
    }

    public function store(EnterScoreRequest $request, ExamRegistration $registration, ScoreService $scores, ProctorScope $scope): RedirectResponse
    {
        abort_unless($scope->canAccessRegistration($request->user(), $registration), 403);

        $score = ExamScore::firstOrCreate([
            'exam_registration_id' => $registration->id,
        ], [
            'seat_assignment_id' => $registration->seatAssignment?->id,
        ]);

        if ($score->score_status === 'locked') {
            $scores->unlockAndChange($score, $request->validated(), $request->input('reason'), $request->user());
        } else {
            $scores->enter($score, $request->validated(), $request->user());
        }

        return back()->with('status', 'Đã lưu điểm thi.');
    }

    public function verify(ExamScore $score, ScoreService $scores): RedirectResponse
    {
        abort_unless(request()->user()->can('scores.verify'), 403);

        $scores->verify($score, request()->user());

        return back()->with('status', 'Đã xác nhận điểm.');
    }

    public function lock(ExamScore $score, ScoreService $scores): RedirectResponse
    {
        abort_unless(request()->user()->can('scores.lock'), 403);

        $scores->lock($score, request()->user());

        return back()->with('status', 'Đã khóa điểm chính thức.');
    }
}
