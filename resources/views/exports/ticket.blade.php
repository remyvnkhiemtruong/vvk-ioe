@php
    $setting = \Illuminate\Support\Facades\Schema::hasTable('system_settings')
        ? \App\Models\SystemSetting::where('key', 'school.logo_path')->first()?->value
        : null;
    $disk = is_array($setting) ? ($setting['disk'] ?? 'public') : 'public';
    $path = is_array($setting) ? ($setting['path'] ?? null) : null;
    $logoData = null;
    $session = $registration->chosenSession ?: $registration->seatAssignment?->session;

    if ($path && \Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';
        $logoData = 'data:'.$mime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk($disk)->get($path));
    }
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:DejaVu Sans,sans-serif;font-size:13px}
        .box{border:1px solid #111;padding:18px}
        .logo{text-align:center;margin-bottom:8px}
        .logo img{height:64px;width:64px;object-fit:contain}
        .title{text-align:center;font-weight:bold;font-size:18px}
        td{padding:6px 8px}
    </style>
</head>
<body>
<div class="box">
    @if($logoData)
        <div class="logo"><img src="{{ $logoData }}" alt="Logo"></div>
    @endif
    <div class="title">TRƯỜNG THPT VÕ VĂN KIỆT<br>PHIẾU DỰ THI IOE CẤP TRƯỜNG NĂM HỌC 2025-2026</div>
    <table width="100%" style="margin-top:20px">
        <tr><td>Họ tên</td><td><strong>{{ $registration->full_name }}</strong></td></tr>
        <tr><td>Lớp</td><td>{{ $registration->class_name }}</td></tr>
        <tr><td>ID IOE</td><td>{{ $registration->ioe_id }}</td></tr>
        <tr><td>Mã đăng ký</td><td>{{ $registration->registration_code }}</td></tr>
        <tr><td>Số báo danh</td><td>{{ $registration->seatAssignment?->candidate_number ?? 'Chưa có' }}</td></tr>
        <tr><td>Ca thi</td><td>{{ $session?->name ?? 'Chưa chọn ca' }}</td></tr>
        <tr><td>Phòng thi</td><td>{{ $registration->seatAssignment?->room?->room_name ?? $session?->room?->room_name ?? 'Chưa phân phòng' }}</td></tr>
        <tr><td>Số máy</td><td>{{ $registration->seatAssignment?->seat_type === 'personal_computer' ? 'Máy cá nhân/BYOD' : ($registration->seatAssignment?->computer?->computer_label ?? 'Chưa phân máy') }}</td></tr>
        <tr><td>Thời gian thi</td><td>{{ $session?->exam_date?->format('d/m/Y') }} {{ $session?->start_time }}-{{ $session?->end_time }}</td></tr>
        <tr><td>Ghi chú</td><td>{{ $registration->note }}</td></tr>
    </table>
</div>
</body>
</html>
