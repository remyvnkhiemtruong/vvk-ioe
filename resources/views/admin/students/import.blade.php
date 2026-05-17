<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Import danh sách học sinh</h1></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.students.import.preview') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <x-input-label for="file" value="File Excel học sinh" />
            <input id="file" type="file" name="file" required class="mt-2 block w-full rounded border border-slate-300 p-2 text-sm">
            <x-input-error :messages="$errors->get('file')" class="mt-2" />
            <x-primary-button class="mt-4">Xem trước import</x-primary-button>
        </form>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Các lần import gần đây</h2>
            <div class="mt-3 space-y-2 text-sm">
                @foreach($batches as $batch)
                    <a class="block rounded border border-slate-200 p-3 hover:bg-slate-50" href="{{ route('admin.students.import.show', $batch) }}">{{ $batch->file_name }} · {{ $batch->valid_rows }}/{{ $batch->total_rows }} dòng hợp lệ · {{ $batch->status }}</a>
                @endforeach
            </div>
            {{ $batches->links() }}
        </div>
    </div>
</x-app-layout>
