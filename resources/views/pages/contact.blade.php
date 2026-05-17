@php
    $contact = \Illuminate\Support\Facades\Schema::hasTable('system_settings')
        ? (\App\Models\SystemSetting::where('key', 'site.contact')->first()?->value ?? [])
        : [];
@endphp
<x-guest-layout>
    <div class="space-y-4">
        <h1 class="text-2xl font-semibold">Liên hệ</h1>
        <dl class="space-y-3 text-sm text-slate-700">
            <div><dt class="font-semibold">Giáo viên phụ trách</dt><dd>{{ $contact['teacher_name'] ?? 'Chưa cấu hình' }}</dd></div>
            <div><dt class="font-semibold">Số điện thoại</dt><dd>{{ $contact['phone'] ?? 'Chưa cấu hình' }}</dd></div>
            <div><dt class="font-semibold">Email</dt><dd>{{ $contact['email'] ?? 'Chưa cấu hình' }}</dd></div>
            <div><dt class="font-semibold">Ghi chú</dt><dd>{{ $contact['note'] ?? 'Học sinh liên hệ giáo viên phụ trách khi cần hỗ trợ tài khoản, thông tin import hoặc quên mật khẩu.' }}</dd></div>
        </dl>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-emerald-700">Về trang chủ</a>
    </div>
</x-guest-layout>
