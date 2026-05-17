@php
    $settings = app(\App\Services\SystemSettingService::class);
    $account = $settings->accountOptions();
@endphp

<x-guest-layout>
    <div class="space-y-5">
        <h1 class="text-2xl font-semibold">Hướng dẫn IOE nội bộ năm học {{ $settings->schoolYear() }}</h1>
        <ol class="list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li>Admin tạo kỳ thi, thời gian đăng ký, đối tượng dự thi, thang điểm và các ca/khung giờ thi.</li>
            <li>Học sinh tạo tài khoản bằng lớp và mã học sinh hoặc CCCD/mã định danh nếu nhà trường đang mở chức năng này.</li>
            <li>Học sinh tự đăng ký kỳ thi khi kỳ thi cho phép; nếu không, admin/giáo viên sẽ thêm học sinh vào danh sách nội bộ.</li>
            <li>Giám thị mở trang live để nhận mã ca thi đúng thời điểm. Hệ thống không thay thế ioe.vn.</li>
            <li>Sau khi thi trên ioe.vn, giáo viên/giám thị nhập điểm vào hệ thống để xếp hạng và xếp giải.</li>
        </ol>
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
            {{ $account['student_code_help'] ?? 'Nếu chưa biết mã học sinh, vui lòng liên hệ bộ phận hỗ trợ.' }}
            @if(! empty($account['student_code_lookup_url']))
                <a href="{{ $account['student_code_lookup_url'] }}" target="_blank" rel="noopener" class="mt-2 block font-semibold text-emerald-700">Tra cứu mã học sinh</a>
            @endif
        </div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-emerald-700">Về trang chủ</a>
    </div>
</x-guest-layout>
