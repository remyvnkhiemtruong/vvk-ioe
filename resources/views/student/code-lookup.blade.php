<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Tra cứu mã học sinh</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            Chọn đúng lớp trong danh sách năm học hiện tại, nhập họ tên và ngày sinh theo hồ sơ nhà trường.
        </p>
    </div>

    @if($status === 'found' && $result)
        @php($credential = $result->student_code ?: $result->ioe_account_id)
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900" role="alert">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Tìm thấy hồ sơ</div>
            <div class="mt-3 rounded border border-emerald-200 bg-white p-4">
                <div class="text-lg font-semibold text-slate-900">{{ $result->full_name }}</div>
                <dl class="mt-3 grid gap-2 text-slate-700 sm:grid-cols-2">
                    <div><dt class="text-slate-500">Mã dùng để tạo tài khoản</dt><dd class="font-semibold">{{ $credential ?: 'Chưa cập nhật' }}</dd></div>
                    <div><dt class="text-slate-500">Lớp</dt><dd>{{ $result->class_name }}</dd></div>
                    <div><dt class="text-slate-500">Ngày sinh</dt><dd>{{ $result->date_of_birth?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-slate-500">Định danh</dt><dd>{{ $result->maskedIdentity() ?: 'Không hiển thị' }}</dd></div>
                </dl>
                @if($credential)
                    <a href="{{ route('register', ['class_name' => $result->class_name, 'credential' => $credential]) }}" class="mt-4 inline-flex rounded-md bg-blue-700 px-4 py-2 font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                        Dùng thông tin này để tạo tài khoản
                    </a>
                @endif
            </div>
        </div>
    @elseif($status === 'multiple')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900" role="alert">
            Có nhiều hồ sơ trùng thông tin. Vui lòng nhập thêm CCCD/mã định danh hoặc liên hệ giáo viên phụ trách. Hệ thống không hiển thị danh sách chi tiết để bảo vệ dữ liệu học sinh.
        </div>
    @elseif($status === 'not_found')
        <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm leading-6 text-slate-700" role="alert">
            Không tìm thấy hồ sơ phù hợp. Vui lòng kiểm tra lại dấu tiếng Việt, lớp, ngày sinh hoặc liên hệ giáo viên phụ trách.
        </div>
    @endif

    @if($classes->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900" role="alert">
            Chưa có dữ liệu lớp. Vui lòng liên hệ quản trị viên hoặc import danh sách học sinh trước.
        </div>
    @else
        <form method="POST" action="{{ route('student_code.lookup.store') }}" class="space-y-5">
            @csrf
            <div>
                <x-input-label for="full_name" value="Họ và tên *" />
                <x-text-input id="full_name" name="full_name" class="mt-1 block w-full" :value="old('full_name')" required autocomplete="name" />
                <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="class_name" value="Lớp *" />
                <select id="class_name" name="class_name" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Chọn lớp --</option>
                    @foreach($classes as $class)
                        <option value="{{ $class }}" @selected(old('class_name') === $class)>{{ $class }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="date_of_birth" value="Ngày sinh *" />
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth')" required />
                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="identity_number" value="CCCD / mã định danh nếu có" />
                <x-text-input id="identity_number" name="identity_number" class="mt-1 block w-full" :value="old('identity_number')" autocomplete="off" />
                <p class="mt-1 text-xs text-slate-500">Thông tin này chỉ dùng để phân biệt hồ sơ trùng, không hiển thị công khai.</p>
                <x-input-error :messages="$errors->get('identity_number')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('register') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Quay về tạo tài khoản</a>
                <x-primary-button>Tra cứu</x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
