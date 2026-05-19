<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AwardRule;
use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudentScore;
use App\Services\AwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AwardController extends Controller
{
    private const SCOPES = [
        'school' => 'Cấp trường',
        'ward' => 'Cấp xã/phường',
        'province' => 'Cấp tỉnh',
        'national' => 'Cấp quốc gia',
    ];

    public function __construct(private readonly AwardService $awardService) {}

    public function index(Request $request, Exam $exam): View
    {
        $selectedScope = array_key_exists($request->input('scope'), self::SCOPES)
            ? $request->input('scope')
            : 'school';
        $selectedGrade = $request->integer('grade') ?: null;

        $rankings = Ranking::where('exam_id', $exam->id)
            ->where('scope', $selectedScope)
            ->whereNotNull('award_code')
            ->with(['student', 'studentScore', 'awardRule'])
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade))
            ->orderBy('grade_number')
            ->orderByRaw("CASE award_code WHEN 'gold' THEN 1 WHEN 'first' THEN 1 WHEN 'silver' THEN 2 WHEN 'second' THEN 2 WHEN 'bronze' THEN 3 WHEN 'third' THEN 3 ELSE 4 END")
            ->orderBy('rank')
            ->get();

        $awardRules = AwardRule::where('exam_id', $exam->id)
            ->with('items')
            ->orderByDesc('is_active')
            ->orderBy('scope')
            ->orderByRaw('CASE WHEN grade_number IS NULL THEN 0 ELSE grade_number END')
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        $grades = collect([10, 11, 12])
            ->merge(StudentScore::where('exam_id', $exam->id)->whereNotNull('grade_number')->distinct()->pluck('grade_number'))
            ->merge(Ranking::where('exam_id', $exam->id)->whereNotNull('grade_number')->distinct()->pluck('grade_number'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rankingExists = Ranking::where('exam_id', $exam->id)
            ->where('scope', $selectedScope)
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade))
            ->exists();
        $needsRerank = StudentScore::where('exam_id', $exam->id)
            ->when($selectedGrade !== null, fn ($q) => $q->where('grade_number', $selectedGrade))
            ->where('needs_rerank', true)
            ->exists();

        return view('admin.awards.index', [
            'exam' => $exam,
            'rankings' => $rankings->groupBy('grade_number')->sortKeys(),
            'awardRules' => $awardRules,
            'grades' => $grades,
            'selectedScope' => $selectedScope,
            'selectedGrade' => $selectedGrade,
            'scopes' => self::SCOPES,
            'rankingExists' => $rankingExists,
            'needsRerank' => $needsRerank,
            'awardReport' => session('award_report'),
            'summaryByGrade' => $rankings->groupBy('grade_number')->map->count()->sortKeys(),
            'summaryByCode' => $rankings->groupBy('award_code')->map->count()->sortKeys(),
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
        $report = $this->awardService->run($exam, $grade, $scope);

        return redirect()
            ->route('admin.exam.awards.index', ['exam' => $exam, 'scope' => $scope, 'grade' => $grade])
            ->with('success', 'Đã xếp giải cho '.$report['total_awarded'].' học sinh.')
            ->with('award_report', $report);
    }

    public function updateRule(Request $request, Exam $exam, AwardRule $awardRule): RedirectResponse
    {
        abort_unless($awardRule->exam_id === $exam->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'in:school,ward,province,national'],
            'grade_number' => ['nullable', 'integer', 'min:1', 'max:12'],
            'min_score' => ['nullable', 'numeric', 'min:0'],
            'min_score_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'top_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'max_awards' => ['nullable', 'integer', 'min:1'],
            'priority_order' => ['required', 'integer', 'min:0', 'max:255'],
            'items' => ['array'],
            'items.*.ratio_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:255'],
        ]);

        $awardRule->update([
            'name' => $data['name'],
            'scope' => $data['scope'],
            'grade_number' => $data['grade_number'] ?? null,
            'min_score' => $data['min_score'] ?? null,
            'min_score_percent' => $data['min_score_percent'] ?? null,
            'top_percent' => $data['top_percent'] ?? null,
            'max_awards' => $data['max_awards'] ?? null,
            'priority_order' => $data['priority_order'],
            'is_active' => $request->boolean('is_active'),
        ]);

        foreach ($data['items'] ?? [] as $itemId => $itemData) {
            $awardRule->items()->whereKey($itemId)->update([
                'ratio_percent' => $itemData['ratio_percent'] ?? null,
                'max_quantity' => $itemData['max_quantity'] ?? null,
                'sort_order' => $itemData['sort_order'],
            ]);
        }

        return back()->with('success', 'Đã cập nhật rule xếp giải.');
    }
}
