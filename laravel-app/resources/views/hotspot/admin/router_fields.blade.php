<div class="row">
    <div class="col-md-4 mb-2"><label>Location</label>
        <select class="form-control" name="location_id" required>
            <option value="">Choose</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @if($row && $row->location_id == $location->id) selected @endif>{{ $location->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-2"><label>Name</label><input class="form-control" name="name" value="{{ optional($row)->name }}" required></div>
    <div class="col-md-4 mb-2"><label>Identity</label><input class="form-control" name="identity" value="{{ optional($row)->identity }}"></div>
    <div class="col-md-3 mb-2"><label>Tunnel IP</label><input class="form-control" name="api_host" value="{{ optional($row)->api_host }}" placeholder="Leave empty until the tunnel exists"></div>
    <div class="col-md-2 mb-2"><label>API port</label><input class="form-control" name="api_port" value="{{ optional($row)->api_port ?: 8728 }}"></div>
    <div class="col-md-3 mb-2"><label>API user</label><input class="form-control" name="api_user" value="{{ optional($row)->api_user }}" required></div>
    <div class="col-md-4 mb-2"><label>API password</label><input class="form-control" type="password" name="api_password" placeholder="{{ $row ? 'Leave blank to keep' : '' }}" autocomplete="new-password"></div>
    <div class="col-md-4 mb-2"><label>RADIUS secret</label><input class="form-control" type="password" name="radius_secret" placeholder="{{ $row ? 'Leave blank to keep' : '' }}" autocomplete="new-password"></div>
    <div class="col-md-3 mb-2"><label>RouterOS version</label><input class="form-control" name="ros_version" value="{{ optional($row)->ros_version }}" placeholder="6 or 7"></div>
</div>
