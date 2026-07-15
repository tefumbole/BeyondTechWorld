@extends('beyond.layout')

@section('title', 'Student Portal')
@section('meta_description', 'Your Beyond Enterprise student dashboard.')

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-brand-blue">Student Portal</h1>
                <p class="text-gray-500">Welcome back, {{ $user->name ?: $user->email }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('student.progress') }}" class="inline-flex items-center gap-2 border border-brand-blue text-brand-blue px-4 py-2 rounded-md font-medium hover:bg-blue-50">
                    <i data-lucide="trending-up" class="w-4 h-4"></i> My Progress
                </a>
                <form method="POST" action="{{ route('beyond.logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-200 text-red-600 px-4 py-2 rounded-md font-medium hover:bg-red-50">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between pb-2">
                    <span class="text-sm font-medium text-gray-500">Enrolled Courses</span>
                    <i data-lucide="book-open" class="w-5 h-5 text-blue-500"></i>
                </div>
                <div class="text-2xl font-bold">{{ $enrolledCount }}</div>
                <p class="text-xs text-gray-500 mt-1">Active learning paths</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between pb-2">
                    <span class="text-sm font-medium text-gray-500">Completed</span>
                    <i data-lucide="award" class="w-5 h-5 text-brand-gold"></i>
                </div>
                <div class="text-2xl font-bold">{{ $completedCount }}</div>
                <p class="text-xs text-gray-500 mt-1">Finished courses</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between pb-2">
                    <span class="text-sm font-medium text-gray-500">Average Progress</span>
                    <i data-lucide="activity" class="w-5 h-5 text-green-500"></i>
                </div>
                <div class="text-2xl font-bold">{{ $avgProgress }}%</div>
                <p class="text-xs text-gray-500 mt-1">Across all courses</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-brand-blue mb-1">My Courses</h2>
                <p class="text-sm text-gray-500 mb-4">Your enrolled training programs</p>

                @if ($progress->isEmpty())
                    <div class="text-center py-12">
                        <i data-lucide="book-open" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-gray-500 mb-4">You are not enrolled in any courses yet.</p>
                        <a href="{{ url('/register-now') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold">
                            <i data-lucide="plus" class="w-4 h-4"></i> Browse Courses
                        </a>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($progress as $item)
                            <div class="p-4 border rounded-lg">
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <h3 class="font-semibold text-brand-blue">{{ $item->course_name }}</h3>
                                        <p class="text-sm text-gray-500">Started {{ optional($item->start_date)->format('M j, Y') }}</p>
                                    </div>
                                    @if ($item->status === 'completed')
                                        <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-bold">Completed</span>
                                    @elseif ($item->status === 'in_progress')
                                        <span class="bg-blue-100 text-brand-blue text-xs px-2 py-1 rounded-full font-bold">In Progress</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-bold">Not Started</span>
                                    @endif
                                </div>
                                <div class="w-full bg-gray-200 h-2 rounded-full mt-4">
                                    <div class="bg-brand-gold h-2 rounded-full" style="width: {{ (float) $item->progress_percentage }}%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-right">{{ (int) $item->progress_percentage }}%</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-brand-blue mb-4">Profile</h2>
                <div class="flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="user" class="w-10 h-10 text-gray-400"></i>
                    </div>
                    <h3 class="font-bold text-lg">{{ $user->name ?: $user->email }}</h3>
                    <p class="text-sm text-gray-500 mb-1">{{ $user->email }}</p>
                    <p class="text-xs text-gray-400 mb-4">Student ID: {{ substr($user->id, 0, 8) }}</p>
                    <a href="{{ url('/user/profile') }}" class="w-full bg-brand-blue text-white py-2 rounded-md font-semibold text-center hover:bg-brand-dark">Edit Profile</a>
                </div>

                @if ($registrations->isNotEmpty())
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Registrations</p>
                        <ul class="space-y-2">
                            @foreach ($registrations as $reg)
                                <li class="text-sm">
                                    <a href="{{ route('training.registered', $reg->reference_number) }}" class="text-brand-light hover:underline font-mono">{{ $reg->reference_number }}</a>
                                    <span class="text-gray-400">· {{ $reg->created_at->format('M j, Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
