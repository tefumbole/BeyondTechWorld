@php
    $code = is_object($bucket ?? null) ? $bucket->code : ($code ?? '');
    $label = is_object($bucket ?? null) ? ($bucket->label ?: $bucket->name) : ($label ?? 'Unclassified');
    $cls = 'wm-badge-none';
    if ($code === 'OPERATIONS') $cls = 'wm-badge-ops';
    if ($code === 'INVESTMENT') $cls = 'wm-badge-inv';
    if ($code === 'CHARITY') $cls = 'wm-badge-cha';
@endphp
<span class="wm-badge {{ $cls }}">{{ $label }}</span>
