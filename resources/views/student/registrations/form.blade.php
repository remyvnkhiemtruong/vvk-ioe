<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">{{ $registration ? 'Cập nhật đăng ký IOE cấp trường' : 'Đăng ký dự thi IOE cấp trường' }}</h1>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="mb-5 rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                Vui lòng kiểm tra lại thông tin đăng ký.
            </div>
        @endif

        <form method="POST" action="{{ $registration ? route('student.registrations.update', $registration) : route('student.registrations.store', $exam) }}" class="space-y-6" x-data="{ byod: '{{ old('uses_personal_computer', $registration?->uses_personal_computer ? 1 : 0) }}', selectedSession: '{{ old('exam_session_id', $registration?->exam_session_id) }}' }">
            @csrf
            @if($registration) @method('PUT') @endif
            <input type="hidden" name="exam_id" value="{{ $exam->id }}">

            @if($exam->requiresSessionChoice())
            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Thông tin từ hồ sơ học sinh</h2>
                <p class="mt-1 text-sm text-slate-600">Họ tên, ngày sinh, giới tính, lớp, khối và CCCD/mã định danh không tự sửa tại form này.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="full_name" value="Họ và tên" />
                        <x-text-input id="full_name" name="full_name" class="mt-1 block w-full bg-slate-50" :value="old('full_name', $student?->full_name)" readonly required />
                    </div>
                    <div>
                        <x-input-label for="class_name" value="Lớp" />
                        <x-text-input id="class_name" name="class_name" class="mt-1 block w-full bg-slate-50" :value="old('class_name', $student?->class_name)" readonly required />
                    </div>
                    <div>
                        <x-input-label for="date_of_birth" value="Ngày sinh" />
                        <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full bg-slate-50" :value="old('date_of_birth', $student?->date_of_birth?->format('Y-m-d'))" readonly required />
                        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gender" value="Giới tính" />
                        <x-text-input id="gender" name="gender" class="mt-1 block w-full bg-slate-50" :value="old('gender', $student?->gender)" readonly required />
                        <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="identity_number" value="CCCD/Mã định danh" />
                        <x-text-input id="identity_number" name="identity_number" class="mt-1 block w-full bg-slate-50" :value="old('identity_number', $student?->identity_number)" readonly required />
                        <x-input-error :messages="$errors->get('identity_number')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Khối" />
                        <div class="mt-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm">{{ $student?->resolvedGrade() ?? 'Chưa cập nhật' }}</div>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-600">Nếu các thông tin cố định chưa đúng, vui lòng liên hệ giáo viên phụ trách để điều chỉnh.</p>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Thông tin đăng ký</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="ioe_id" value="ID tài khoản IOE" />
                        <x-text-input id="ioe_id" name="ioe_id" class="mt-1 block w-full" :value="old('ioe_id', $registration?->ioe_id)" required />
                        <x-input-error :messages="$errors->get('ioe_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Số điện thoại học sinh" />
                        <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $registration?->phone ?? $student?->phone)" required />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $registration?->email ?? $student?->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="note" value="Ghi chú" />
                        <x-text-input id="note" name="note" class="mt-1 block w-full" :value="old('note', $registration?->note)" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="address" value="Địa chỉ" />
                        <textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-md border-slate-300" required>{{ old('address', $registration?->address ?? $student?->address) }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Chọn ca thi mong muốn</h2>
                        <p class="mt-1 text-sm text-slate-600">Hệ thống chỉ hiển thị ca phù hợp với khối/lớp và còn chỗ.</p>
                    </div>
                    <x-input-error :messages="$errors->get('exam_session_id')" class="mt-1" />
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @forelse($availableSessions as $session)
                        @php($remaining = $session->remaining_slots ?? $session->remainingSlots($registration?->id))
                        <label class="relative cursor-pointer rounded-lg border p-4 transition hover:border-emerald-400 hover:shadow-sm" :class="selectedSession == '{{ $session->id }}' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 bg-white'">
                            <input type="radio" name="exam_session_id" value="{{ $session->id }}" x-model="selectedSession" class="sr-only" required>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold">{{ $session->name }} — {{ $session->targetLabel() }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $session->exam_date?->format('d/m/Y') }} — {{ $session->start_time }}-{{ $session->end_time }}</div>
                                    <div class="text-sm text-slate-600">{{ $session->room?->room_name ?? 'Chưa cấu hình phòng' }}</div>
                                </div>
                                <x-status-badge :status="$remaining <= 0 ? 'full' : ($remaining <= 3 ? 'pending' : 'open')" />
                            </div>
                            <div class="mt-3 text-sm font-medium text-slate-700">Còn {{ $remaining }}/{{ $session->max_candidates }} chỗ</div>
                        </label>
                    @empty
                        <div class="rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 md:col-span-2">
                            Hiện chưa có ca thi khả dụng. Vui lòng liên hệ giáo viên phụ trách.
                        </div>
                    @endforelse
                </div>
            </section>
            @else
                <input type="hidden" name="exam_session_id" value="{{ old('exam_session_id', $registration?->exam_session_id) }}">
                <section class="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Ca thi và phòng thi</h2>
                    <p class="mt-1 text-sm text-slate-600">Kỳ thi này do ban tổ chức phân ca/phòng sau khi duyệt danh sách. Học sinh không cần chọn ca khi gửi đăng ký nội bộ.</p>
                    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        Sau khi được duyệt và phân phòng, hệ thống sẽ hiển thị ca thi, phòng thi, số máy và phiếu dự thi trong trang học sinh.
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Máy tính cá nhân</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <label class="rounded border border-slate-200 p-4 text-sm" :class="byod == 0 ? 'bg-emerald-50 border-emerald-500' : 'bg-white'">
                        <input type="radio" name="uses_personal_computer" value="0" x-model="byod">
                        Không, em sử dụng máy tính của phòng Tin học.
                    </label>
                    <label class="rounded border border-slate-200 p-4 text-sm {{ $exam->allow_personal_computer ? '' : 'opacity-50' }}" :class="byod == 1 ? 'bg-emerald-50 border-emerald-500' : 'bg-white'">
                        <input type="radio" name="uses_personal_computer" value="1" x-model="byod" @disabled(! $exam->allow_personal_computer)>
                        Có, em sử dụng máy tính cá nhân.
                    </label>
                </div>
                <x-input-error :messages="$errors->get('uses_personal_computer')" class="mt-2" />

                <div x-show="byod == 1" x-transition class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="device_type" value="Loại thiết bị" />
                        <select id="device_type" name="device_type" class="mt-1 block w-full rounded-md border-slate-300">
                            @foreach(['Laptop', 'Máy tính bảng', 'Khác'] as $value)
                                <option value="{{ $value }}" @selected(old('device_type', $registration?->device_type) === $value)>{{ $value }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('device_type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="device_os" value="Hệ điều hành" />
                        <select id="device_os" name="device_os" class="mt-1 block w-full rounded-md border-slate-300">
                            @foreach(['Windows', 'macOS', 'Linux', 'Khác'] as $value)
                                <option value="{{ $value }}" @selected(old('device_os', $registration?->device_os) === $value)>{{ $value }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('device_os')" class="mt-2" />
                    </div>
                    <label class="text-sm"><input type="checkbox" name="has_charger" value="1" @checked(old('has_charger', $registration?->has_charger))> Có mang sạc</label>
                    <label class="text-sm"><input type="checkbox" name="device_commitment" value="1" @checked(old('device_commitment', $registration?->device_commitment))> Em cam kết thiết bị cá nhân hoạt động ổn định, có thể kết nối Internet và sử dụng trong suốt ca thi.</label>
                    <div class="md:col-span-2">
                        <x-input-label for="device_note" value="Ghi chú thiết bị" />
                        <textarea id="device_note" name="device_note" rows="2" class="mt-1 block w-full rounded-md border-slate-300">{{ old('device_note', $registration?->device_note) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="confirm_information" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" required>
                    <span>Em xác nhận các thông tin đăng ký là chính xác và chỉ đăng ký dự thi IOE cấp trường.</span>
                </label>
                <x-input-error :messages="$errors->get('confirm_information')" class="mt-2" />
                <div class="mt-5 flex justify-end">
                    <x-primary-button :disabled="$exam->requiresSessionChoice() && $availableSessions->isEmpty()">{{ $registration ? 'Cập nhật đăng ký' : 'Gửi đăng ký' }}</x-primary-button>
                </div>
            </section>
        </form>
    </div>
</x-app-layout>
