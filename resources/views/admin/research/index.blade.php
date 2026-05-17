<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Nghiên cứu cuộc thi IOE</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Khu vực này chỉ để lưu văn bản, lịch thi, điều kiện, checklist và kết quả tham khảo. Học sinh không đăng ký cấp tỉnh/cấp quốc gia tại website.</div>
        <form method="POST" action="{{ route('admin.research.documents.store') }}" enctype="multipart/form-data" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            @csrf
            <input name="title" placeholder="Tên văn bản" required class="rounded-md border-slate-300 md:col-span-2">
            <select name="level" class="rounded-md border-slate-300"><option value="school">Cấp trường</option><option value="provincial">Cấp tỉnh</option><option value="national">Cấp quốc gia</option><option value="general">Chung</option></select>
            <input name="school_year" placeholder="Năm học" class="rounded-md border-slate-300">
            <input name="issued_date" type="date" class="rounded-md border-slate-300">
            <input name="source_url" type="url" placeholder="Link nguồn" class="rounded-md border-slate-300 md:col-span-2">
            <input name="file" type="file" class="rounded-md border border-slate-300 p-2 text-sm">
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Lưu văn bản</button>
        </form>
        <form method="POST" action="{{ route('admin.research.checklists.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            @csrf
            <input name="title" placeholder="Checklist tổ chức" required class="rounded-md border-slate-300 md:col-span-2">
            <select name="level" class="rounded-md border-slate-300"><option value="school">Cấp trường</option><option value="provincial">Chuẩn bị cấp tỉnh</option><option value="national">Chuẩn bị cấp quốc gia</option></select>
            <input name="due_date" type="date" class="rounded-md border-slate-300">
            <textarea name="description" placeholder="Mô tả" class="rounded-md border-slate-300 md:col-span-3"></textarea>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Thêm checklist</button>
        </form>
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-4"><h2 class="font-semibold">Thư viện văn bản IOE</h2><div class="mt-3 space-y-2 text-sm">@foreach($documents as $doc)<div class="rounded border border-slate-200 p-3"><div class="font-medium">{{ $doc->title }}</div><div class="text-slate-500">{{ $doc->level }} · {{ $doc->school_year }} · {{ $doc->issued_date?->format('d/m/Y') }}</div></div>@endforeach</div>{{ $documents->links() }}</section>
            <section class="rounded-lg border border-slate-200 bg-white p-4"><h2 class="font-semibold">Checklist tổ chức</h2><div class="mt-3 space-y-2 text-sm">@foreach($checklists as $item)<form method="POST" action="{{ route('admin.research.checklists.toggle', $item) }}" class="rounded border border-slate-200 p-3">@csrf @method('PATCH')<button class="font-medium {{ $item->is_completed ? 'line-through text-slate-500' : '' }}">{{ $item->title }}</button><div class="text-slate-500">{{ $item->level }} · hạn {{ $item->due_date?->format('d/m/Y') ?? '-' }}</div></form>@endforeach</div>{{ $checklists->links() }}</section>
        </div>
    </div>
</x-app-layout>
