<div class="form-row">
    <div class="form-group col-md-4"><label>Show name</label><input class="form-control" name="name" required value="{{ $show ? $show->name : '' }}"></div>
    <div class="form-group col-md-4"><label>Event type</label>
        <input class="form-control" name="event_type" list="mix-event-types" value="{{ $show ? $show->event_type : '' }}">
        <datalist id="mix-event-types">@foreach($types as $type)<option value="{{ $type }}">@endforeach</datalist>
    </div>
    <div class="form-group col-md-4"><label>Version</label><input class="form-control" name="version_label" value="{{ $show ? $show->version_label : '' }}"></div>
    <div class="form-group col-md-4"><label>Console</label>
        <select class="form-control" name="console_id"><option value="">—</option>
            @foreach($consoles as $console)<option value="{{ $console->id }}" {{ $show && (int) $show->console_id === (int) $console->id ? 'selected' : '' }}>{{ $console->name }}</option>@endforeach
        </select>
    </div>
    <div class="form-group col-md-8"><label>Pinned mix template version</label>
        <select class="form-control" name="template_version_id"><option value="">—</option>
            @foreach($versions as $version)
                <option value="{{ $version->id }}" {{ $show && (int) $show->template_version_id === (int) $version->id ? 'selected' : '' }}>{{ optional($version->template)->name }} · v{{ $version->version_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-6"><label>Main L/R</label><textarea class="form-control" name="main_notes" rows="2">{{ $show ? $show->main_notes : '' }}</textarea></div>
    <div class="form-group col-md-6"><label>Monitor mixes</label><textarea class="form-control" name="monitor_notes" rows="2">{{ $show ? $show->monitor_notes : '' }}</textarea></div>
    <div class="form-group col-md-6"><label>Subwoofer</label><textarea class="form-control" name="subwoofer_notes" rows="2">{{ $show ? $show->subwoofer_notes : '' }}</textarea></div>
    <div class="form-group col-md-6"><label>Buses</label><textarea class="form-control" name="bus_notes" rows="2">{{ $show ? $show->bus_notes : '' }}</textarea></div>
    <div class="form-group col-12"><label>Notes</label><textarea class="form-control" name="notes" rows="2">{{ $show ? $show->notes : '' }}</textarea></div>
    <div class="form-group col-12"><label><input type="checkbox" name="published" value="1" {{ $show && $show->published ? 'checked' : '' }}> Published</label></div>
</div>
