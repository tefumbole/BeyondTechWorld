<div class="form-row">
    <div class="form-group col-md-3"><label>Name</label><input class="form-control" name="name" required value="{{ $bus ? $bus->name : '' }}"></div>
    <div class="form-group col-md-2"><label>Type</label>
        <select class="form-control" name="bus_type">
            @foreach($types as $type)<option value="{{ $type }}" {{ $bus && $bus->bus_type === $type ? 'selected' : '' }}>{{ $type }}</option>@endforeach
        </select>
    </div>
    <div class="form-group col-md-3"><label>Processing preset version</label>
        <select class="form-control" name="preset_version_id"><option value="">None</option>
            @foreach($versions as $version)
                <option value="{{ $version->id }}" {{ $bus && (int) $bus->preset_version_id === (int) $version->id ? 'selected' : '' }}>{{ optional($version->preset)->name }} v{{ $version->version_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-2"><label>Limiter threshold (dB)</label><input class="form-control" name="limiter_threshold_db" value="{{ $bus ? $bus->limiter_threshold_db : '' }}"></div>
    <div class="form-group col-md-2"><label>Limiter release (ms)</label><input class="form-control" name="limiter_release_ms" value="{{ $bus ? $bus->limiter_release_ms : '' }}"></div>
    <div class="form-group col-12"><label>Notes</label><input class="form-control" name="notes" value="{{ $bus ? $bus->notes : '' }}"></div>
</div>
