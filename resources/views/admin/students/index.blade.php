<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Học sinh gốc</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.students.import') }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Import Excel</a>
            <a href="{{ route('admin.students.export') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất danh sách</a>
        </div>
        <form method="POST" action="{{ route('admin.students.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-6">
            @csrf
            <input name="full_name" required placeholder="Họ và tên" class="rounded-md border-slate-300 md:col-span-2">
            <select name="grade" required class="rounded-md border-slate-300"><option value="">Khối</option><option value="10">10</option><option value="11">11</option><option value="12">12</option></select>
            <input name="class_name" required placeholder="Lớp" class="rounded-md border-slate-300">
            <input name="student_code" placeholder="Mã HS" class="rounded-md border-slate-300">
            <input name="identity_number" placeholder="CCCD/Mã định danh" class="rounded-md border-slate-300">
            <input name="date_of_birth" type="date" class="rounded-md border-slate-300">
            <input name="gender" placeholder="Giới tính" class="rounded-md border-slate-300">
            <input name="phone" placeholder="Số điện thoại" class="rounded-md border-slate-300">
            <input name="email" type="email" placeholder="Email" class="rounded-md border-slate-300">
            <input name="address" placeholder="Địa chỉ" class="rounded-md border-slate-300 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300"><option value="active">Đang hoạt động</option><option value="inactive">Đã khóa</option></select>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Thêm học sinh</button>
        </form>
        <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-5">
            <input name="q" value="{{ request('q') }}" placeholder="Tìm tên, mã, CCCD, lớp" class="rounded-md border-slate-300">
            <select name="grade" class="rounded-md border-slate-300"><option value="">Tất cả khối</option>@foreach([10,11,12] as $grade)<option value="{{ $grade }}" @selected(request('grade') == $grade)>Khối {{ $grade }}</option>@endforeach</select>
            <select name="class_name" class="rounded-md border-slate-300"><option value="">Tất cả lớp</option>@foreach($classes as $class)<option value="{{ $class }}" @selected(request('class_name') === $class)>{{ $class }}</option>@endforeach</select>
            <select name="status" class="rounded-md border-slate-300"><option value="">Tất cả trạng thái</option><option value="active" @selected(request('status')==='active')>Đang hoạt động</option><option value="inactive" @selected(request('status')==='inactive')>Đã khóa</option></select>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Lọc</button>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3 text-left">Họ tên</th><th class="p-3">Lớp</th><th class="p-3">Mã HS</th><th class="p-3">CCCD/Mã định danh</th><th class="p-3">Liên hệ</th><th class="p-3">Trạng thái</th><th class="p-3">Thao tác</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $student)
                        <tr>
                            <td class="p-3 font-medium">{{ $student->full_name }}<div class="text-xs text-slate-500">{{ $student->date_of_birth?->format('d/m/Y') }} · {{ $student->gender }}</div></td>
                            <td class="p-3 text-center">{{ $student->class_name }}<div class="text-xs text-slate-500">Khối {{ $student->grade }}</div></td>
                            <td class="p-3 text-center">{{ $student->student_code ?? 'Chưa cập nhật' }}</td>
                            <td class="p-3 text-center">{{ auth()->user()->can('students.view_sensitive') ? ($student->identity_number ?? 'Chưa cập nhật') : ($student->maskedIdentity() ?: 'Chưa cập nhật') }}</td>
                            <td class="p-3 text-center">{{ $student->phone ?? 'Chưa cập nhật' }}<div class="text-xs text-slate-500">{{ $student->email ?? 'Chưa cập nhật' }}</div></td>
                            <td class="p-3 text-center"><x-status-badge :status="$student->status" /></td>
                            <td class="p-3 text-right">
                                <form method="POST" action="{{ route('admin.students.toggle', $student) }}">@csrf<button class="rounded border px-3 py-1 text-xs font-semibold">{{ $student->status === 'active' ? 'Khóa' : 'Mở khóa' }}</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-sm text-slate-600">Chưa có học sinh trong hệ thống. Hãy import danh sách học sinh từ Excel hoặc thêm học sinh thủ công.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    </div>
</x-app-layout>
