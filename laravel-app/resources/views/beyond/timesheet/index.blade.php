@extends('beyond.layout')

@section('title', 'My Timesheet')
@section('meta_description', 'Log and review your working hours at Beyond Enterprise.')

@php
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $m = \Carbon\Carbon::now()->subMonths($i);
        $months[$m->format('Y-m')] = $m->format('F Y');
    }
@endphp

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8"
     x-data="{ editing: null, openEdit(e){ this.editing = e } }">
    <div class="max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-brand-blue flex items-center gap-2">
                    <i data-lucide="clock" class="w-6 h-6"></i> My Timesheet
                </h1>
                <p class="text-gray-500">{{ $user->name ?: $user->email }}</p>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ url('/staff/timesheet') }}">
                    <select name="month" onchange="this.form.submit()"
                            class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-blue focus:border-brand-blue">
                        @foreach ($months as $value => $label)
                            <option value="{{ $value }}" @if($value === $month) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-200 text-red-600 px-4 py-2 rounded-md font-medium hover:bg-red-50">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i> {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Total Hours</p>
                <p class="text-2xl font-bold text-brand-blue mt-1">{{ $summary['total_hours'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Days Logged</p>
                <p class="text-2xl font-bold text-brand-blue mt-1">{{ $summary['days_logged'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Entries</p>
                <p class="text-2xl font-bold text-brand-blue mt-1">{{ $summary['entries_count'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Avg / Day</p>
                <p class="text-2xl font-bold text-brand-blue mt-1">{{ $summary['avg_per_day'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Fill form --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 lg:col-span-1 h-fit">
                <h2 class="text-lg font-bold text-brand-blue mb-4 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5"></i> Log Hours
                </h2>
                <form method="POST" action="{{ url('/staff/timesheet') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="entry_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-blue focus:border-brand-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity</label>
                        <select name="activity_id"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-blue focus:border-brand-blue">
                            <option value="">— Select activity —</option>
                            @foreach ($activities as $activity)
                                <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hours</label>
                        <input type="number" name="hours" step="0.25" min="0" max="24" required placeholder="e.g. 8"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-blue focus:border-brand-blue">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="3" placeholder="What did you work on?"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-blue focus:border-brand-blue"></textarea>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-brand-blue text-white px-4 py-2.5 rounded-md font-semibold hover:bg-blue-800">
                        <i data-lucide="save" class="w-4 h-4"></i> Save Entry
                    </button>
                </form>

                @if ($summary['by_activity']->isNotEmpty())
                    <div class="mt-6 pt-4 border-t">
                        <h3 class="text-sm font-bold text-gray-700 mb-2">Hours by Activity</h3>
                        <div class="space-y-1.5">
                            @foreach ($summary['by_activity'] as $name => $hours)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ $name }}</span>
                                    <span class="font-semibold text-gray-900">{{ $hours }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Entries --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 lg:col-span-2">
                <h2 class="text-lg font-bold text-brand-blue mb-4 flex items-center gap-2">
                    <i data-lucide="list" class="w-5 h-5"></i> Entries — {{ $months[$month] ?? $month }}
                </h2>

                @if ($entries->isEmpty())
                    <div class="text-center py-12">
                        <i data-lucide="calendar-off" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-gray-500">No timesheet entries for this month yet.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($entries as $entry)
                            @php
                                $editPayload = [
                                    'id' => $entry->id,
                                    'entry_date' => \Carbon\Carbon::parse($entry->entry_date)->toDateString(),
                                    'activity_id' => $entry->activity_id,
                                    'hours' => (float) $entry->hours,
                                    'notes' => $entry->notes,
                                ];
                            @endphp
                            <div class="p-4 border rounded-lg bg-white shadow-sm border-l-4 border-l-brand-gold flex justify-between items-start gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-gray-900">{{ \Carbon\Carbon::parse($entry->entry_date)->format('D, M j') }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-brand-blue font-semibold">{{ $entry->activity_name ?: 'Uncategorized' }}</span>
                                    </div>
                                    @if ($entry->notes)
                                        <p class="text-sm text-gray-500 mt-1 break-words">{{ $entry->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-lg font-bold text-brand-blue whitespace-nowrap">{{ rtrim(rtrim(number_format($entry->hours, 2), '0'), '.') }} h</span>
                                    <button type="button"
                                            @click="openEdit({{ json_encode($editPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }})"
                                            class="text-gray-400 hover:text-brand-blue">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                    </button>
                                    <form method="POST" action="{{ url('/staff/timesheet/'.$entry->id) }}"
                                          onsubmit="return confirm('Delete this entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-600">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div x-show="editing" x-cloak style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.away="editing = null">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-brand-blue">Edit Entry</h3>
                <button type="button" @click="editing = null" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form method="POST" x-bind:action="'/staff/timesheet/' + (editing?.id || '')" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="entry_date" x-model="editing.entry_date" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Activity</label>
                    <select name="activity_id" x-model="editing.activity_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="">— Select activity —</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hours</label>
                    <input type="number" name="hours" step="0.25" min="0" max="24" x-model="editing.hours" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" x-model="editing.notes"
                              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="editing = null" class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="flex-1 bg-brand-blue text-white px-4 py-2 rounded-md font-semibold hover:bg-blue-800">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
