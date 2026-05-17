<?php

namespace App\Http\Requests;

use App\Support\SchoolClassOptions;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_name' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! SchoolClassOptions::contains((string) $value)) {
                    $fail('Lớp chưa có trong danh sách lớp/học sinh đã import.');
                }
            }],
            'credential' => ['required', 'string', 'max:100'],
            'username'   => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'phone'      => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'email'      => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'avatar'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password'   => ['required', 'confirmed', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_name.required'  => 'Vui lòng chọn lớp.',
            'credential.required'  => 'Vui lòng nhập mã học sinh hoặc CCCD/mã định danh.',
            'username.regex'       => 'Username chỉ được dùng chữ cái không dấu, số và dấu gạch dưới (_).',
            'username.unique'      => 'Username này đã được sử dụng, vui lòng chọn tên khác.',
            'phone.regex'          => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
            'email.email'          => 'Email không đúng định dạng.',
            'email.unique'         => 'Email này đã được sử dụng cho tài khoản khác.',
            'avatar.image'         => 'File ảnh đại diện phải là ảnh (jpg, png, webp).',
            'avatar.max'           => 'Ảnh đại diện không được vượt quá 2MB.',
            'password.required'    => 'Vui lòng nhập mật khẩu.',
            'password.confirmed'   => 'Xác nhận mật khẩu không khớp.',
            'password.min'         => 'Mật khẩu phải có tối thiểu 8 ký tự.',
            'password.regex'       => 'Mật khẩu phải có cả chữ và số.',
        ];
    }
}
