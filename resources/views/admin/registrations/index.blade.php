<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Danh sách đăng ký IOE cấp trường</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        @can('exports.manage')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.exports.registrations') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất đăng ký Excel</a>
                <a href="{{ route('admin.exports.byod') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất BYOD Excel</a>
                <a href="{{ route('admin.exports.byod.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất BYOD PDF</a>
            </div>
        @endcan
        <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-5">
            <input name="q" value="{{ request('q') }}" placeholder="Tìm tên, ID IOE, mã đăng ký" class="rounded-md border-slate-300">
            <input name="class_name" value="{{ request('class_name') }}" placeholder="Lớp" class="rounded-md border-slate-300">
            <select name="status" class="rounded-md border-slate-300"><option value="">Tất cả trạng thái</option>@foreach(['submitted'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối','cancelled'=>'Đã hủy'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
            <select name="session_status" class="rounded-md border-slate-300"><option value="">Tất cả ca chọn</option><option value="missing" @selected(request('session_status')==='missing')>Chưa chọn ca</option></select>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Lọc</button>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3 text-left">Họ tên</th><th class="p-3">Lớp</th><th class="p-3">ID IOE</th><th class="p-3">Ca đã chọn</th><th class="p-3">Máy cá nhân</th><th class="p-3">Trạng thái</th><th class="p-3">Thao tác</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        <tr>
                            <td class="p-3 font-medium">{{ $registration->full_name }}<div class="text-xs text-slate-500">{{ $registration->registration_code }}</div></td>
                            <td class="p-3 text-center">{{ $registration->class_name }}</td>
                            <td class="p-3 text-center">{{ $registration->ioe_id }}</td>
                            <td class="p-3 text-center">
                                {{ $registration->chosenSession?->name ?? 'Chưa chọn ca' }}
                                @if($registration->chosenSession)<div class="text-xs text-slate-500">{{ $registration->chosenSession->targetLabel() }}</div>@endif
                            </td>
                            <td class="p-3 text-center"><x-status-badge :status="$registration->personal_computer_status" /></td>
                            <td class="p-3 text-center"><x-status-badge :status="$registration->status" /></td>
                            <td class="p-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if(! in_array($registration->status, ['approved'], true))<form method="POST" action="{{ route('admin.registrations.approve', $registration) }}">@csrf<button class="rounded bg-emerald-700 px-3 py-1 text-xs font-semibold text-white">Duyệt</button></form>@endif
                                    @if(! in_array($registration->status, ['rejected','cancelled'], true))<form method="POST" action="{{ route('admin.registrations.reject', $registration) }}">@csrf<button class="rounded border px-3 py-1 text-xs font-semibold">Từ chối</button></form>@endif
                                    @if($registration->status !== 'cancelled')<form method="POST" action="{{ route('admin.registrations.cancel', $registration) }}">@csrf<button class="rounded border px-3 py-1 text-xs font-semibold">Hủy</button></form>@endif
                                    @if(in_array($registration->status, ['rejected','cancelled'], true))<form method="POST" action="{{ route('admin.registrations.restore', $registration) }}">@csrf<button class="rounded border px-3 py-1 text-xs font-semibold">Khôi phục</button></form>@endif
                                    @if($registration->uses_personal_computer)
                                        <form method="POST" action="{{ route('admin.registrations.device', $registration) }}">@csrf @method('PATCH')<input type="hidden" name="personal_computer_status" value="approved"><button class="rounded border px-3 py-1 text-xs font-semibold">Duyệt máy</button></form>
                                        <form method="POST" action="{{ route('admin.registrations.device', $registration) }}">@csrf @method('PATCH')<input type="hidden" name="personal_computer_status" value="rejected"><button class="rounded border px-3 py-1 text-xs font-semibold">Từ chối máy</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-sm text-slate-600">Chưa có học sinh nào đăng ký. Hãy kiểm tra trạng thái mở đăng ký hoặc xem Landing Page.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $registrations->links() }}
    </div>
</x-app-layout>
