<x-guest-layout>
    <div class="mb-4 text-sm text-slate-700">
        Nếu nhà trường chưa cấu hình SMTP, học sinh gửi yêu cầu tại đây để giáo viên/Admin xác minh và cấp mật khẩu tạm thời.
    </div>

    @if(session('status'))
        <div class="mb-4 rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.manual-request.store') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="class_name" value="Lớp" />
            <select id="class_name" name="class_name" required class="mt-1 block w-full rounded-md border-slate-300">
                <option value="">Chọn lớp</option>
                @foreach($classes as $class)
                    <option value="{{ $class }}" @selected(old('class_name') === $class)>{{ $class }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="credential" value="Mã học sinh hoặc CCCD/mã định danh" />
            <x-text-input id="credential" name="credential" class="mt-1 block w-full" :value="old('credential')" required />
            <x-input-error :messages="$errors->get('credential')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="request_note" value="Ghi chú xác minh" />
            <textarea id="request_note" name="request_note" rows="3" class="mt-1 block w-full rounded-md border-slate-300">{{ old('request_note') }}</textarea>
            <x-input-error :messages="$errors->get('request_note')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm font-medium text-emerald-700">Quay lại đăng nhập</a>
            <x-primary-button>Gửi yêu cầu</x-primary-button>
        </div>
    </form>
</x-guest-layout>
