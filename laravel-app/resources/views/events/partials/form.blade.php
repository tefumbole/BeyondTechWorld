@php
    $isEdit = isset($event) && $event->exists;
    $action = $isEdit ? route('events.update', $event->id) : route('events.store');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="events-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="booking-section">
        <div class="booking-section-title">Basic Information</div>
        <div class="row">
            <div class="col-md-8 form-group">
                <label>Event name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="{{ old('name', $event->name) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Event type <span class="text-danger">*</span></label>
                <select name="event_type" class="form-control" required>
                    @foreach(\App\Event::TYPES as $k => $label)
                        <option value="{{ $k }}" {{ old('event_type', $event->event_type) == $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label>URL slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $event->slug) }}" placeholder="auto-generated if empty">
            </div>
            <div class="col-md-4 form-group">
                <label>Internal status</label>
                <select name="internal_status" class="form-control">
                    @foreach(\App\Event::STATUSES as $k => $label)
                        <option value="{{ $k }}" {{ old('internal_status', $event->internal_status) == $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($isEdit)
                <div class="col-md-4 form-group">
                    <label>Status change note</label>
                    <input type="text" name="status_note" class="form-control" placeholder="Optional reason for status change">
                </div>
            @endif
            <div class="col-md-6 form-group">
                <label>Event flyer / cover image</label>
                <input type="file" name="flyer" class="form-control-file" accept="image/*">
                @if($event->flyer_path)
                    <img src="{{ $event->flyerUrl() }}" alt="" class="mt-2" style="max-height:80px;border-radius:8px;">
                @endif
            </div>
            <div class="col-md-6 form-group">
                <label>Client / customer</label>
                <select name="customer_id" class="form-control selectpicker" data-live-search="true">
                    <option value="">— Select customer —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id', $event->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }} @if($c->phone_number)({{ $c->phone_number }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label>Client contact person</label>
                <input type="text" name="client_contact_person" class="form-control" value="{{ old('client_contact_person', $event->client_contact_person) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Client telephone</label>
                <input type="text" name="client_telephone" class="form-control" value="{{ old('client_telephone', $event->client_telephone) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Client email</label>
                <input type="email" name="client_email" class="form-control" value="{{ old('client_email', $event->client_email) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Venue</label>
                <input type="text" name="venue" class="form-control" value="{{ old('venue', $event->venue) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>City</label>
                <input type="text" name="city" class="form-control" value="{{ old('city', $event->city) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Timezone</label>
                <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $event->timezone ?: 'Africa/Kigali') }}">
            </div>
            <div class="col-12 form-group">
                <label>Full venue address</label>
                <textarea name="venue_address" class="form-control" rows="2">{{ old('venue_address', $event->venue_address) }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Internal description</label>
                <textarea name="internal_description" class="form-control" rows="4">{{ old('internal_description', $event->internal_description) }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Internal notes</label>
                <textarea name="internal_notes" class="form-control" rows="4">{{ old('internal_notes', $event->internal_notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="booking-section">
        <div class="booking-section-title">Dates &amp; Operations Schedule</div>
        <div class="row">
            @foreach([
                'packing_at' => 'Equipment packing',
                'loading_at' => 'Equipment loading',
                'departure_at' => 'Departure',
                'setup_start_at' => 'Setup start',
                'setup_end_at' => 'Setup end',
                'rehearsal_at' => 'Rehearsal',
                'event_start_at' => 'Event start',
                'event_end_at' => 'Event end',
                'dismantling_start_at' => 'Dismantling start',
                'dismantling_end_at' => 'Dismantling end',
                'return_at' => 'Equipment return',
                'timesheet_deadline_at' => 'Timesheet deadline',
            ] as $field => $label)
                <div class="col-md-4 form-group">
                    <label>{{ $label }}</label>
                    <input type="text" name="{{ $field }}" class="form-control datetime-picker"
                           value="{{ old($field, ($event->$field ?? null) ? $event->$field->format('Y-m-d H:i') : '') }}">
                </div>
            @endforeach
            <div class="col-md-4 form-group">
                <label>Expected workdays</label>
                <input type="number" name="expected_workdays" class="form-control" min="0" max="365"
                       value="{{ old('expected_workdays', $event->expected_workdays) }}">
            </div>
        </div>
    </div>

    <div class="booking-section">
        <div class="booking-section-title">Rental Connection</div>
        <div class="row">
            <div class="col-md-4 form-group">
                <label>Rental link mode</label>
                <select name="rental_link_mode" class="form-control" id="rental-link-mode">
                    <option value="none" {{ old('rental_link_mode', $event->rental_link_mode) == 'none' ? 'selected' : '' }}>Continue without rental</option>
                    <option value="link" {{ old('rental_link_mode', $event->rental_link_mode) == 'link' ? 'selected' : '' }}>Link existing rental</option>
                </select>
            </div>
            <div class="col-md-8 form-group" id="booking-select-wrap">
                <label>Existing rental (booking)</label>
                <select name="booking_id" class="form-control selectpicker" data-live-search="true">
                    <option value="">— Select rental —</option>
                    @foreach($bookings as $b)
                        <option value="{{ $b->id }}" {{ old('booking_id', $event->booking_id) == $b->id ? 'selected' : '' }}>
                            {{ $b->reference_no }} — Paid: {{ number_format($b->paid_amount) }} / {{ number_format($b->grand_total) }} XAF
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Equipment availability logic is reused from the rental module. Date mismatch warnings arrive in Phase 3.</small>
            </div>
            <div class="col-md-4 form-group">
                <label>Labour payment mode</label>
                <select name="labour_mode" class="form-control">
                    <option value="individual" {{ old('labour_mode', $event->labour_mode) == 'individual' ? 'selected' : '' }}>Individual rates</option>
                    <option value="budget" {{ old('labour_mode', $event->labour_mode) == 'budget' ? 'selected' : '' }}>Fixed labour budget</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-lg">{{ $isEdit ? 'Save Changes' : 'Create Event' }}</button>
        <a href="{{ $isEdit ? route('events.show', $event->id) : route('events.index') }}" class="btn btn-light btn-lg">Cancel</a>
    </div>
</form>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function () {
    document.querySelectorAll('.datetime-picker').forEach(function (el) {
        flatpickr(el, { enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true });
    });
    var mode = document.getElementById('rental-link-mode');
    var wrap = document.getElementById('booking-select-wrap');
    function syncRental() {
        if (!mode || !wrap) return;
        wrap.style.display = mode.value === 'link' ? '' : 'none';
    }
    if (mode) { mode.addEventListener('change', syncRental); syncRental(); }
})();
</script>
@endpush
