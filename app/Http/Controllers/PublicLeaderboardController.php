<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Ranking;
use App\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLeaderboardController extends Controller
{
    public function index(Request $request, SystemSettingService $settings): View
    {
        $exam = $request->integer('exam_id')
            ? Exam::find($request->integer('exam_id'))
            : Exam::whereHas('rankings')->latest('id')->first();

        return $this->show($request, $settings, $exam);
    }

    public function show(Request $request, SystemSettingService $settings, ?Exam $exam = null): View
    {
        $scoreOptions = $settings->get('score.options', []);
        $exams = Exam::whereHas('rankings')->latest('id')->get();
        $scope = $request->input('scope', data_get($scoreOptions, 'ranking_scope', 'school'));
        if (! in_array($scope, ['school', 'ward', 'province', 'national'], true)) {
            $scope = 'school';
        }

        $canShow = $exam
            && ((bool) $exam->publish_scores || (bool) data_get($scoreOptions, 'public_scoreboard', false))
            && ((bool) data_get($scoreOptions, 'show_ranking', false) || (bool) $exam->show_public_stats);

        $rankings = collect();
        $rankingsByGrade = collect();
        $topByGrade = collect();
        $classes = collect();
        $grades = collect();
        $lastGeneratedAt = null;

        if ($canShow) {
            $baseQuery = Ranking::query()
                ->where('exam_id', $exam->id)
                ->where('scope', $scope)
                ->with(['student:id,full_name,class_name', 'studentScore:id,class_name'])
                ->when($request->filled('grade'), fn ($query) => $query->where('grade_number', (int) $request->input('grade')))
                ->when($request->filled('class_name'), fn ($query) => $query->where(function ($q) use ($request) {
                    $className = $request->input('class_name');
                    $q->whereHas('student', fn ($student) => $student->where('class_name', $className))
                        ->orWhereHas('studentScore', fn ($score) => $score->where('class_name', $className));
                }))
                ->when($request->filled('q'), fn ($query) => $query->whereHas('student', fn ($student) => $student->where('full_name', 'like', '%'.$request->input('q').'%')))
                ->orderBy('grade_number')
                ->orderBy('rank')
                ->orderByDesc('score')
                ->orderByRaw('CASE WHEN duration_seconds IS NULL THEN 1 ELSE 0 END')
                ->orderBy('duration_seconds')
                ->orderBy('student_id');

            $rankings = $baseQuery->get();
            $rankingsByGrade = $rankings->groupBy('grade_number');
            $topByGrade = $rankingsByGrade->map(fn ($items) => $items->take(3)->values());
            $lastGeneratedAt = $rankings->max('generated_at');

            $grades = Ranking::where('exam_id', $exam->id)
                ->where('scope', $scope)
                ->whereNotNull('grade_number')
                ->distinct()
                ->orderBy('grade_number')
                ->pluck('grade_number');

            $classes = Ranking::where('exam_id', $exam->id)
                ->where('scope', $scope)
                ->with('student:id,class_name')
                ->get()
                ->map(fn (Ranking $ranking) => $ranking->student?->class_name)
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }

        return view('public.leaderboard', [
            'exam' => $exam,
            'exams' => $exams,
            'settings' => $settings,
            'scoreOptions' => $scoreOptions,
            'canShow' => $canShow,
            'scope' => $scope,
            'rankings' => $rankings,
            'rankingsByGrade' => $rankingsByGrade,
            'topByGrade' => $topByGrade,
            'grades' => $grades,
            'classes' => $classes,
            'lastGeneratedAt' => $lastGeneratedAt,
        ]);
    }
}
