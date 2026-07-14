@php
    $isEdit = isset($event) && $event->exists;
    $action = $isEdit ? route('events.update', $event->id) : route('events.store');
    $workerProfiles = $workerProfiles ?? collect();
    $customersJson = $customers->map(function ($c) {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone_number,
            'email' => $c->email,
            'company' => $c->company_name ?? '',
            'address' => $c->address ?? '',
            'city' => $c->city ?? '',
        ];
    })->values();
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="events-form" id="event-form">
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
                <select name="customer_id" id="customer-id" class="form-control selectpicker" data-live-search="true">
                    <option value="">— Select customer —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id', $event->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }} @if($c->phone_number)({{ $c->phone_number }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12" id="client-info-panel" style="{{ old('customer_id', $event->customer_id) ? '' : 'display:none;' }}">
                <div class="alert alert-light border mb-3" style="background:#f4f7fc;">
                    <strong class="d-block mb-2 text-primary">Client information</strong>
                    <div class="row small mb-0" id="client-info-preview">
                        <div class="col-md-3"><span class="text-muted">Name</span><div id="ci-name">—</div></div>
                        <div class="col-md-3"><span class="text-muted">Phone</span><div id="ci-phone">—</div></div>
                        <div class="col-md-3"><span class="text-muted">Email</span><div id="ci-email">—</div></div>
                        <div class="col-md-3"><span class="text-muted">Company / City</span><div id="ci-company">—</div></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 form-group">
                <label>Client contact person</label>
                <input type="text" name="client_contact_person" id="client_contact_person" class="form-control" value="{{ old('client_contact_person', $event->client_contact_person) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Client telephone</label>
                <input type="text" name="client_telephone" id="client_telephone" class="form-control" value="{{ old('client_telephone', $event->client_telephone) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Client email</label>
                <input type="email" name="client_email" id="client_email" class="form-control" value="{{ old('client_email', $event->client_email) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Venue</label>
                <input type="text" name="venue" class="form-control" value="{{ old('venue', $event->venue) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>City</label>
                <input type="text" name="city" id="client_city" class="form-control" value="{{ old('city', $event->city) }}">
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
        <div class="booking-section-title">Operations Schedule</div>
        <div class="row">
            <div class="col-md-3 form-group">
                <label>From (event start) <span class="text-danger">*</span></label>
                <input type="text" name="event_start_at" class="form-control datetime-picker" required
                       value="{{ old('event_start_at', ($event->event_start_at ?? null) ? $event->event_start_at->format('Y-m-d H:i') : '') }}">
            </div>
            <div class="col-md-3 form-group">
                <label>To (event end) <span class="text-danger">*</span></label>
                <input type="text" name="event_end_at" class="form-control datetime-picker" required
                       value="{{ old('event_end_at', ($event->event_end_at ?? null) ? $event->event_end_at->format('Y-m-d H:i') : '') }}">
            </div>
            <div class="col-md-3 form-group">
                <label>Setup</label>
                <input type="text" name="setup_start_at" class="form-control datetime-picker"
                       value="{{ old('setup_start_at', ($event->setup_start_at ?? null) ? $event->setup_start_at->format('Y-m-d H:i') : '') }}">
            </div>
            <div class="col-md-3 form-group">
                <label>Packing</label>
                <input type="text" name="packing_at" class="form-control datetime-picker"
                       value="{{ old('packing_at', ($event->packing_at ?? null) ? $event->packing_at->format('Y-m-d H:i') : '') }}">
            </div>
            <div class="col-md-3 form-group">
                <label>Expected workdays</label>
                <input type="number" name="expected_workdays" id="expected_workdays" class="form-control" min="0" max="365"
                       value="{{ old('expected_workdays', $event->expected_workdays) }}">
            </div>
        </div>
        {{-- Keep less-used schedule fields available but collapsed --}}
        <details class="mt-2">
            <summary class="text-muted small" style="cursor:pointer;">More schedule dates (loading, return, etc.)</summary>
            <div class="row mt-2">
                @foreach([
                    'loading_at' => 'Equipment loading',
                    'departure_at' => 'Departure',
                    'setup_end_at' => 'Setup end',
                    'rehearsal_at' => 'Rehearsal',
                    'dismantling_start_at' => 'Dismantling start',
                    'dismantling_end_at' => 'Dismantling end',
                    'return_at' => 'Equipment return',
                ] as $field => $label)
                    <div class="col-md-4 form-group">
                        <label>{{ $label }}</label>
                        <input type="text" name="{{ $field }}" class="form-control datetime-picker"
                               value="{{ old($field, ($event->$field ?? null) ? $event->$field->format('Y-m-d H:i') : '') }}">
                    </div>
                @endforeach
            </div>
        </details>
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
            </div>
        </div>
    </div>

    <div class="booking-section">
        <div class="booking-section-title">Labour Payment Mode</div>
        <div class="row">
            <div class="col-md-4 form-group">
                <label>Labour payment mode</label>
                <select name="labour_mode" id="labour-mode" class="form-control">
                    <option value="individual" {{ old('labour_mode', $event->labour_mode ?: 'individual') == 'individual' ? 'selected' : '' }}>Individual rates</option>
                    <option value="budget" {{ old('labour_mode', $event->labour_mode) == 'budget' ? 'selected' : '' }}>Fixed labour budget</option>
                </select>
            </div>
            <div class="col-md-4 form-group" id="budget-amount-wrap" style="display:none;">
                <label>Total labour budget (XAF)</label>
                <input type="number" name="labour_budget_total" class="form-control" min="0" value="{{ old('labour_budget_total') }}">
            </div>
            @unless($isEdit)
                <div class="col-md-4 form-group">
                    <label>Publish on website</label>
                    <select name="publish_on_website" class="form-control">
                        <option value="1" {{ old('publish_on_website', '1') == '1' ? 'selected' : '' }}>Yes — show on Events &amp; home</option>
                        <option value="0" {{ old('publish_on_website') === '0' ? 'selected' : '' }}>No — keep draft</option>
                    </select>
                </div>
            @endunless
        </div>

        <div id="staff-assign-wrap">
            @if($isEdit)
                <p class="text-muted small mb-0">Manage staff assignments on the event <a href="{{ route('events.show', ['id' => $event->id, 'tab' => 'workforce']) }}">Workforce</a> tab.</p>
            @else
            <p class="text-muted small mb-2">Select one or more staff/workers and set their daily rate. You can also manage assignments later under the Workforce tab.</p>
            @if($workerProfiles->isEmpty())
                <div class="alert alert-warning mb-0">No worker profiles yet. <a href="{{ route('events.workforce.profiles') }}">Create worker profiles</a> first, or save the event and assign later.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="staff-assign-table">
                        <thead style="background:#0b3f90;color:#fff;">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Staff</th>
                                <th>Category</th>
                                <th>Role on event</th>
                                <th>Daily rate (XAF)</th>
                                <th>Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workerProfiles as $wp)
                                @php $checked = collect(old('staff', []))->contains($wp->id); @endphp
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="staff-check" name="staff[]" value="{{ $wp->id }}"
                                               data-rate="{{ $wp->standard_daily_rate }}" {{ $checked ? 'checked' : '' }}>
                                    </td>
                                    <td>{{ $wp->displayName() }}</td>
                                    <td>{{ optional($wp->category)->name }}</td>
                                    <td>
                                        <input type="text" name="staff_role[{{ $wp->id }}]" class="form-control form-control-sm"
                                               value="{{ old('staff_role.'.$wp->id, optional($wp->category)->name ?: 'Crew') }}" placeholder="Role">
                                    </td>
                                    <td>
                                        <input type="number" name="staff_rate[{{ $wp->id }}]" class="form-control form-control-sm staff-rate" min="0"
                                               value="{{ old('staff_rate.'.$wp->id, $wp->standard_daily_rate) }}">
                                    </td>
                                    <td>
                                        <input type="number" name="staff_days[{{ $wp->id }}]" class="form-control form-control-sm" min="1" max="365"
                                               value="{{ old('staff_days.'.$wp->id, 1) }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @endif
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

    var customers = @json($customersJson);
    var customerSel = document.getElementById('customer-id');
    var panel = document.getElementById('client-info-panel');

    function fillClient() {
        var id = customerSel ? String(customerSel.value || '') : '';
        var c = customers.find(function (x) { return String(x.id) === id; });
        if (!c) {
            if (panel) panel.style.display = 'none';
            return;
        }
        if (panel) panel.style.display = '';
        var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v || '—'; };
        set('ci-name', c.name);
        set('ci-phone', c.phone);
        set('ci-email', c.email);
        set('ci-company', [c.company, c.city].filter(Boolean).join(' · ') || '—');

        var contact = document.getElementById('client_contact_person');
        var phone = document.getElementById('client_telephone');
        var email = document.getElementById('client_email');
        var city = document.getElementById('client_city');
        if (contact && (!contact.value || contact.dataset.autofilled === '1')) {
            contact.value = c.name || '';
            contact.dataset.autofilled = '1';
        }
        if (phone && (!phone.value || phone.dataset.autofilled === '1')) {
            phone.value = c.phone || '';
            phone.dataset.autofilled = '1';
        }
        if (email && (!email.value || email.dataset.autofilled === '1')) {
            email.value = c.email || '';
            email.dataset.autofilled = '1';
        }
        if (city && (!city.value || city.dataset.autofilled === '1') && c.city) {
            city.value = c.city;
            city.dataset.autofilled = '1';
        }
    }

    if (customerSel) {
        $(customerSel).on('changed.bs.select change', fillClient);
        fillClient();
    }

    var mode = document.getElementById('rental-link-mode');
    var wrap = document.getElementById('booking-select-wrap');
    function syncRental() {
        if (!mode || !wrap) return;
        wrap.style.display = mode.value === 'link' ? '' : 'none';
    }
    if (mode) { mode.addEventListener('change', syncRental); syncRental(); }

    var labour = document.getElementById('labour-mode');
    var staffWrap = document.getElementById('staff-assign-wrap');
    var budgetWrap = document.getElementById('budget-amount-wrap');
    function syncLabour() {
        if (!labour) return;
        var individual = labour.value === 'individual';
        if (staffWrap) staffWrap.style.display = individual ? '' : 'none';
        if (budgetWrap) budgetWrap.style.display = individual ? 'none' : '';
    }
    if (labour) { labour.addEventListener('change', syncLabour); syncLabour(); }
})();
</script>
@endpush
