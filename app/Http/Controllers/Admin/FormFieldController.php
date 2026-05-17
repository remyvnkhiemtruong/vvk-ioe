<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamFormField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormFieldController extends Controller
{
    public function index(Request $request): View
    {
        $exam = Exam::where('level', 'school')
            ->when($request->integer('exam_id'), fn ($query, $examId) => $query->whereKey($examId))
            ->latest()
            ->first();

        return view('admin.form-fields.index', [
            'exam' => $exam,
            'exams' => Exam::where('level', 'school')->latest()->get(),
            'fields' => $exam
                ? $exam->formFields()->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'field_key' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:text,textarea,select,radio,checkbox,boolean,date,number,email'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'options_text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
        ], [
            'field_key.regex' => 'Mã trường chỉ được dùng chữ, số và dấu gạch dưới.',
        ]);

        ExamFormField::updateOrCreate([
            'exam_id' => $data['exam_id'],
            'field_key' => $data['field_key'],
        ], $this->payload($data));

        return back()->with('success', 'Đã lưu trường đăng ký.');
    }

    public function update(Request $request, ExamFormField $field): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:text,textarea,select,radio,checkbox,boolean,date,number,email'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'options_text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $field->update($this->payload($data));

        return back()->with('success', 'Đã cập nhật cấu hình form.');
    }

    private function payload(array $data): array
    {
        $options = collect(preg_split('/\r\n|\r|\n/', (string) ($data['options_text'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return [
            'label' => $data['label'],
            'type' => $data['type'],
            'help_text' => $data['help_text'] ?? null,
            'options' => $options ?: null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'metadata' => [
                'updated_from_admin_ui' => true,
                'updated_at' => now()->toIso8601String(),
            ],
        ];
    }
}
