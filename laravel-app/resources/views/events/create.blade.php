@extends('layout.main')

@section('content')
<style>
    .booking-section { background:#fff;border:1px solid #e3e9f4;border-radius:14px;padding:20px;margin-bottom:18px; }
    .booking-section-title { font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#0b3f90;margin-bottom:14px; }
    .crew-table input[type="number"] { min-width:88px; }
    .plus-btn { width:42px;height:38px;border-radius:8px;font-size:22px;line-height:1;padding:0; }
    #find-result { min-height:1.2em; }
</style>
<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#0b3f90,#1456b8);color:#fff;">
                <h4 class="mb-0">Create Event</h4>
                <a href="{{ route('events.index') }}" class="btn btn-sm btn-light">All Events</a>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data" id="event-form">
                    @csrf
                    <div class="booking-section">
                        <div class="booking-section-title">Event</div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Event name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required value="{{ old('name', $event->name) }}" placeholder="Name shown with the flyer">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="customer-id" class="form-control selectpicker" data-live-search="true" required title="Select customer">
                                    <option value="">Select customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ (string) old('customer_id', $event->customer_id) === (string) $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}@if($customer->phone_number) — {{ $customer->phone_number }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="booking-section">
                        <div class="booking-section-title">Event duration</div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>From <span class="text-danger">*</span></label>
                                <input type="date" name="event_start_at" id="event-from" class="form-control" required value="{{ old('event_start_at') }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>To <span class="text-danger">*</span></label>
                                <input type="date" name="event_end_at" id="event-to" class="form-control" required value="{{ old('event_end_at') }}">
                            </div>
                        </div>
                        <p class="text-muted small mb-0" id="span-note">Choose the first and last day. Each person's days start from that span.</p>
                    </div>

                    <div class="booking-section">
                        <div class="booking-section-title">Labour force</div>
                        <div class="form-row align-items-end">
                            <div class="col-md-8 form-group">
                                <label>People already in the system</label>
                                <select id="labour-pick" class="form-control selectpicker" data-live-search="true" title="Select a person">
                                    <option value="">Select a person</option>
                                    @foreach($people as $index => $person)
                                        <option value="{{ $index }}">{{ $person['name'] }}@if(!empty($person['phone'])) — {{ $person['phone'] }}@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group d-flex">
                                <button type="button" class="btn btn-primary mr-2" id="add-person">Add</button>
                                <button type="button" class="btn btn-outline-primary plus-btn" id="show-new-person" title="Add someone by phone number">+</button>
                            </div>
                        </div>
                        <div id="new-person" class="border rounded p-3 mb-3" style="display:none;background:#f7f9fc;">
                            <label>New person — Mobile Money number</label>
                            <div class="form-row">
                                <div class="col-md-6 mb-2">
                                    <input type="text" id="new-phone" class="form-control" placeholder="6XX XXX XXX" inputmode="tel">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="button" class="btn btn-primary btn-block" id="find-name">Find name</button>
                                </div>
                            </div>
                            <div id="find-result" class="small text-muted"></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered crew-table mb-2" id="crew-table">
                                <thead style="background:#0b3f90;color:#fff;">
                                    <tr>
                                        <th>Name</th>
                                        <th>Days</th>
                                        <th>Daily rate (XAF)</th>
                                        <th>Food money</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <p class="mb-0"><strong>Labour total: <span id="labour-total">0</span> XAF</strong></p>
                        <p class="text-muted small mb-0">Each person keeps their own name, daily rate, and food amount. Pay is (daily rate + food) × days.</p>
                    </div>

                    <div class="booking-section">
                        <div class="booking-section-title">Website</div>
                        <div class="form-group">
                            <label>Flyer</label>
                            <input type="file" name="flyer" class="form-control-file" accept="image/*">
                        </div>
                        <div class="form-group mb-0">
                            <input type="hidden" name="publish_on_website" value="0">
                            <label class="mb-0">
                                <input type="checkbox" name="publish_on_website" value="1" {{ old('publish_on_website') ? 'checked' : '' }}>
                                Publish on the website
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Create Event</button>
                    <a href="{{ route('events.index') }}" class="btn btn-light btn-lg">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var people = @json($people);
    var oldCrew = @json(old('crew', []));
    var lookupUrl = @json(route('events.labour-name'));
    var token = document.querySelector('#event-form input[name="_token"]').value;
    var tbody = document.querySelector('#crew-table tbody');
    var rowIndex = 0;

    function spanDays() {
        var from = document.getElementById('event-from').value;
        var to = document.getElementById('event-to').value;
        var note = document.getElementById('span-note');
        if (!from || !to) {
            note.textContent = 'Choose the first and last day. Each person\'s days start from that span.';
            return 1;
        }
        var ms = Date.parse(to) - Date.parse(from);
        var days = ms < 0 || isNaN(ms) ? 1 : Math.floor(ms / 86400000) + 1;
        note.textContent = days + (days === 1 ? ' day' : ' days') + ' from ' + from + ' to ' + to + '.';
        return days;
    }

    function money(n) {
        return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function refresh() {
        var total = 0;
        tbody.querySelectorAll('tr').forEach(function (row) {
            var days = parseInt(row.querySelector('.crew-days').value, 10) || 0;
            var rate = parseInt(row.querySelector('.crew-rate').value, 10) || 0;
            var food = parseInt(row.querySelector('.crew-food').value, 10) || 0;
            var line = (rate + food) * days;
            row.querySelector('.crew-line').textContent = money(line);
            total += line;
        });
        document.getElementById('labour-total').textContent = money(total);
    }

    function addRow(person) {
        var key = person.kind + ':' + person.ref;
        if (tbody.querySelector('tr[data-key="' + key + '"]')) {
            return;
        }
        var i = rowIndex++;
        var days = person.days || spanDays();
        var tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm crew-name" name="crew[' + i + '][name]" value="' + escapeHtml(person.name || '') + '" required>' +
                '<div class="small text-muted">' + escapeHtml(person.phone || '') + '</div>' +
                '<input type="hidden" name="crew[' + i + '][kind]" value="' + escapeHtml(person.kind) + '">' +
                '<input type="hidden" name="crew[' + i + '][ref]" value="' + escapeHtml(person.ref || '') + '">' +
                '<input type="hidden" name="crew[' + i + '][phone]" value="' + escapeHtml(person.phone || '') + '">' +
            '</td>' +
            '<td><input type="number" class="form-control form-control-sm crew-days" name="crew[' + i + '][days]" min="1" max="365" value="' + days + '" data-auto="' + (person.days ? '0' : '1') + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm crew-rate" name="crew[' + i + '][rate]" min="0" value="' + (parseInt(person.rate, 10) || 0) + '" required></td>' +
            '<td><input type="number" class="form-control form-control-sm crew-food" name="crew[' + i + '][food]" min="0" value="' + (parseInt(person.food, 10) || 0) + '" placeholder="0"></td>' +
            '<td class="crew-line font-weight-bold">0</td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger crew-remove">Remove</button></td>';
        tbody.appendChild(tr);
        refresh();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
        });
    }

    document.getElementById('add-person').addEventListener('click', function () {
        var sel = document.getElementById('labour-pick');
        var picked = window.jQuery ? window.jQuery(sel).val() : sel.value;
        var person = people[picked];
        if (!person) {
            return;
        }
        addRow(person);
    });

    document.getElementById('show-new-person').addEventListener('click', function () {
        var box = document.getElementById('new-person');
        box.style.display = box.style.display === 'none' ? '' : 'none';
        document.getElementById('new-phone').focus();
    });

    document.getElementById('find-name').addEventListener('click', function () {
        var result = document.getElementById('find-result');
        var phone = document.getElementById('new-phone').value.trim();
        result.className = 'small text-muted';
        result.textContent = 'Checking Campay…';
        var body = new FormData();
        body.append('_token', token);
        body.append('phone', phone);
        fetch(lookupUrl, {method: 'POST', body: body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (res) { return res.json().then(function (data) { return {ok: res.ok, data: data}; }); })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    result.className = 'small text-danger';
                    result.textContent = (res.data && res.data.message) ? res.data.message : 'Campay has no registered name for this number.';
                    return;
                }
                result.className = 'small text-success';
                result.textContent = res.data.name;
                addRow({kind: 'phone', ref: res.data.phone, name: res.data.name, phone: res.data.phone, rate: 0, days: spanDays()});
                document.getElementById('new-phone').value = '';
            })
            .catch(function () {
                result.className = 'small text-danger';
                result.textContent = 'The name check did not complete. Try that number again.';
            });
    });

    tbody.addEventListener('input', function (event) {
        if (event.target.classList.contains('crew-days')) {
            event.target.setAttribute('data-auto', '0');
        }
        refresh();
    });
    tbody.addEventListener('change', refresh);
    tbody.addEventListener('click', function (event) {
        if (!event.target.classList.contains('crew-remove')) {
            return;
        }
        event.target.closest('tr').remove();
        refresh();
    });

    function applySpan() {
        var days = spanDays();
        tbody.querySelectorAll('.crew-days').forEach(function (input) {
            if (input.getAttribute('data-auto') === '1') {
                input.value = days;
            }
        });
        refresh();
    }
    document.getElementById('event-from').addEventListener('change', applySpan);
    document.getElementById('event-to').addEventListener('change', applySpan);
    spanDays();

    (oldCrew || []).forEach(function (row) {
        if (!row || !row.kind) {
            return;
        }
        addRow({
            kind: row.kind,
            ref: row.ref || row.phone || '',
            name: row.name || row.phone || '',
            phone: row.phone || '',
            rate: row.rate || 0,
            days: row.days || spanDays(),
            food: parseInt(row.food, 10) || 0
        });
    });
})();
</script>
@endpush

@stack('scripts')
