@php
    $sections = isset($sections) && is_array($sections) ? $sections : [];
    $weeks = isset($weeks) && is_array($weeks) ? $weeks : [];
    $color = $color ?? '#003D82';
@endphp
@if($sections || $weeks)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($sections as $section)
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <h4 class="text-base font-bold text-brand-blue mb-3">{{ $section['title'] }}</h4>
                <ul class="space-y-2 m-0 p-0 list-none">
                    @foreach(($section['items'] ?? []) as $item)
                        <li class="flex items-start gap-2 text-gray-700 text-sm">
                            <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 shrink-0" style="color: {{ $color }}"></i>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    @if($weeks)
        <h4 class="text-sm font-bold uppercase tracking-wider text-brand-blue mt-5 mb-3">By week</h4>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($weeks as $week)
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <h4 class="text-base font-bold text-brand-blue mb-3">{{ $week['title'] }}</h4>
                    <ul class="space-y-2 m-0 p-0 list-none">
                        @foreach(($week['items'] ?? []) as $item)
                            <li class="flex items-start gap-2 text-gray-700 text-sm">
                                <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 shrink-0" style="color: {{ $color }}"></i>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
@endif
