@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $labels = [
        'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
        'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
    ];
@endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ $backUrl }}" class="ip-btn ip-btn-outline mb-3">&larr; Back</a>
        <h1 class="ip-title">Working week</h1>
        <p class="ip-meta mb-3">{{ $personName }}
            @if(!empty($enrolment))
                · {{ optional($enrolment->program)->displayName() ?: optional($enrolment->program)->name }}
            @endif
        </p>

        <div class="ip-card">
            @if(empty($inspect['slots']))
                <p class="mb-2"><span class="ip-badge warn">Not configured</span></p>
                <p class="ip-meta mb-0">This intern has not saved a working week yet. Tasks will not release until they do.</p>
            @else
                <p class="mb-3">
                    <span class="ip-badge {{ $inspect['configured'] ? 'active' : 'blue' }}">
                        {{ $inspect['configured'] ? 'Saved on their account' : 'Saved on the application' }}
                    </span>
                    @if($inspect['label'])
                        <span class="ip-meta">{{ $inspect['label'] }}</span>
                    @endif
                </p>
                <table class="table ip-table mb-0">
                    <thead><tr><th>Day</th><th>From</th><th>To</th></tr></thead>
                    <tbody>
                    @foreach($inspect['slots'] as $slot)
                        <tr>
                            <td>{{ $labels[$slot['day']] ?? $slot['day'] }}</td>
                            <td>{{ $slot['start'] }}</td>
                            <td>{{ $slot['end'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>
@endsection
