<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Reset & nhập dữ liệu học sinh</h1></x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-lg border border-rose-200 bg-white p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-rose-700">Thao tác nguy hiểm</p>
                    <h2 class="mt-1 text-lg font-semibold">Clear dữ liệu nghiệp vụ cũ rồi import học sinh mới</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Hệ thống giữ nguyên tài khoản admin/giáo viên/giám thị, roles, permissions, settings, kỳ thi, ca thi, phòng máy và cấu hình. Dữ liệu học sinh, đăng ký, điểm, xếp hạng, xếp giải, check-in, live/code và các import batch cũ sẽ được xóa khi commit.
                    </p>
                </div>
                <a href="{{ route('admin.students.import') }}" class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Import thường</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(array_slice($clearCounts, 0, 8, true) as $table => $count)
                    <div class="rounded border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs uppercase text-slate-500">{{ $table }}</div>
                        <div class="mt-1 text-2xl font-semibold">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('admin.students.reset_import.preview') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-[1fr_180px]">
                <div>
                    <x-input-label for="file" value="File Excel danh sách học sinh" />
                    <input id="file" type="file" name="file" required class="mt-2 block w-full rounded border border-slate-300 p-2 text-sm">
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="school_year" value="Năm học" />
                    <x-text-input id="school_year" name="school_year" class="mt-2 block w-full" value="{{ old('school_year', '2025-2026') }}" required />
                </div>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="reset_award_rules" value="1" class="rounded border-slate-300 text-rose-700">
                Xóa cả rule xếp giải hiện có
            </label>
            <x-primary-button class="mt-4">Preview dữ liệu</x-primary-button>
        </form>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Các lần reset/import gần đây</h2>
            <div class="mt-3 space-y-2 text-sm">
                @forelse($batches as $batch)
                    <a class="block rounded border border-slate-200 p-3 hover:bg-slate-50" href="{{ route('admin.students.reset_import.show', $batch) }}">
                        {{ $batch->file_name }} · {{ $batch->valid_rows }}/{{ $batch->total_rows }} dòng hợp lệ · {{ $batch->status }}
                    </a>
                @empty
                    <div class="rounded border border-dashed border-slate-300 p-4 text-slate-500">Chưa có lần reset/import nào.</div>
                @endforelse
            </div>
            <div class="mt-4">{{ $batches->links() }}</div>
        </section>
    </div>
</x-app-layout>
