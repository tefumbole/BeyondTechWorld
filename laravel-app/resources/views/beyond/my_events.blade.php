@extends('beyond.layout')
@section('title', 'My Events')
@section('content')
<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4">
        <h1 class="text-2xl font-bold text-brand-blue mb-6">My Event Assignments</h1>
        @if(session('status'))<div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('status') }}</div>@endif
        @if($assignments->isEmpty())
            <p class="text-gray-600">No event assignments linked to your account yet.</p>
        @else
            <div class="space-y-4">
                @foreach($assignments as $a)
                    <div class="bg-white rounded-lg border p-4 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold">{{ $a->event->name }}</h3>
                            <p class="text-sm text-gray-600">{{ $a->assignment_role }} · {{ $a->contract_status }} · TS: {{ $a->timesheet_status }}</p>
                        </div>
                        <a href="{{ route('staff.event-timesheet', $a->id) }}" class="px-4 py-2 bg-brand-blue text-white rounded-lg text-sm font-semibold">Timesheet</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
