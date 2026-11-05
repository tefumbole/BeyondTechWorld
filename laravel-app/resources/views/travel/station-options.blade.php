@php
    $by = isset($by) ? $by : 'name';
    $selected = isset($selected) ? $selected : null;
    $southwest = $stations->filter(function ($station) {
        return isset($station->region) && $station->region === 'South West';
    });
    $rest = $stations->filter(function ($station) {
        return ! isset($station->region) || $station->region !== 'South West';
    });
@endphp
@foreach($rest as $station)
    @php $value = $by === 'id' ? $station->id : $station->name; @endphp
    <option value="{{ $value }}" {{ (string) $selected === (string) $value ? 'selected' : '' }}>{{ $station->name }}</option>
@endforeach
@if($southwest->count())
    <optgroup label="South West">
        @foreach($southwest as $station)
            @php $value = $by === 'id' ? $station->id : $station->name; @endphp
            <option value="{{ $value }}" {{ (string) $selected === (string) $value ? 'selected' : '' }}>{{ $station->name }}</option>
        @endforeach
    </optgroup>
@endif
