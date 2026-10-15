@php
    $amount = $row ? max(1, (int) round($row->validity_seconds / 60)) : 1;
    $unit = 'hours';
    if ($row && $row->validity_seconds % 2592000 === 0) { $amount = (int) ($row->validity_seconds / 2592000); $unit = 'months'; }
    elseif ($row && $row->validity_seconds % 604800 === 0) { $amount = (int) ($row->validity_seconds / 604800); $unit = 'weeks'; }
    elseif ($row && $row->validity_seconds % 86400 === 0) { $amount = (int) ($row->validity_seconds / 86400); $unit = 'days'; }
    elseif ($row && $row->validity_seconds % 3600 === 0) { $amount = (int) ($row->validity_seconds / 3600); $unit = 'hours'; }
    elseif ($row) { $unit = 'minutes'; }
    $dataGb = '';
    if ($row && $row->data_bytes) {
        $dataGb = rtrim(rtrim(number_format($row->data_bytes / 1073741824, 2, '.', ''), '0'), '.');
    }
    $uploadOpen = $row && ($row->rate_up === null || (int) $row->rate_up === 0);
    $downloadOpen = $row && ($row->rate_down === null || (int) $row->rate_down === 0);
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
    <div class="col-md-2 mb-2"><label>Duration</label><input class="form-control" type="number" min="1" name="validity_value" value="{{ $amount }}" required></div>
    <div class="col-md-2 mb-2"><label>Unit</label>
        <select class="form-control" name="validity_unit">
            <option value="minutes" @if($unit==='minutes') selected @endif>Minutes</option>
            <option value="hours" @if($unit==='hours') selected @endif>Hours</option>
            <option value="days" @if($unit==='days') selected @endif>Days</option>
            <option value="weeks" @if($unit==='weeks') selected @endif>Weeks</option>
            <option value="months" @if($unit==='months') selected @endif>Months</option>
        </select>
    </div>
    <div class="col-md-2 mb-2"><label>Volume cap (GB)</label><input class="form-control" type="number" min="0.01" step="0.01" name="data_gb" value="{{ $dataGb }}" placeholder="Empty = unlimited"></div>
    <div class="col-md-2 mb-2">
        <label>Upload (Kbps)</label>
        <input class="form-control" type="number" min="1" name="rate_up" value="{{ $uploadOpen ? '' : (optional($row)->rate_up ?: 1024) }}" @if($uploadOpen) disabled @endif>
        <label class="mt-1 mb-0"><input type="checkbox" name="unlimited_up" value="1" class="hs-unlimited" data-target="rate_up" @if($uploadOpen) checked @endif> Unlimited upload</label>
    </div>
    <div class="col-md-2 mb-2">
        <label>Download (Kbps)</label>
        <input class="form-control" type="number" min="1" name="rate_down" value="{{ $downloadOpen ? '' : (optional($row)->rate_down ?: 2048) }}" @if($downloadOpen) disabled @endif>
        <label class="mt-1 mb-0"><input type="checkbox" name="unlimited_down" value="1" class="hs-unlimited" data-target="rate_down" @if($downloadOpen) checked @endif> Unlimited download</label>
    </div>
    <div class="col-md-2 mb-2"><label>Shared users</label><input class="form-control" type="number" min="1" name="shared_users" value="{{ optional($row)->shared_users ?: 1 }}"></div>
    <div class="col-md-2 mb-2"><label>Sessions</label><input class="form-control" type="number" min="1" name="simultaneous_sessions" value="{{ optional($row)->simultaneous_sessions ?: 1 }}"></div>
    <div class="col-md-2 mb-2"><label>Active</label>
        <select class="form-control" name="active"><option value="1" @if(!$row || $row->active) selected @endif>Yes</option><option value="0" @if($row && !$row->active) selected @endif>No</option></select>
    </div>
</div>
