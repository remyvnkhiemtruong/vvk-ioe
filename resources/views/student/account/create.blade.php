<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Tạo tài khoản học sinh</h1>
        <p class="mt-2 text-sm text-slate-600">Tài khoản chỉ tạo được khi thông tin khớp danh sách học sinh đã import.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
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
            <x-input-label for="email" value="Email khôi phục nếu có" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Mật khẩu" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Xác nhận mật khẩu" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
        </div>
        <div class="flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm font-medium text-emerald-700">Đã có tài khoản?</a>
            <x-primary-button>Tạo tài khoản</x-primary-button>
        </div>
    </form>
</x-guest-layout>
