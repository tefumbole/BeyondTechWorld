@extends('beyond.layout')
@section('title', 'Event Timesheet')
@section('content')
<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4">
        <a href="{{ route('staff.my-events') }}" class="text-brand-blue text-sm">← My events</a>
        <h1 class="text-2xl font-bold text-brand-blue mt-2 mb-1">{{ $assignment->event->name }}</h1>
        <p class="text-gray-600 mb-6">{{ $assignment->assignment_role }} · Status: {{ $timesheet->statusLabel() }}</p>
        @if(session('status'))<div class="bg-green-100 p-3 rounded mb-4">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="bg-red-100 p-3 rounded mb-4">{{ $errors->first() }}</div>@endif

        @if($timesheet->status !== 'submitted' && $timesheet->status !== 'approved')
            <form method="POST" action="{{ route('staff.event-timesheet.entry', $assignment->id) }}" class="bg-white rounded-lg border p-4 mb-6">
                @csrf
                <h3 class="font-bold mb-3">Log work day</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-sm">Date</label><input type="date" name="work_date" class="w-full border rounded px-3 py-2" required></div>
                    <div><label class="text-sm">Hours</label><input type="number" name="hours" value="8" min="0" max="24" step="0.5" class="w-full border rounded px-3 py-2" required></div>
                </div>
                <div class="mt-3"><label class="text-sm">Notes</label><input type="text" name="notes" class="w-full border rounded px-3 py-2"></div>
                <button class="mt-3 px-4 py-2 bg-brand-blue text-white rounded font-semibold">Add day</button>
            </form>
        @endif

        <div class="bg-white rounded-lg border overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-brand-blue text-white"><tr><th class="p-3 text-left">Date</th><th class="p-3">Hours</th><th class="p-3 text-left">Notes</th></tr></thead>
                <tbody>
                    @forelse($timesheet->entries as $e)
                        <tr class="border-t"><td class="p-3">{{ $e->work_date->format('d M Y') }}</td><td class="p-3 text-center">{{ $e->hours }}</td><td class="p-3">{{ $e->notes }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-gray-500 text-center">No entries yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50"><tr><td class="p-3 font-bold">Total</td><td class="p-3 text-center font-bold">{{ $timesheet->total_hours }}h</td><td class="p-3">{{ $timesheet->total_days }} days</td></tr></tfoot>
            </table>
        </div>

        @if($timesheet->status === 'draft' || $timesheet->status === 'rejected')
            <form method="POST" action="{{ route('staff.event-timesheet.submit', $assignment->id) }}">@csrf
                <button class="px-6 py-3 bg-brand-gold text-brand-blue font-bold rounded-lg">Submit for approval</button>
            </form>
        @endif
    </div>
</section>
@endsection
