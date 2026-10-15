@php
    $minutes = $row ? max(1, (int) round($row->validity_seconds / 60)) : 30;
    $unit = 'minutes';
    if ($row && $row->validity_seconds % 86400 === 0) { $minutes = (int) ($row->validity_seconds / 86400); $unit = 'days'; }
    elseif ($row && $row->validity_seconds % 3600 === 0) { $minutes = (int) ($row->validity_seconds / 3600); $unit = 'hours'; }
@endphp
<div class="row">
                <div class="col-md-3 mb-2"><label>Name</label><input class="form-control" name="name" value="{{ optional($row)->name }}" required></div>
    <div class="col-md-3 mb-2"><label>Location</label>
        <select class="form-control" name="location_id">
            <option value="">All locations</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @if($row && $row->location_id == $location->id) selected @endif>{{ $location->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 mb-2"><label>Price (XAF)</label><input class="form-control" type="number" min="100" step="1" name="price" value="{{ optional($row)->price }}" required></div>
    <div class="col-md-2 mb-2"><label>Duration</label><input class="form-control" type="number" min="1" name="validity_value" value="{{ $minutes }}" required></div>
    <div class="col-md-2 mb-2"><label>Unit</label>
        <select class="form-control" name="validity_unit">
            <option value="minutes" @if($unit==='minutes') selected @endif>Minutes</option>
            <option value="hours" @if($unit==='hours') selected @endif>Hours</option>
            <option value="days" @if($unit==='days') selected @endif>Days</option>
        </select>
    </div>
    <div class="col-md-2 mb-2"><label>Data cap (MB)</label><input class="form-control" type="number" min="1" name="data_mb" value="{{ $row && $row->data_bytes ? (int) round($row->data_bytes / 1048576) : '' }}" placeholder="Optional"></div>
    <div class="col-md-2 mb-2"><label>Upload (Kbps)</label><input class="form-control" type="number" min="1" name="rate_up" value="{{ optional($row)->rate_up ?: 1024 }}"></div>
    <div class="col-md-2 mb-2"><label>Download (Kbps)</label><input class="form-control" type="number" min="1" name="rate_down" value="{{ optional($row)->rate_down ?: 2048 }}"></div>
    <div class="col-md-2 mb-2"><label>Shared users</label><input class="form-control" type="number" min="1" name="shared_users" value="{{ optional($row)->shared_users ?: 1 }}"></div>
    <div class="col-md-2 mb-2"><label>Sessions</label><input class="form-control" type="number" min="1" name="simultaneous_sessions" value="{{ optional($row)->simultaneous_sessions ?: 1 }}"></div>
    <div class="col-md-2 mb-2"><label>Active</label>
        <select class="form-control" name="active"><option value="1" @if(!$row || $row->active) selected @endif>Yes</option><option value="0" @if($row && !$row->active) selected @endif>No</option></select>
    </div>
</div>
