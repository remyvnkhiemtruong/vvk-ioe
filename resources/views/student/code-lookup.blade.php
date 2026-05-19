<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Tra cứu mã học sinh</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Nhập đúng họ tên, lớp và ngày sinh theo hồ sơ nhà trường.
        </p>
    </div>

    @if($status === 'found' && $result)
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Tìm thấy hồ sơ</div>
            <div class="mt-3 rounded border border-emerald-200 bg-white p-4">
                <div class="text-lg font-semibold text-slate-900">{{ $result->full_name }}</div>
                <dl class="mt-3 grid gap-2 text-slate-700 sm:grid-cols-2">
                    <div><dt class="text-slate-500">Mã học sinh</dt><dd class="font-semibold">{{ $result->student_code ?: 'Chưa cập nhật' }}</dd></div>
                    <div><dt class="text-slate-500">Lớp</dt><dd>{{ $result->class_name }}</dd></div>
                    <div><dt class="text-slate-500">Ngày sinh</dt><dd>{{ $result->date_of_birth?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-slate-500">Mã định danh</dt><dd>{{ $result->maskedIdentity() ?: 'Không hiển thị' }}</dd></div>
                </dl>
                @if($result->student_code)
                    <a href="{{ route('register', ['class_name' => $result->class_name, 'credential' => $result->student_code]) }}" class="mt-4 inline-flex rounded bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">
                        Dùng mã này để tạo tài khoản
                    </a>
                @endif
            </div>
        </div>
    @elseif($status === 'multiple')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
            Có nhiều hồ sơ trùng thông tin. Vui lòng nhập thêm CCCD/mã định danh hoặc liên hệ giáo viên phụ trách để được hỗ trợ.
        </div>
    @elseif($status === 'not_found')
        <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm leading-6 text-slate-700">
            Không tìm thấy hồ sơ phù hợp. Vui lòng kiểm tra lại dấu tiếng Việt, lớp, ngày sinh hoặc liên hệ giáo viên phụ trách.
        </div>
    @endif

    <form method="POST" action="{{ route('student_code.lookup.store') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="full_name" value="Họ và tên *" />
            <x-text-input id="full_name" name="full_name" class="mt-1 block w-full" :value="old('full_name')" required />
            <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="class_name" value="Lớp *" />
            @if($classes->isNotEmpty())
                <select id="class_name" name="class_name" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Chọn lớp</option>
                    @foreach($classes as $class)
                        <option value="{{ $class }}" @selected(old('class_name') === $class)>{{ $class }}</option>
                    @endforeach
                </select>
            @else
                <x-text-input id="class_name" name="class_name" class="mt-1 block w-full" :value="old('class_name')" required />
            @endif
            <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="date_of_birth" value="Ngày sinh *" />
            <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth')" required />
            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="identity_number" value="CCCD / mã định danh nếu có" />
            <x-text-input id="identity_number" name="identity_number" class="mt-1 block w-full" :value="old('identity_number')" />
            <x-input-error :messages="$errors->get('identity_number')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('register') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Quay về tạo tài khoản</a>
            <x-primary-button>Tra cứu</x-primary-button>
        </div>
    </form>
</x-guest-layout>
