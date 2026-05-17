<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">Danh sách học sinh nội bộ - {{ $exam->name }}</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @foreach(['success' => 'emerald', 'warning' => 'amber', 'error' => 'rose'] as $key => $tone)
            @if(session($key))
                <div class="rounded border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-3 text-sm text-{{ $tone }}-800">{{ session($key) }}</div>
            @endif
        @endforeach

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Thêm học sinh vào kỳ thi</h2>
            <form method="POST" action="{{ route('admin.exam-students.store', $exam) }}" class="mt-4 grid gap-3 md:grid-cols-6">
                @csrf
                <select name="student_id" class="md:col-span-2 rounded border-slate-300">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->full_name }} - {{ $student->class_name }} - {{ $student->student_code }}</option>
                    @endforeach
                </select>
                <input name="grade_number" type="number" min="1" max="12" placeholder="Khối" class="rounded border-slate-300">
                <input name="ioe_account_id" placeholder="ID IOE" class="rounded border-slate-300">
                <input name="self_training_round" type="number" min="0" placeholder="Vòng tự luyện" class="rounded border-slate-300">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ioe_account_verified" value="1" class="rounded border-slate-300"> Đã xác thực</label>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white md:col-span-6">Thêm và kiểm tra điều kiện</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Danh sách nội bộ</h2>
                <form method="POST" action="{{ route('admin.exam-students.check_all', $exam) }}">
                    @csrf
                    <button class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold">Kiểm tra điều kiện tất cả</button>
                </form>
            </div>

            <form method="GET" class="mt-4 flex flex-wrap gap-2">
                <input name="search" value="{{ request('search') }}" placeholder="Tìm học sinh" class="rounded border-slate-300">
                <input name="grade" value="{{ request('grade') }}" placeholder="Khối" class="w-24 rounded border-slate-300">
                <select name="eligibility" class="rounded border-slate-300">
                    <option value="">Tất cả điều kiện</option>
                    <option value="eligible" @selected(request('eligibility') === 'eligible')>Đủ điều kiện</option>
                    <option value="ineligible" @selected(request('eligibility') === 'ineligible')>Thiếu điều kiện</option>
                </select>
                <button class="rounded bg-slate-800 px-3 py-2 text-sm font-semibold text-white">Lọc</button>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Học sinh</th>
                        <th class="px-3 py-2">IOE</th>
                        <th class="px-3 py-2">Điều kiện</th>
                        <th class="px-3 py-2">Trạng thái</th>
                        <th class="px-3 py-2">Khung giờ</th>
                        <th class="px-3 py-2">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($examStudents as $item)
                        <tr>
                            <td class="px-3 py-3">
                                <div class="font-semibold">{{ $item->student?->full_name }}</div>
                                <div class="text-xs text-slate-500">Khối {{ $item->grade_number }} - {{ $item->class_name }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div>{{ $item->ioe_account_id ?: $item->student?->ioe_account_id ?: '-' }}</div>
                                <div class="text-xs {{ $item->ioe_account_verified ? 'text-emerald-700' : 'text-amber-700' }}">{{ $item->ioe_account_verified ? 'Đã xác thực' : 'Chưa xác thực' }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <x-status-badge :status="$item->eligibility_status ?? 'pending'" />
                                @if($item->ineligible_reasons)
                                    <div class="mt-1 max-w-xs text-xs text-rose-700">{{ implode('; ', $item->ineligible_reasons) }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3"><x-status-badge :status="$item->status" /></td>
                            <td class="px-3 py-3">{{ $item->assignedTimeSlot?->name ?? '-' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.exam-students.check', [$exam, $item]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Kiểm tra</button></form>
                                    <form method="POST" action="{{ route('admin.exam-students.mark_ioe', [$exam, $item]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Đã đăng ký IOE</button></form>
                                    <form method="POST" action="{{ route('admin.exam-students.update', [$exam, $item]) }}" class="flex gap-1">
                                        @csrf @method('PATCH')
                                        <select name="assigned_time_slot_id" class="w-44 rounded border-slate-300 text-xs">
                                            <option value="">Gán khung giờ</option>
                                            @foreach($timeSlots as $slot)
                                                <option value="{{ $slot->id }}" @selected($item->assigned_time_slot_id === $slot->id)>{{ $slot->starts_at?->format('d/m H:i') }} - {{ $slot->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded bg-slate-800 px-2 py-1 text-xs text-white">Lưu</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Chưa có học sinh trong danh sách nội bộ.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $examStudents->links() }}</div>
        </section>
    </div>
</x-app-layout>
