<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Sự cố phòng thi</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @can('exports.manage')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.exports.incidents.docx') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất biên bản DOCX</a>
                <a href="{{ route('admin.exports.technical_incidents') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất kỹ thuật Excel</a>
                <a href="{{ route('admin.exports.technical_incidents.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất kỹ thuật PDF</a>
            </div>
        @endcan
        <form method="POST" action="{{ route(request()->routeIs('proctor.*') ? 'proctor.incidents.store' : 'admin.incidents.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            @csrf
            <select name="seat_assignment_id" class="rounded-md border-slate-300 md:col-span-2"><option value="">Chọn thí sinh nếu có</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->registration->full_name }} · {{ $assignment->session->name }} · {{ $assignment->room->room_name }}</option>@endforeach</select>
            <select name="incident_type" required class="rounded-md border-slate-300"><option>Không đăng nhập được IOE</option><option>Sai ID IOE</option><option>Quên mật khẩu IOE</option><option>Máy tính lỗi</option><option>Mất mạng</option><option>Chuyển máy</option><option>Máy cá nhân không hoạt động</option><option>Học sinh đến muộn</option><option>Học sinh vắng</option><option>Lỗi trình duyệt</option><option>Lỗi khác</option></select>
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Ghi sự cố</button>
            <textarea name="description" required placeholder="Mô tả sự cố" class="rounded-md border-slate-300 md:col-span-2"></textarea>
            <textarea name="solution" placeholder="Cách xử lý" class="rounded-md border-slate-300 md:col-span-2"></textarea>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Thời gian</th><th class="p-3 text-left">Thí sinh</th><th class="p-3">Loại</th><th class="p-3 text-left">Mô tả</th><th class="p-3 text-left">Xử lý</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($incidents as $incident)<tr><td class="p-3">{{ $incident->reported_at?->format('d/m/Y H:i') }}</td><td class="p-3 font-medium">{{ $incident->registration?->full_name }}</td><td class="p-3 text-center">{{ $incident->incident_type }}</td><td class="p-3">{{ $incident->description }}</td><td class="p-3">{{ $incident->solution }}</td></tr>@endforeach</tbody></table>
        </div>
        {{ $incidents->links() }}
    </div>
</x-app-layout>
