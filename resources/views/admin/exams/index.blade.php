<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">Kỳ thi IOE nội bộ</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.exams.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            @csrf
            <input name="name" placeholder="Tên kỳ thi nội bộ" required class="rounded-md border-slate-300 md:col-span-2">
            <input name="code" placeholder="Mã kỳ thi, ví dụ ioe_2026_2027_school" class="rounded-md border-slate-300 md:col-span-2">

            <select name="academic_year_id" class="rounded-md border-slate-300">
                <option value="">Chọn năm học</option>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name ?? $year->code }}</option>
                @endforeach
            </select>
            <input name="school_year" value="{{ $academicYears->firstWhere('is_active', true)?->code ?? '2026-2027' }}" required class="rounded-md border-slate-300">
            <select name="exam_level_id" class="rounded-md border-slate-300">
                <option value="">Chọn cấp thi</option>
                @foreach($examLevels as $level)
                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border-slate-300">
                @foreach([
                    'draft' => 'Nháp',
                    'preparing' => 'Đang chuẩn bị',
                    'student_list_ready' => 'Sẵn sàng danh sách',
                    'live_ready' => 'Sẵn sàng live',
                    'running' => 'Đang thi',
                    'score_entering' => 'Đang nhập điểm',
                    'ranked' => 'Đã xếp giải',
                    'archived' => 'Lưu trữ',
                    'completed' => 'Hoàn thành',
                ] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <input type="datetime-local" name="registration_opens_at" class="rounded-md border-slate-300">
            <input type="datetime-local" name="registration_closes_at" class="rounded-md border-slate-300">
            <input type="date" name="exam_date" class="rounded-md border-slate-300">
            <input type="time" name="exam_time" class="rounded-md border-slate-300">

            <input name="timezone" value="Asia/Ho_Chi_Minh" class="rounded-md border-slate-300">
            <input name="source" value="admin_configured" class="rounded-md border-slate-300">
            <input name="max_score" type="number" min="1" value="1000" placeholder="Thang điểm" class="rounded-md border-slate-300">
            <input name="award_min_score_percent" type="number" min="0" max="100" value="50" placeholder="Ngưỡng điểm giải (%)" class="rounded-md border-slate-300">
            <input name="award_top_percent" type="number" min="1" max="100" value="50" placeholder="Top đạt giải (%)" class="rounded-md border-slate-300">
            <select name="registration_mode" class="rounded-md border-slate-300">
                <option value="admin_assign_session">Admin gán ca/khung giờ</option>
                <option value="student_select_session">Học sinh chọn ca legacy</option>
            </select>
            <select name="countdown_mode" class="rounded-md border-slate-300">
                <option value="auto">Countdown tự động</option>
                <option value="exam">Đến giờ thi</option>
                <option value="open">Đến mở đăng ký</option>
                <option value="close">Đến đóng đăng ký</option>
            </select>

            <div class="md:col-span-4">
                <div class="mb-2 text-sm font-semibold text-slate-700">Khối áp dụng</div>
                <div class="grid gap-2 sm:grid-cols-6 md:grid-cols-12">
                    @for($grade = 1; $grade <= 12; $grade++)
                        <label class="inline-flex items-center gap-2 rounded border border-slate-200 px-2 py-1 text-sm">
                            <input type="checkbox" name="target_grades[]" value="{{ $grade }}" @checked($grade >= 10)>
                            {{ $grade }}
                        </label>
                    @endfor
                </div>
            </div>

            <label class="text-sm"><input type="hidden" name="allow_student_edit" value="0"><input type="checkbox" name="allow_student_edit" value="1"> Cho học sinh sửa legacy</label>
            <label class="text-sm"><input type="hidden" name="allow_student_session_change" value="0"><input type="checkbox" name="allow_student_session_change" value="1"> Cho đổi ca legacy</label>
            <label class="text-sm"><input type="hidden" name="allow_personal_computer" value="0"><input type="checkbox" name="allow_personal_computer" value="1"> Theo dõi BYOD legacy</label>
            <label class="text-sm"><input type="hidden" name="publish_scores" value="0"><input type="checkbox" name="publish_scores" value="1"> Công bố điểm legacy</label>
            <input type="hidden" name="auto_lock_full_sessions" value="0">
            <input type="hidden" name="show_public_stats" value="0">
            <input type="hidden" name="require_approval" value="0">
            <input type="hidden" name="show_countdown" value="1">

            <textarea name="description" placeholder="Mô tả, ghi chú nghiệp vụ" class="rounded-md border-slate-300 md:col-span-3"></textarea>
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo kỳ thi</button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3 text-left">Kỳ thi</th>
                        <th class="p-3 text-left">Cấp thi</th>
                        <th class="p-3">Năm học</th>
                        <th class="p-3">Ca</th>
                        <th class="p-3">HS nội bộ</th>
                        <th class="p-3">Điểm</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3 text-right">Workflow</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exams as $exam)
                        <tr>
                            <td class="p-3">
                                <div class="font-medium">{{ $exam->name }}</div>
                                <div class="text-xs text-slate-500">{{ $exam->code ?? 'Chưa có mã' }}</div>
                            </td>
                            <td class="p-3">{{ $exam->examLevel?->name ?? $exam->level }}</td>
                            <td class="p-3 text-center">{{ $exam->academicYear?->code ?? $exam->school_year }}</td>
                            <td class="p-3 text-center">{{ $exam->sessions_count }}</td>
                            <td class="p-3 text-center">{{ $exam->exam_students_count }}</td>
                            <td class="p-3 text-center">{{ $exam->student_scores_count }}</td>
                            <td class="p-3 text-center"><x-status-badge :status="$exam->status" /></td>
                            <td class="p-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.exam-students.index', $exam) }}" class="rounded border px-2 py-1 text-xs font-semibold">Học sinh</a>
                                    <a href="{{ route('admin.exam-codes.index', $exam) }}" class="rounded border px-2 py-1 text-xs font-semibold">Mã ca</a>
                                    <a href="{{ route('admin.live-screens.index', $exam) }}" class="rounded border px-2 py-1 text-xs font-semibold">Live</a>
                                    <a href="{{ route('admin.score-entry.index', $exam) }}" class="rounded border px-2 py-1 text-xs font-semibold">Điểm</a>
                                    <a href="{{ route('admin.exam.rankings.index', $exam) }}" class="rounded border px-2 py-1 text-xs font-semibold">Xếp giải</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-6 text-center text-sm text-slate-600">
                                Chưa có kỳ thi nội bộ. Hãy tạo kỳ thi đầu tiên hoặc chạy seed lịch sử 2025-2026.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $exams->links() }}
    </div>
</x-app-layout>
