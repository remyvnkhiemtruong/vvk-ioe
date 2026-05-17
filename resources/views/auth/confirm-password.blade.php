<x-guest-layout>
    <div class="mb-4 text-sm text-slate-700">
        Đây là khu vực bảo vệ. Vui lòng xác nhận mật khẩu trước khi tiếp tục.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div>
            <x-input-label for="password" value="Mật khẩu" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 flex justify-end">
            <x-primary-button>Xác nhận</x-primary-button>
        </div>
    </form>
</x-guest-layout>
