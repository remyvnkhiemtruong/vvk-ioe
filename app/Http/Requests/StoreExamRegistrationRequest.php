<?php

namespace App\Http\Requests;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Services\ExamSessionAvailabilityService;
use App\Support\SchoolClassOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->student_id;
    }

    public function rules(): array
    {
        $registration = $this->route('registration');
        $exam = $registration instanceof ExamRegistration
            ? $registration->exam
            : ($this->route('exam') instanceof Exam ? $this->route('exam') : Exam::find($this->input('exam_id')));
        $registrationId = $registration instanceof ExamRegistration ? $registration->id : null;

        return [
            'exam_id' => ['nullable', 'exists:exams,id'],
            'exam_session_id' => [($exam?->require_session_choice ?? true) ? 'required' : 'nullable', 'exists:exam_sessions,id'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'ioe_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('exam_registrations', 'ioe_id')
                    ->where('exam_id', $exam?->id)
                    ->ignore($registrationId),
            ],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Nam', 'Nữ', 'Khác', 'nam', 'nữ', 'khác', 'male', 'female', 'other'])],
            'identity_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('exam_registrations', 'identity_number')
                    ->where('exam_id', $exam?->id)
                    ->ignore($registrationId),
            ],
            'class_name' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! SchoolClassOptions::contains((string) $value)) {
                    $fail('Lớp chưa có trong danh sách lớp/học sinh đã import.');
                }
            }],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'email' => ['required', 'email', 'max:255'],
            'uses_personal_computer' => ['required', 'boolean'],
            'device_type' => ['required_if:uses_personal_computer,1', 'nullable', Rule::in(['Laptop', 'Máy tính bảng', 'Khác'])],
            'device_os' => ['required_if:uses_personal_computer,1', 'nullable', Rule::in(['Windows', 'macOS', 'Linux', 'Khác'])],
            'has_charger' => ['required_if:uses_personal_computer,1', 'nullable', 'boolean'],
            'device_note' => ['nullable', 'string', 'max:1000'],
            'device_commitment' => ['accepted_if:uses_personal_computer,1'],
            'custom_fields' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
            'confirm_information' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('exam_session_id')) {
                return;
            }

            $registration = $this->route('registration');
            $exam = $registration instanceof ExamRegistration
                ? $registration->exam
                : ($this->route('exam') instanceof Exam ? $this->route('exam') : Exam::find($this->input('exam_id')));
            $student = $this->user()?->student;
            $session = $this->input('exam_session_id') ? ExamSession::find($this->input('exam_session_id')) : null;

            if (! $exam || ! $student || ! $session) {
                return;
            }

            if ($this->boolean('uses_personal_computer') && ! $exam->allow_personal_computer) {
                $validator->errors()->add('uses_personal_computer', 'Kỳ đăng ký này chưa cho phép đăng ký máy tính cá nhân.');
            }

            $error = app(ExamSessionAvailabilityService::class)
                ->availabilityError($session, $student, $exam, $registration instanceof ExamRegistration ? $registration->id : null);

            if ($error) {
                $validator->errors()->add('exam_session_id', $error);
            }
        });
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên không được để trống.',
            'exam_session_id.required' => 'Vui lòng chọn ca thi.',
            'exam_session_id.exists' => 'Ca thi được chọn không hợp lệ.',
            'ioe_id.required' => 'Vui lòng nhập ID tài khoản IOE.',
            'ioe_id.unique' => 'ID tài khoản IOE đã được đăng ký trong kỳ thi này.',
            'identity_number.required' => 'Vui lòng nhập CCCD/mã định danh.',
            'identity_number.unique' => 'CCCD/mã định danh đã được đăng ký trong kỳ thi này.',
            'email.email' => 'Email không đúng định dạng.',
            'phone.regex' => 'Số điện thoại Việt Nam không đúng định dạng.',
            'date_of_birth.before' => 'Ngày sinh không hợp lệ.',
            'class_name.exists' => 'Lớp chưa có trong danh sách học sinh đã import.',
            'device_type.required_if' => 'Vui lòng chọn loại thiết bị cá nhân.',
            'device_type.in' => 'Loại thiết bị cá nhân không hợp lệ.',
            'device_os.required_if' => 'Vui lòng chọn hệ điều hành thiết bị.',
            'device_os.in' => 'Hệ điều hành thiết bị không hợp lệ.',
            'has_charger.required_if' => 'Vui lòng xác nhận có mang sạc hay không.',
            'device_commitment.accepted_if' => 'Vui lòng cam kết thiết bị hoạt động ổn định.',
            'confirm_information.accepted' => 'Vui lòng xác nhận thông tin đăng ký là chính xác.',
        ];
    }
}
