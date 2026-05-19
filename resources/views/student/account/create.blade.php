<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Tạo tài khoản học sinh</h1>
        <p class="mt-2 text-sm text-slate-500">
            Tài khoản chỉ tạo được khi thông tin khớp danh sách học sinh đã import vào hệ thống.
        </p>
    </div>

    @unless($registrationEnabled)
        <div class="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <p class="font-semibold">Nhà trường đang tạm khóa chức năng tạo tài khoản học sinh.</p>
            <p>{{ $account['student_code_help'] ?? 'Vui lòng liên hệ bộ phận hỗ trợ để được cấp mã học sinh hoặc tài khoản.' }}</p>
            <a href="{{ route('student_code.lookup') }}" class="inline-flex rounded bg-amber-700 px-4 py-2 font-semibold text-white hover:bg-amber-800">Tra cứu mã học sinh</a>
            <div class="grid gap-3 rounded border border-amber-200 bg-white/70 p-3 sm:grid-cols-2">
                <div>
                    <div class="font-semibold">{{ $contact['teacher_name'] ?? 'Giáo viên phụ trách' }}</div>
                    <div>{{ $contact['teacher_email'] ?? '' }}</div>
                </div>
                <div>
                    <div class="font-semibold">{{ $contact['support_name'] ?? 'Bộ phận hỗ trợ' }}</div>
                    <div>{{ $contact['support_phone'] ?? '' }} {{ ! empty($contact['support_email']) ? '· '.$contact['support_email'] : '' }}</div>
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('register') }}" class="space-y-5" enctype="multipart/form-data" x-data="accountForm()">
            @csrf

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p class="font-medium text-slate-700">Thông tin xác thực học sinh</p>
                <p class="mt-1">Chọn đúng lớp và nhập mã học sinh hoặc CCCD/mã định danh Bộ GDĐT đúng với hồ sơ.</p>
                <a href="{{ route('student_code.lookup') }}" class="mt-2 inline-flex text-sm font-semibold text-emerald-700">Không nhớ mã học sinh, tra cứu tại đây</a>
            </div>

            <div>
                <x-input-label for="class_name" value="Lớp học *" />
                <select id="class_name" name="class_name" required
                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">— Chọn lớp —</option>
                    @foreach($classes as $class)
                        <option value="{{ $class }}" @selected(old('class_name', request('class_name')) === $class)>{{ $class }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="credential" value="Mã học sinh hoặc CCCD / Mã định danh Bộ GDĐT *" />
                <x-text-input id="credential" name="credential" class="mt-1 block w-full"
                    :value="old('credential', request('credential'))" required placeholder="Nhập mã học sinh hoặc số CCCD/mã định danh" />
                <p class="mt-1 text-xs text-slate-500">{{ $account['student_code_help'] ?? 'Nhập mã học sinh hoặc số định danh Bộ GDĐT.' }}</p>
                <x-input-error :messages="$errors->get('credential')" class="mt-2" />
            </div>

            <hr class="border-slate-200">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Thông tin tài khoản</p>

            <div>
                <x-input-label for="username" value="Tên đăng nhập (Username)" />
                <x-text-input id="username" name="username" class="mt-1 block w-full"
                    :value="old('username')" placeholder="Để trống sẽ tự động dùng mã học sinh"
                    autocomplete="username" x-model="username" />
                <p class="mt-1 text-xs text-slate-500">Chỉ dùng chữ cái không dấu, số và dấu gạch dưới. Tối đa 50 ký tự.</p>
                <x-input-error :messages="$errors->get('username')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Số điện thoại" />
                <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full"
                    :value="old('phone')" placeholder="0xxxxxxxxx" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email khôi phục" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                    :value="old('email')" placeholder="email@example.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="avatar" value="Ảnh đại diện" />
                <div class="mt-2 flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                        <img id="avatar-preview" src="" alt="" class="hidden h-full w-full object-cover">
                        <span id="avatar-placeholder" class="text-xs text-slate-400">Ảnh</span>
                    </div>
                    <div class="flex-1">
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm" @change="previewAvatar($event)">
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG hoặc WebP. Tối đa 2MB.</p>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            </div>

            <hr class="border-slate-200">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mật khẩu</p>

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
                <a href="{{ route('login') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Đã có tài khoản? Đăng nhập</a>
                <x-primary-button>Tạo tài khoản</x-primary-button>
            </div>
        </form>
    @endunless

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
