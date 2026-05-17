<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\Ranking;
use App\Services\AwardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AwardController extends Controller
{
    public function __construct(private readonly AwardService $awardService) {}

    public function index(Request $request, Exam $exam): View
    {
        $rankings = Ranking::where('exam_id', $exam->id)
            ->whereNotNull('award_code')
            ->with(['student', 'studentScore'])
            ->when($request->grade, fn ($q) => $q->where('grade_number', $request->grade))
            ->orderBy('grade_number')
            ->orderByRaw("CASE award_code WHEN 'gold' THEN 1 WHEN 'first' THEN 1 WHEN 'silver' THEN 2 WHEN 'second' THEN 2 WHEN 'bronze' THEN 3 WHEN 'third' THEN 3 ELSE 4 END")
            ->orderBy('rank')
            ->get()
            ->groupBy('grade_number');

        $awardRules = AwardRule::where('exam_id', $exam->id)->with('items')->get();
        $grades = Ranking::where('exam_id', $exam->id)->whereNotNull('award_code')->distinct()->pluck('grade_number')->sort()->values();

        return view('admin.awards.index', compact('exam', 'rankings', 'awardRules', 'grades'));
    }

    public function run(Request $request, Exam $exam)
    {
        $grade = $request->integer('grade_number') ?: null;
        $count = $this->awardService->run($exam, $grade);

        return back()->with('success', "Đã xếp giải cho {$count} học sinh.");
    }
}
