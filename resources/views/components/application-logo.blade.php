@php
    $setting = \Illuminate\Support\Facades\Schema::hasTable('system_settings')
        ? \App\Models\SystemSetting::where('key', 'school.logo_path')->first()?->value
        : null;
    $disk = is_array($setting) ? ($setting['disk'] ?? 'public') : 'public';
    $path = is_array($setting) ? ($setting['path'] ?? null) : null;
    $logoUrl = $path ? \Illuminate\Support\Facades\Storage::disk($disk)->url($path) : null;
@endphp

@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="Logo Trường THPT Võ Văn Kiệt" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded bg-emerald-700 text-sm font-bold text-white']) }}>VVK</span>
@endif
