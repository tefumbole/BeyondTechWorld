@php
    $files = $files ?? (isset($task) ? $task->attachments : collect());
@endphp
@if($files && $files->count())
    <div class="{{ $wrapClass ?? 'mt-3 space-y-2' }}">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Attachments</div>
        <div class="flex flex-wrap gap-2">
            @foreach($files as $file)
                <a href="{{ $file->href() }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    @if($file->isImage())
                        <img src="{{ $file->href() }}" alt="" class="w-9 h-9 rounded object-cover">
                    @else
                        <i data-lucide="file-text" class="w-4 h-4 text-brand-blue"></i>
                    @endif
                    <span class="max-w-[180px] truncate">{{ $file->file_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
