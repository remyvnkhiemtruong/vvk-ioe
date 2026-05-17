<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Cập nhật thông tin cá nhân</h1></x-slot>
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="mb-4 rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <div class="rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Học sinh chỉ tự cập nhật số điện thoại, email, địa chỉ và ghi chú. Họ tên, ngày sinh, giới tính, lớp, khối, mã học sinh và CCCD/mã định danh cần liên hệ giáo viên phụ trách.
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Họ tên" /><div class="mt-1 rounded border border-slate-200 bg-slate-50 p-2 text-sm">{{ $student->full_name }}</div></div>
                <div><x-input-label value="Lớp" /><div class="mt-1 rounded border border-slate-200 bg-slate-50 p-2 text-sm">{{ $student->class_name }}</div></div>
                <div>
                    <x-input-label for="phone" value="Số điện thoại" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $student->phone)" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $student->email)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="address" value="Địa chỉ" />
                    <textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-md border-slate-300">{{ old('address', $student->address) }}</textarea>
                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="note" value="Ghi chú" />
                    <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-md border-slate-300">{{ old('note') }}</textarea>
                </div>
            </div>
            <div class="flex justify-end"><x-primary-button>Lưu thông tin</x-primary-button></div>
        </form>
    </div>
</x-app-layout>
