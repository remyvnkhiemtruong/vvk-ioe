<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Ranking;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(private readonly RankingService $rankingService) {}

    public function index(Request $request, Exam $exam): View
    {
        $query = Ranking::where('exam_id', $exam->id)
            ->where('is_highest_award', true)
            ->with(['student', 'studentScore', 'awardRule'])
            ->orderBy('grade_number')
            ->orderBy('rank');

        if ($request->grade) {
            $query->where('grade_number', $request->grade);
        }
        if ($request->scope) {
            $query->where('scope', $request->scope);
        }

        $rankings = $query->paginate(100)->withQueryString();
        $grades = Ranking::where('exam_id', $exam->id)->distinct()->pluck('grade_number')->sort()->values();

        $needsRerank = \App\Models\StudentScore::where('exam_id', $exam->id)
            ->where('needs_rerank', true)->exists();

        return view('admin.rankings.index', compact('exam', 'rankings', 'grades', 'needsRerank'));
    }

    public function run(Request $request, Exam $exam)
    {
        $grade = $request->integer('grade_number') ?: null;
        $scope = $request->input('scope', 'school');

        $count = $this->rankingService->run($exam, $scope, $grade);

        return back()->with('success', "Đã xếp hạng cho {$count} học sinh (scope: {$scope}).");
    }
}
