@php
    $paths = [
        'bus' => '<rect x="4" y="3" width="16" height="16" rx="4"/><path d="M4 12h16M8 16h.01M16 16h.01M7 19v2m10-2v2"/>',
        'ticket' => '<path d="M3 7h18v3a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4zM15 8v2m0 4v2"/>',
        'track' => '<path d="M19 10c0 5-7 10-7 10S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
        'staff' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-15a3 3 0 0 1 0 6m0 3a5 5 0 0 1 4 5v1"/>',
        'box' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 9 8-4.5M12 12 4 7.5m8 4.5v9M8 5.25l8 4.5"/>',
        'counter' => '<rect x="3" y="12" width="18" height="8" rx="2"/><path d="M6 12V5h12v7M7 16h4m5 0h1"/>',
        'route' => '<circle cx="5" cy="6" r="2"/><circle cx="19" cy="18" r="2"/><path d="M7 6h8a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h8"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M21 10h-6a2 2 0 0 0 0 4h6m-5-2h.01"/>',
        'arrow' => '<path d="M5 19 19 5M8 5h11v11"/>',
        'back' => '<path d="m10 5-7 7 7 7m-7-7h18"/>',
    ];
    $icon = isset($icon) ? $icon : 'bus';
    $class = isset($class) ? $class : '';
@endphp
<svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$icon] !!}</svg>
