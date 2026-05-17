<x-guest-layout>
    <div class="mb-4 text-sm text-slate-700">
        Vui lòng xác minh email bằng liên kết hệ thống đã gửi. Nếu chưa nhận được email, bạn có thể gửi lại liên kết xác minh.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-emerald-700">
            Liên kết xác minh mới đã được gửi tới email của bạn.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Gửi lại email xác minh</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                Đăng xuất
            </button>
        </form>
    </div>
</x-guest-layout>
