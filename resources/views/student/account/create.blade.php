<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Tạo tài khoản học sinh</h1>
        <p class="mt-2 text-sm text-slate-500">Tài khoản chỉ tạo được khi thông tin khớp danh sách học sinh đã import vào hệ thống.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" enctype="multipart/form-data" x-data="accountForm()">
        @csrf

        {{-- ĐẢM BẢO THÔNG TIN CHÍNH XÁC --}}
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <p class="font-medium text-slate-700">Thông tin xác thực học sinh</p>
            <p class="mt-1">Chọn đúng lớp và nhập Mã học sinh hoặc CCCD/Mã định danh Bộ GDĐT đúng với hồ sơ.</p>
        </div>

        {{-- LỚP --}}
        <div>
            <x-input-label for="class_name" value="Lớp học *" />
            <select id="class_name" name="class_name" required
                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                <option value="">— Chọn lớp —</option>
                @foreach($classes as $class)
                    <option value="{{ $class }}" @selected(old('class_name') === $class)>{{ $class }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
        </div>

        {{-- MÃ HỌC SINH / CCCD --}}
        <div>
            <x-input-label for="credential" value="Mã học sinh hoặc CCCD / Mã định danh Bộ GDĐT *" />
            <x-text-input id="credential" name="credential" class="mt-1 block w-full"
                :value="old('credential')" required placeholder="Nhập mã học sinh hoặc số CCCD/mã định danh" />
            <p class="mt-1 text-xs text-slate-500">Nhập mã học sinh (VD: HS00123) hoặc số định danh Bộ GDĐT (12 chữ số).</p>
            <x-input-error :messages="$errors->get('credential')" class="mt-2" />
        </div>

        <hr class="border-slate-200">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Thông tin tài khoản (có thể bổ sung sau)</p>

        {{-- USERNAME --}}
        <div>
            <x-input-label for="username" value="Tên đăng nhập (Username)" />
            <x-text-input id="username" name="username" class="mt-1 block w-full"
                :value="old('username')" placeholder="Để trống sẽ tự động dùng mã học sinh"
                autocomplete="username"
                x-model="username" />
            <p class="mt-1 text-xs text-slate-500">Chỉ dùng chữ cái không dấu, số và dấu gạch dưới. Tối đa 50 ký tự.</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        {{-- SỐ ĐIỆN THOẠI --}}
        <div>
            <x-input-label for="phone" value="Số điện thoại" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full"
                :value="old('phone')" placeholder="0xxxxxxxxx (10 số)" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        {{-- EMAIL --}}
        <div>
            <x-input-label for="email" value="Email khôi phục" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                :value="old('email')" placeholder="email@example.com (không bắt buộc)" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- ẢNH ĐẠI DIỆN --}}
        <div>
            <x-input-label for="avatar" value="Ảnh đại diện" />
            <div class="mt-2 flex items-center gap-4">
                <div class="h-16 w-16 rounded-full overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                    <img id="avatar-preview" src="" alt="" class="h-full w-full object-cover hidden">
                    <svg id="avatar-placeholder" class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <label for="avatar" class="cursor-pointer inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Chọn ảnh
                    </label>
                    <input id="avatar" name="avatar" type="file" class="sr-only"
                        accept="image/jpeg,image/png,image/webp"
                        @change="previewAvatar($event)">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG hoặc WebP. Tối đa 2MB.</p>
                </div>
            </div>
            <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
        </div>

        <hr class="border-slate-200">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mật khẩu</p>

        {{-- MẬT KHẨU --}}
        <div>
            <x-input-label for="password" value="Mật khẩu *" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                required autocomplete="new-password" />
            <p class="mt-1 text-xs text-slate-500">Tối thiểu 8 ký tự, phải có cả chữ và số.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Xác nhận mật khẩu *" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('login') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                Đã có tài khoản? Đăng nhập
            </a>
            <x-primary-button>Tạo tài khoản</x-primary-button>
        </div>
    </form>

    <script>
        function accountForm() {
            return {
                username: '{{ old('username') }}',
                previewAvatar(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        document.getElementById('avatar-preview').src = e.target.result;
                        document.getElementById('avatar-preview').classList.remove('hidden');
                        document.getElementById('avatar-placeholder').classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            };
        }
    </script>
</x-guest-layout>
