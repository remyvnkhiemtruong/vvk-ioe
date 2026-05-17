<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IoeChecklist;
use App\Models\IoePotentialStudent;
use App\Models\IoeReferenceResult;
use App\Models\IoeResearchCalendarEvent;
use App\Models\IoeResearchCondition;
use App\Models\IoeResearchDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchController extends Controller
{
    public function index(): View
    {
        return view('admin.research.index', [
            'documents' => IoeResearchDocument::latest()->paginate(10, ['*'], 'documents'),
            'events' => IoeResearchCalendarEvent::latest()->paginate(10, ['*'], 'events'),
            'conditions' => IoeResearchCondition::latest()->get(),
            'checklists' => IoeChecklist::latest()->paginate(10, ['*'], 'checklists'),
            'potentials' => IoePotentialStudent::latest()->paginate(10, ['*'], 'potentials'),
            'results' => IoeReferenceResult::latest()->paginate(10, ['*'], 'results'),
        ]);
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:school,provincial,national,general'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'issued_date' => ['nullable', 'date'],
            'source_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file'],
            'note' => ['nullable', 'string'],
        ]);
        $data['file_path'] = $request->file('file')?->store('ioe-documents', 'public');
        $data['updated_by'] = $request->user()->id;
        IoeResearchDocument::create($data);

        return back()->with('status', 'Đã lưu văn bản nghiên cứu IOE.');
    }

    public function storeChecklist(Request $request): RedirectResponse
    {
        IoeChecklist::create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:school,provincial,national,general'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]));

        return back()->with('status', 'Đã thêm checklist.');
    }

    public function toggleChecklist(IoeChecklist $checklist): RedirectResponse
    {
        $checklist->update([
            'is_completed' => ! $checklist->is_completed,
            'completed_at' => $checklist->is_completed ? null : now(),
        ]);

        return back();
    }
}
