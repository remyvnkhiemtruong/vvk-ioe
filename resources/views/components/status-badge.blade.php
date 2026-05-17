@props(['status'])

@php
    $labels = [
        'draft' => 'Nháp',
        'open' => 'Đang mở',
        'closed' => 'Đã đóng',
        'assigning' => 'Đang phân phòng',
        'locked' => 'Đã khóa',
        'in_progress' => 'Đang thi',
        'completed' => 'Hoàn thành',
        'full' => 'Đã đủ chỗ',
        'submitted' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'cancelled' => 'Đã hủy',
        'pending' => 'Chờ duyệt',
        'need_check' => 'Cần kiểm tra',
        'not_applicable' => 'Không sử dụng',
        'assigned' => 'Đã phân',
        'not_checked_in' => 'Chưa check-in',
        'present' => 'Có mặt',
        'absent' => 'Vắng',
        'late' => 'Đến muộn',
        'incident' => 'Sự cố',
        'entered' => 'Đã nhập',
        'verified' => 'Đã xác nhận',
        'not_entered' => 'Chưa nhập',
        'ready' => 'Sẵn sàng',
        'in_use' => 'Đang dùng',
        'broken' => 'Hỏng',
        'maintenance' => 'Bảo trì',
        'active' => 'Đang hoạt động',
        'inactive' => 'Đã khóa',
    ];

    $tones = [
        'open' => 'bg-emerald-100 text-emerald-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'present' => 'bg-emerald-100 text-emerald-800',
        'verified' => 'bg-emerald-100 text-emerald-800',
        'ready' => 'bg-emerald-100 text-emerald-800',
        'active' => 'bg-emerald-100 text-emerald-800',
        'submitted' => 'bg-amber-100 text-amber-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'need_check' => 'bg-amber-100 text-amber-800',
        'late' => 'bg-amber-100 text-amber-800',
        'full' => 'bg-rose-100 text-rose-800',
        'locked' => 'bg-rose-100 text-rose-800',
        'rejected' => 'bg-rose-100 text-rose-800',
        'cancelled' => 'bg-rose-100 text-rose-800',
        'absent' => 'bg-rose-100 text-rose-800',
        'incident' => 'bg-rose-100 text-rose-800',
        'broken' => 'bg-rose-100 text-rose-800',
        'closed' => 'bg-slate-200 text-slate-700',
        'draft' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded px-2 py-1 text-xs font-medium '.($tones[$status] ?? 'bg-slate-100 text-slate-700')]) }}>
    {{ $labels[$status] ?? $status }}
</span>
