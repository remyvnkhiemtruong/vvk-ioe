<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="login" value="Email hoặc tên đăng nhập" />
            <x-text-input id="login" class="mt-1 block w-full" type="text" name="login" :value="old('login')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Mật khẩu" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 block">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Ghi nhớ đăng nhập</span>
            </label>
        </div>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="flex flex-col gap-1">
                <a class="text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('password.request') }}">Quên mật khẩu?</a>
                <a class="text-xs font-medium text-emerald-700" href="{{ route('password.manual-request') }}">Yêu cầu cấp lại thủ công</a>
            </div>
            <x-primary-button>Đăng nhập</x-primary-button>
        </div>
    </form>
</x-guest-layout>
