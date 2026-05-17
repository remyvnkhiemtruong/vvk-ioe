@php
    $settings = app(\App\Services\SystemSettingService::class);
    $contact = $settings->contact();
@endphp

<x-guest-layout>
    <div class="space-y-5">
        <h1 class="text-2xl font-semibold">Liên hệ</h1>
        <div class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
            <div class="rounded border border-slate-200 p-4">
                <div class="text-slate-500">Giáo viên phụ trách</div>
                <div class="mt-1 font-semibold">{{ $contact['teacher_name'] ?? 'Thầy Huỳnh Thanh Hào' }}</div>
                <div>{{ $contact['teacher_title'] ?? 'Giáo viên tiếng Anh, phụ trách tổ chức thi IOE' }}</div>
                <div class="mt-2">{{ $contact['teacher_email'] ?? 'huynhthanhhaota@gmail.com' }}</div>
            </div>
            <div class="rounded border border-slate-200 p-4">
                <div class="text-slate-500">Hỗ trợ học sinh</div>
                <div class="mt-1 font-semibold">{{ $contact['support_name'] ?? 'Trương Minh Khiêm' }}</div>
                <div>{{ $contact['support_title'] ?? 'Cựu học sinh, học viên Trường Sĩ quan Thông tin' }}</div>
                <div class="mt-2">{{ $contact['support_phone'] ?? '0385844458' }} · {{ $contact['support_email'] ?? 'truongminhkhiemvta@gmail.com' }}</div>
            </div>
            <div class="rounded border border-slate-200 p-4 md:col-span-2">
                <div class="text-slate-500">Dev</div>
                <div class="mt-1 font-semibold">{{ $contact['developer_name'] ?? 'Trương Minh Khiêm' }}</div>
                <div class="mt-2">{{ $contact['note'] ?? 'Liên hệ khi cần hỗ trợ tài khoản, mã học sinh hoặc thông tin ca thi.' }}</div>
            </div>
        </div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-emerald-700">Về trang chủ</a>
    </div>
</x-guest-layout>
