@props(['label', 'value', 'tone' => 'emerald', 'href' => null])

@php
    $tones = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
        'slate' => 'border-slate-200 bg-white text-slate-900',
    ];
    $classes = 'block rounded-lg border p-4 transition hover:-translate-y-0.5 hover:shadow-sm motion-reduce:transform-none '.($tones[$tone] ?? $tones['slate']);
@endphp

@if($href)
    <a href="{{ $href }}" class="{{ $classes }}">
        <div class="text-sm text-slate-600">{{ $label }}</div>
        <div class="mt-2 text-2xl font-semibold" data-count-up>{{ $value }}</div>
    </a>
@else
    <div class="{{ $classes }}">
        <div class="text-sm text-slate-600">{{ $label }}</div>
        <div class="mt-2 text-2xl font-semibold" data-count-up>{{ $value }}</div>
    </div>
@endif
