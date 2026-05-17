<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Quản lý nhân sự & Tài khoản giáo viên</h1>
            <div class="flex gap-2">
                {{-- Tạo hàng loạt --}}
                <form method="POST" action="{{ route('admin.staff.account.bulk') }}" class="flex items-center gap-2">
                    @csrf
                    <select name="role" class="rounded-md border-slate-300 text-sm">
                        <option value="teacher">Giáo viên</option>
                        <option value="proctor">Giám thị</option>
                    </select>
                    <select name="filter" class="rounded-md border-slate-300 text-sm">
                        <option value="suggested">GV/GT được gợi ý (chưa có TK)</option>
                        <option value="all">Tất cả chưa có TK</option>
                    </select>
                    <button onclick="return confirm('Tạo tài khoản hàng loạt? Mật khẩu mặc định = ngày sinh DDMMYYYY hoặc Vvk@12345.')"
                        class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Tạo hàng loạt
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6 lg:px-8">

        @if(session('status'))
            <div class="rounded bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if(session('bulk_errors') && count(session('bulk_errors')) > 0)
            <div class="rounded bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                <p class="font-medium mb-1">Một số mục bị bỏ qua:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach(array_slice(session('bulk_errors'), 0, 10) as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        {{-- Bộ lọc --}}
        <form method="GET" class="flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
            <select name="role" class="rounded-md border-slate-300 text-sm">
                <option value="">Tất cả vai trò gợi ý</option>
                <option value="teacher" @selected($roleFilter === 'teacher')>Giáo viên</option>
                <option value="proctor" @selected($roleFilter === 'proctor')>Giám thị</option>
            </select>
            <select name="has_account" class="rounded-md border-slate-300 text-sm">
                <option value="">Tất cả</option>
                <option value="0" @selected($hasAccountFilter === '0')>Chưa có tài khoản</option>
                <option value="1" @selected($hasAccountFilter === '1')>Đã có tài khoản</option>
            </select>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Lọc</button>
            <a href="{{ route('admin.staff.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm">Xóa lọc</a>
        </form>

        {{-- Bảng nhân sự --}}
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3 text-left">Họ và tên</th>
                        <th class="p-3 text-left">Môn dạy / Chức vụ</th>
                        <th class="p-3 text-center">Gợi ý role</th>
                        <th class="p-3 text-center">Tài khoản</th>
                        <th class="p-3 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($staff as $person)
                        <tr class="{{ $person->user_id ? '' : 'bg-amber-50/30' }}">
                            <td class="p-3">
                                <div class="font-medium text-slate-800">{{ $person->full_name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $person->staff_code ?? '' }}
                                    @if($person->date_of_birth)
                                        · {{ $person->date_of_birth->format('d/m/Y') }}
                                    @endif
                                    @if($person->gender) · {{ $person->gender }} @endif
                                </div>
                            </td>
                            <td class="p-3">
                                <div>{{ $person->subject ?? $person->staff_type ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $person->position_group ?? $person->contract_type ?? '' }}</div>
                            </td>
                            <td class="p-3 text-center">
                                @if($person->suggested_role === 'teacher')
                                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Giáo viên</span>
                                @elseif($person->suggested_role === 'proctor')
                                    <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">Giám thị</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Chưa phân loại</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($person->user)
                                    <div class="font-medium text-emerald-700">{{ $person->user->username }}</div>
                                    <div class="text-xs text-slate-500">{{ $person->user->role }}</div>
                                @else
                                    <span class="text-slate-400 text-xs">Chưa có</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                @if(! $person->user_id)
                                    {{-- Form tạo tài khoản --}}
                                    <details class="relative inline-block text-left">
                                        <summary class="cursor-pointer rounded bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 list-none">
                                            + Tạo TK
                                        </summary>
                                        <div class="absolute right-0 z-10 mt-1 w-72 rounded-lg border border-slate-200 bg-white shadow-lg p-3 space-y-2">
                                            <form method="POST" action="{{ route('admin.staff.account.store', $person) }}">
                                                @csrf
                                                <div>
                                                    <label class="block text-xs text-slate-600 mb-1">Role</label>
                                                    <select name="role" class="w-full rounded border-slate-300 text-sm">
                                                        <option value="teacher" @selected($person->suggested_role === 'teacher')>Giáo viên (teacher)</option>
                                                        <option value="exam_admin">Quản lý thi (exam_admin)</option>
                                                        <option value="proctor" @selected($person->suggested_role === 'proctor')>Giám thị (proctor)</option>
                                                        <option value="viewer">Xem (viewer)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-slate-600 mb-1">Username (để trống = tự động)</label>
                                                    <input name="username" type="text" placeholder="Tự động từ mã CB / tên"
                                                        class="w-full rounded border-slate-300 text-sm">
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-slate-600 mb-1">Email (tùy chọn)</label>
                                                    <input name="email" type="email" placeholder="email@vvk.edu.vn"
                                                        class="w-full rounded border-slate-300 text-sm">
                                                </div>
                                                <p class="text-xs text-slate-500">
                                                    Mật khẩu mặc định:
                                                    <strong>{{ $person->date_of_birth ? $person->date_of_birth->format('dmY') : 'Vvk@12345' }}</strong>
                                                </p>
                                                <button class="w-full rounded bg-emerald-700 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                                                    Xác nhận tạo tài khoản
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                @else
                                    <form method="POST" action="{{ route('admin.staff.account.unlink', $person) }}"
                                        onsubmit="return confirm('Gỡ liên kết tài khoản {{ $person->user->username }} khỏi {{ $person->full_name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                                            Gỡ liên kết
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-sm text-slate-500">
                                Chưa có nhân sự trong hệ thống. Vui lòng import danh sách cán bộ từ Excel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $staff->links() }}

        {{-- Ghi chú phân quyền --}}
        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
            <p class="font-semibold mb-2">Phân quyền hệ thống</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                <div><span class="font-medium">super_admin / admin:</span> BGH – toàn quyền</div>
                <div><span class="font-medium">exam_admin:</span> Giáo viên quản lý thi – tạo kỳ thi, ca thi, duyệt đăng ký</div>
                <div><span class="font-medium">teacher:</span> Giáo viên thường – tạo ca thi của mình, chấm điểm, giám sát</div>
                <div><span class="font-medium">proctor:</span> Giám thị – checkin, sự cố, nhập điểm</div>
                <div><span class="font-medium">student:</span> Học sinh – đăng ký dự thi</div>
            </div>
        </div>
    </div>
</x-app-layout>
