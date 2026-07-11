@extends('beyond.layout')

@section('title', 'My Learning Progress')
@section('meta_description', 'Track your Beyond Enterprise course progress.')

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">

        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-brand-blue">My Learning Progress</h1>
            <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-2 border border-gray-300 bg-white px-4 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
            </a>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i> {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($progress->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 text-center py-16">
                <i data-lucide="book-open" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                <p class="text-gray-500 mb-4">No course progress to display yet.</p>
                <a href="{{ url('/register-now') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold">Browse Courses</a>
            </div>
        @else
            <div class="grid gap-6">
                @foreach ($progress as $item)
                    <div class="bg-white rounded-xl shadow-sm border-t-4 border-t-brand-blue overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-start gap-3">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">{{ $item->course_name }}</h2>
                                <div class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    Started: {{ optional($item->start_date)->format('M j, Y') ?? '—' }}
                                </div>
                            </div>
                            @if ($item->status === 'completed')
                                <span class="bg-green-600 text-white text-xs px-3 py-1 rounded-full font-bold">Completed</span>
                            @else
                                <span class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full font-bold">In Progress</span>
                            @endif
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm font-medium">
                                    <span>Progress</span>
                                    <span>{{ (int) $item->progress_percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 h-2 rounded-full">
                                    <div class="bg-brand-blue h-2 rounded-full" style="width: {{ (float) $item->progress_percentage }}%"></div>
                                </div>
                            </div>

                            @if ($item->status === 'completed')
                                <div class="pt-6 border-t border-gray-100">
                                    @if (empty($feedbackMap[$item->id]))
                                        <div class="bg-blue-50 rounded-lg p-6" x-data="{ rating: 5 }">
                                            <h3 class="font-semibold text-brand-blue mb-2 flex items-center gap-2">
                                                <i data-lucide="award" class="w-5 h-5"></i> Share your feedback
                                            </h3>
                                            <p class="text-sm text-gray-600 mb-4">Congratulations on completing the course! Please rate your experience.</p>
                                            <form method="POST" action="{{ route('student.feedback') }}" class="space-y-4">
                                                @csrf
                                                <input type="hidden" name="registration_id" value="{{ $item->registration_id }}">
                                                <input type="hidden" name="course_id" value="{{ $item->course_id }}">
                                                <input type="hidden" name="rating" :value="rating">
                                                <div class="flex items-center gap-1">
                                                    <template x-for="star in [1,2,3,4,5]" :key="star">
                                                        <button type="button" @click="rating = star" class="focus:outline-none">
                                                            <i data-lucide="star" class="w-7 h-7" :class="star <= rating ? 'text-brand-gold fill-brand-gold' : 'text-gray-300'"></i>
                                                        </button>
                                                    </template>
                                                    <span class="ml-2 text-sm text-gray-600" x-text="rating + ' / 5'"></span>
                                                </div>
                                                <textarea name="feedback_text" rows="3" placeholder="Tell us about your experience..."
                                                          class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"></textarea>
                                                <button type="submit" class="bg-brand-blue hover:bg-brand-dark text-white font-semibold px-5 py-2 rounded-md inline-flex items-center gap-2">
                                                    <i data-lucide="send" class="w-4 h-4"></i> Submit Feedback
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="bg-green-50 rounded-lg p-4 flex items-center gap-3 text-green-800">
                                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                                            <span class="font-medium">Thank you for your feedback!</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:initialized', () => { if (window.lucide) lucide.createIcons(); });
</script>
@endpush
