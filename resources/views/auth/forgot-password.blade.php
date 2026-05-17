<x-guest-layout>
    <div class="mb-4 text-sm text-slate-700">
        Nhập email tài khoản để nhận liên kết đặt lại mật khẩu. Nếu nhà trường chưa cấu hình SMTP, hãy dùng mục yêu cầu cấp lại mật khẩu thủ công.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4 flex items-center justify-between">
            <a class="text-sm font-medium text-emerald-700" href="{{ route('password.manual-request') }}">Chưa có email SMTP?</a>
            <x-primary-button>Gửi liên kết đặt lại</x-primary-button>
        </div>
    </form>
</x-guest-layout>
