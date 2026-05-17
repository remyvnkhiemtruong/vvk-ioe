<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnterScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scores.enter') ?? false;
    }

    public function rules(): array
    {
        return [
            'official_score' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'completion_time_seconds' => ['nullable', 'integer', 'min:0'],
            'correct_answers' => ['nullable', 'integer', 'min:0'],
            'exam_status' => ['required', 'in:not_started,in_progress,completed,incomplete,incident'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'required_if:locked_change,1', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'official_score.required' => 'Vui lòng nhập điểm thi chính thức.',
            'official_score.numeric' => 'Điểm thi phải là số.',
            'exam_status.required' => 'Vui lòng chọn trạng thái bài thi.',
            'reason.required_if' => 'Vui lòng nhập lý do khi sửa điểm đã khóa.',
        ];
    }
}
