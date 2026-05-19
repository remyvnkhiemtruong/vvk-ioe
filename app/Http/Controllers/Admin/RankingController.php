<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudentScore;
use App\Services\RankingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    private const SCOPES = [
        'school' => 'Cấp trường',
        'ward' => 'Cấp xã/phường',
        'province' => 'Cấp tỉnh',
        'national' => 'Cấp quốc gia',
    ];

    public function __construct(private readonly RankingService $rankingService) {}

    public function index(Request $request, Exam $exam): View
    {
        $selectedScope = array_key_exists($request->input('scope'), self::SCOPES)
            ? $request->input('scope')
            : 'school';
        $selectedGrade = $request->integer('grade') ?: null;

        $rankings = Ranking::where('exam_id', $exam->id)
            ->with(['student', 'studentScore', 'awardRule'])
            ->where('scope', $selectedScope)
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade))
            ->orderBy('grade_number')
            ->orderBy('rank')
            ->orderByDesc('score')
            ->orderByRaw('CASE WHEN duration_seconds IS NULL THEN 1 ELSE 0 END')
            ->orderBy('duration_seconds')
            ->orderBy('student_id')
            ->get();

        $rankingGroups = $rankings->groupBy('grade_number')->sortKeys();
        $grades = collect([10, 11, 12])
            ->merge(StudentScore::where('exam_id', $exam->id)->whereNotNull('grade_number')->distinct()->pluck('grade_number'))
            ->merge(Ranking::where('exam_id', $exam->id)->whereNotNull('grade_number')->distinct()->pluck('grade_number'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $scoreQuery = StudentScore::where('exam_id', $exam->id)
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade));

        $scoreCount = (clone $scoreQuery)->whereNotNull('score')->count();
        $needsRerank = (clone $scoreQuery)->where('needs_rerank', true)->exists();
        $hasRanking = Ranking::where('exam_id', $exam->id)
            ->where('scope', $selectedScope)
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade))
            ->exists();

        return view('admin.rankings.index', [
            'exam' => $exam,
            'rankings' => $rankings,
            'rankingGroups' => $rankingGroups,
            'grades' => $grades,
            'needsRerank' => $needsRerank,
            'hasRanking' => $hasRanking,
            'scoreCount' => $scoreCount,
            'selectedScope' => $selectedScope,
            'selectedGrade' => $selectedGrade,
            'scopes' => self::SCOPES,
            'rankingReport' => session('ranking_report'),
        ]);
    }

    public function run(Request $request, Exam $exam): RedirectResponse
    {
        $request->validate([
            'scope' => ['required', 'in:school,ward,province,national'],
            'grade_number' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $grade = $request->integer('grade_number') ?: null;
        $scope = $request->input('scope', 'school');
        $report = $this->rankingService->run($exam, $scope, $grade);

        return redirect()
            ->route('admin.exam.rankings.index', ['exam' => $exam, 'scope' => $scope, 'grade' => $grade])
            ->with('success', 'Đã xếp hạng cho '.$report['total_ranked'].' học sinh.')
            ->with('ranking_report', $report);
    }
}
