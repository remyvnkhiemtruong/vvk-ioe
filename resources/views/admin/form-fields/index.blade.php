<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Cấu hình form đăng ký</h2>
                <p class="text-sm text-slate-500">Bật/tắt trường, đổi nhãn, bắt buộc và thứ tự hiển thị cho form IOE cấp trường.</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <select name="exam_id" class="rounded border-slate-300 text-sm" onchange="this.form.submit()">
                    @forelse($exams as $item)
                        <option value="{{ $item->id }}" @selected($exam?->id === $item->id)>{{ $item->name }}</option>
                    @empty
                        <option>Chưa có kỳ đăng ký</option>
                    @endforelse
                </select>
            </form>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 p-6">
        @if(session('success'))
            <div class="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if($exam)
            <form method="POST" action="{{ route('admin.form_fields.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-6">
                @csrf
                <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                <div>
                    <label class="text-xs font-semibold text-slate-600">Mã trường</label>
                    <input name="field_key" required class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="custom_note">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600">Nhãn hiển thị</label>
                    <input name="label" required class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="Ghi chú bổ sung">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600">Kiểu dữ liệu</label>
                    <select name="type" class="mt-1 w-full rounded border-slate-300 text-sm">
                        @foreach(['text','textarea','select','radio','checkbox','boolean','date','number','email'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600">Thứ tự</label>
                    <input name="sort_order" type="number" min="0" value="{{ ($fields->max('sort_order') ?? 0) + 1 }}" class="mt-1 w-full rounded border-slate-300 text-sm">
                </div>
                <div class="flex items-end gap-3">
                    <label class="inline-flex items-center gap-2 text-sm"><input name="is_enabled" value="1" type="checkbox" checked class="rounded border-slate-300"> Bật</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input name="is_required" value="1" type="checkbox" class="rounded border-slate-300"> Bắt buộc</label>
                </div>
                <div class="flex items-end">
                    <button class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Thêm trường</button>
                </div>
                <div class="md:col-span-3">
                    <label class="text-xs font-semibold text-slate-600">Gợi ý</label>
                    <input name="help_text" class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="Hiển thị dưới trường nhập nếu cần">
                </div>
                <div class="md:col-span-3">
                    <label class="text-xs font-semibold text-slate-600">Tùy chọn select/radio/checkbox, mỗi dòng một giá trị</label>
                    <textarea name="options_text" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm"></textarea>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Trường</th>
                            <th class="px-4 py-3">Kiểu</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3">Thứ tự</th>
                            <th class="px-4 py-3">Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($fields as $field)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ $field->label }}</div>
                                    <div class="text-xs text-slate-500">{{ $field->field_key }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $field->type }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-badge :status="$field->is_enabled ? 'active' : 'inactive'" />
                                        @if($field->is_required)
                                            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Bắt buộc</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ $field->sort_order }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.form_fields.update', $field) }}" class="grid gap-2 md:grid-cols-6">
                                        @csrf
                                        @method('PUT')
                                        <input name="label" value="{{ $field->label }}" class="rounded border-slate-300 text-sm md:col-span-2">
                                        <select name="type" class="rounded border-slate-300 text-sm">
                                            @foreach(['text','textarea','select','radio','checkbox','boolean','date','number','email'] as $type)
                                                <option value="{{ $type }}" @selected($field->type === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                        <input name="sort_order" type="number" min="0" value="{{ $field->sort_order }}" class="rounded border-slate-300 text-sm">
                                        <div class="flex items-center gap-3">
                                            <label class="inline-flex items-center gap-1 text-xs"><input name="is_enabled" value="1" type="checkbox" @checked($field->is_enabled) class="rounded border-slate-300"> Bật</label>
                                            <label class="inline-flex items-center gap-1 text-xs"><input name="is_required" value="1" type="checkbox" @checked($field->is_required) class="rounded border-slate-300"> Bắt buộc</label>
                                        </div>
                                        <button class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold">Lưu</button>
                                        <input name="help_text" value="{{ $field->help_text }}" class="rounded border-slate-300 text-sm md:col-span-3" placeholder="Gợi ý">
                                        <textarea name="options_text" rows="1" class="rounded border-slate-300 text-sm md:col-span-3" placeholder="Tùy chọn">{{ collect($field->options ?? [])->join("\n") }}</textarea>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                    <div class="space-y-3">
                                        <p>Chưa có trường nào cho kỳ này. Hãy thêm trường đầu tiên ở form phía trên.</p>
                                        <div class="flex flex-wrap justify-center gap-2">
                                            <a href="{{ route('admin.exams.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Kiểm tra kỳ đăng ký</a>
                                            <a href="{{ route('admin.settings.index') }}" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Cấu hình form và thời gian</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-slate-600">Chưa có kỳ đăng ký IOE cấp trường.</p>
                <a href="{{ route('admin.exams.index') }}" class="mt-4 inline-flex rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo kỳ đăng ký</a>
            </div>
        @endif
    </div>
</x-app-layout>
