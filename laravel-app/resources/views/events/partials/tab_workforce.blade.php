@if($rentalWarning)
    <div class="alert alert-warning">{{ $rentalWarning }}</div>
@endif

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold">Assign worker</div>
            <div class="card-body">
                <form method="POST" action="{{ route('events.workforce.assign', $event->id) }}">
                    @csrf
                    <div class="form-group">
                        <label>Worker profile</label>
                        <select name="worker_profile_id" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">— Select —</option>
                            @foreach($workerProfiles as $wp)
                                <option value="{{ $wp->id }}">
                                    {{ $wp->displayName() }} — {{ optional($wp->category)->name }} ({{ number_format($wp->standard_daily_rate) }} XAF/day)
                                </option>
                            @endforeach
                        </select>
                        <small><a href="{{ route('events.workforce.profiles') }}">Manage worker profiles</a></small>
                    </div>
                    <div class="form-group">
                        <label>Assignment role <span class="text-danger">*</span></label>
                        <input type="text" name="assignment_role" class="form-control" required placeholder="e.g. Lead Sound Engineer">
                    </div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Work start</label>
                            <input type="date" name="work_start_date" class="form-control">
                        </div>
                        <div class="col-6 form-group">
                            <label>Work end</label>
                            <input type="date" name="work_end_date" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4 form-group">
                            <label>Expected days</label>
                            <input type="number" name="expected_days" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-4 form-group">
                            <label>Event daily rate (XAF)</label>
                            <input type="number" name="event_daily_rate" class="form-control" min="0" placeholder="Override">
                        </div>
                        <div class="col-4 form-group">
                            <label>Pay method</label>
                            <select name="compensation_method" class="form-control">
                                <option value="daily">Daily rate</option>
                                <option value="hourly">Hourly rate</option>
                                <option value="fixed">Fixed amount</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_supervisor" value="1"> Supervisor</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Add to workforce</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header font-weight-bold">Assigned workers ({{ $event->assignments->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Category</th>
                            <th>Rate × Days</th>
                            <th>Expected</th>
                            <th>Contract</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($event->assignments as $a)
                            <tr>
                                <td>{{ optional($a->workerProfile)->displayName() }}</td>
                                <td>{{ $a->assignment_role }}</td>
                                <td>{{ optional(optional($a->workerProfile)->category)->name }}</td>
                                <td>{{ number_format($a->event_daily_rate) }} × {{ $a->expected_days }}</td>
                                <td><strong>{{ number_format($a->expected_total) }} XAF</strong></td>
                                <td><span class="badge badge-secondary">{{ $a->contract_status }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('events.workforce.remove', [$event->id, $a->id]) }}" onsubmit="return confirm('Remove assignment?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">×</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center py-3">No workers assigned yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">Contracts, signing and reminders arrive in Phases 4–6.</p>
    </div>
</div>
