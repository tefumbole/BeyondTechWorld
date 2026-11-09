@extends('layout.main')

@section('content')
@include('events.partials.modern')
@php
    $editing = ! empty($editing);
    $existingCrew = isset($existingCrew) ? $existingCrew : [];
    $setupDates = isset($setupDates) ? $setupDates : [];
    $unpackDates = isset($unpackDates) ? $unpackDates : [];
    $published = $event->exists && optional($event->publication)->publish_on_website;
    $setupOld = old('setup_dates');
    if (is_string($setupOld) && $setupOld !== '') {
        $decodedSetup = json_decode($setupOld, true);
        if (is_array($decodedSetup)) {
            $setupDates = $decodedSetup;
        }
    }
    $unpackOld = old('unpack_dates');
    if (is_string($unpackOld) && $unpackOld !== '') {
        $decodedUnpack = json_decode($unpackOld, true);
        if (is_array($decodedUnpack)) {
            $unpackDates = $decodedUnpack;
        }
    }
@endphp
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-create-hero">
            <div>
                <div class="ev-kicker">Events</div>
                <h2>{{ $editing ? 'Edit event' : 'Create event' }}</h2>
                <p class="ev-lead">{{ $editing ? 'Add or remove engineers, change a rate, and choose the days each person works.' : 'Set the dates and the engineers. Notes go to the staff. The client is not notified.' }}</p>
            </div>
            <a href="{{ route('events.dashboard') }}" class="btn btn-light">Dashboard</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>The event was not saved.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="alert alert-danger" id="form-error" style="display:none;"></div>
        @if($roles->isEmpty())
            <div class="alert alert-warning">Add at least one role and daily rate under <a href="{{ route('events.settings.categories') }}">Event Settings</a> before assigning engineers.</div>
        @endif

        <form method="POST" action="{{ $editing ? route('events.update', $event->id) : route('events.store') }}" enctype="multipart/form-data" id="event-form">
            @csrf
            @if($editing) @method('PUT') @endif
            <input type="hidden" name="crew_form" value="1">
            <div class="ev-section">
                <h3>Event</h3>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Event name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $event->name) }}" placeholder="Name shown with the flyer">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Customer</label>
                        <div class="ev-combo" id="customer-combo">
                            <input type="hidden" name="customer_id" id="customer-id" value="{{ old('customer_id', $event->customer_id) }}">
                            <input type="search" name="customer_query" class="form-control ev-combo-input" id="customer-search" placeholder="Optional. Search customer name or phone" autocomplete="off" value="{{ old('customer_query', $selectedCustomer) }}">
                            <div class="ev-combo-menu" id="customer-menu" hidden></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ev-section">
                <h3>Event duration</h3>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>From <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="event_start_at" id="event-from" class="form-control" required value="{{ old('event_start_at', optional($event->event_start_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>To <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="event_end_at" id="event-to" class="form-control" required value="{{ old('event_end_at', optional($event->event_end_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>
                <div class="ev-duration-note" id="span-note">Choose the first and last date. Each person's working days start from that span.</div>
                <div class="form-group">
                    <label>Setup dates</label>
                    <div>
                        <button type="button" class="btn btn-outline-primary" id="setup-open">Choose setup dates</button>
                        <div class="ev-chips" id="setup-chips"></div>
                        <div id="setup-notes" class="mt-2"></div>
                        <input type="hidden" name="setup_dates" id="setup-dates" value="{{ json_encode(array_values($setupDates)) }}">
                    </div>
                    <div class="small text-muted mt-1">Click every setup day. You can select more than one. Add a note for each date. Engineers receive these dates and notes.</div>
                </div>
                <div class="form-group">
                    <label>Unpacking dates</label>
                    <div>
                        <button type="button" class="btn btn-outline-primary" id="unpack-open">Choose unpacking dates</button>
                        <div class="ev-chips" id="unpack-chips"></div>
                        <div id="unpack-notes" class="mt-2"></div>
                        <input type="hidden" name="unpack_dates" id="unpack-dates" value="{{ json_encode(array_values($unpackDates)) }}">
                    </div>
                    <div class="small text-muted mt-1">Click every unpacking day. You can select more than one, including a day after the event.</div>
                </div>
                <div class="form-group mb-0">
                    <label>Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="3" placeholder="Schedule notes, call times, or anything to remember about these dates">{{ old('internal_notes', $event->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="ev-section">
                <h3>Engineers</h3>
                <div class="form-row align-items-end">
                    <div class="col-md-8 form-group mb-md-0">
                        <label>Search a person</label>
                        <div class="ev-combo" id="engineer-combo">
                            <input type="search" class="form-control ev-combo-input" id="engineer-search" placeholder="Search customers, staff, interns, or any account" autocomplete="off">
                            <div class="ev-combo-menu" id="engineer-menu" hidden></div>
                        </div>
                    </div>
                    <div class="col-md-4 form-group mb-md-0">
                        <button type="button" class="btn btn-outline-primary btn-block" id="show-new-person">New number</button>
                    </div>
                </div>
                <p class="ev-lead mt-2">Add people here, change the daily rate, and open Working days to pick the exact dates. A person can work a day before or after the event, and not everyone has to work every day. Click a date chip to remove that day.</p>
                <div id="new-person" class="ev-new-person" style="display:none;">
                    <label>Mobile Money number</label>
                    <div class="form-row">
                        <div class="col-md-7 mb-2">
                            <input type="text" id="new-phone" class="form-control" placeholder="6XX XXX XXX" inputmode="tel">
                        </div>
                        <div class="col-md-5 mb-2">
                            <button type="button" class="btn btn-primary btn-block" id="find-name">Find name</button>
                        </div>
                    </div>
                    <div id="find-result" class="small text-muted"></div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table crew-table mb-2" id="crew-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Working days</th>
                                <th>Daily rate</th>
                                <th>Food money</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p class="ev-crew-empty mb-2" id="crew-empty">No engineers yet.</p>
                <p class="mb-0"><strong>Labour total: <span id="labour-total">0</span> XAF</strong></p>
                <div id="day-panels" class="mt-3"></div>
            </div>

            <div class="ev-section">
                <h3>Website</h3>
                <div class="ev-drop">
                    <label class="d-block font-weight-bold mb-2">Flyer</label>
                    <input type="file" name="flyer" class="form-control-file" accept="image/*">
                    <div class="small text-muted mt-2">Shown on the public event page when you publish.</div>
                </div>
                <label class="ev-toggle">
                    <input type="hidden" name="publish_on_website" value="0">
                    <input type="checkbox" name="publish_on_website" value="1" {{ old('publish_on_website', $published ? '1' : '0') == '1' ? 'checked' : '' }}>
                    Publish on the website
                </label>
            </div>

            <div class="ev-actions">
                <button type="submit" class="btn btn-primary btn-lg">{{ $editing ? 'Save changes' : 'Create event' }}</button>
                <a href="{{ $editing ? route('events.show', $event->id) : route('events.index') }}" class="btn btn-light btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    var people = @json($people);
    var customers = @json($customerOptions);
    var roles = @json($roleOptions);
    var oldCrew = @json(old('crew', $existingCrew));
    var initialSetup = @json(array_values($setupDates));
    var initialUnpack = @json(array_values($unpackDates));
    var isAdmin = @json(!empty($isEventAdmin));
    var lookupUrl = @json(route('events.labour-name'));
    var roleUrl = @json(route('events.labour-role'));
    var token = document.querySelector('#event-form input[name="_token"]').value;
    var tbody = document.querySelector('#crew-table tbody');
    var rowIndex = 0;
    var activeIndex = -1;

    var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var roleMenu = document.createElement('div');
    roleMenu.className = 'ev-role-menu';
    roleMenu.hidden = true;
    document.body.appendChild(roleMenu);

    function prettyDate(iso) {
        var parts = String(iso || '').split('-');
        if (parts.length !== 3) return iso;
        var month = months[parseInt(parts[1], 10) - 1] || parts[1];
        return parseInt(parts[2], 10) + ' ' + month + ' ' + parts[0];
    }

    function spanDays() {
        var from = document.getElementById('event-from').value;
        var to = document.getElementById('event-to').value;
        var note = document.getElementById('span-note');
        if (!from || !to) {
            note.textContent = 'Choose the first and last date and time. Each person\'s working days start from that span.';
            return 1;
        }
        var fromDay = dateOnly(from);
        var toDay = dateOnly(to);
        var ms = Date.parse(toDay) - Date.parse(fromDay);
        var days = ms < 0 || isNaN(ms) ? 1 : Math.floor(ms / 86400000) + 1;
        note.textContent = days + (days === 1 ? ' day' : ' days') + ', from ' + prettyWhen(from) + ' to ' + prettyWhen(to) + '.';
        return days;
    }

    function money(n) {
        return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
        });
    }

    function roleById(id) {
        for (var i = 0; i < roles.length; i++) {
            if (String(roles[i].id) === String(id)) return roles[i];
        }
        return null;
    }

    function isoDate(date) {
        var month = date.getMonth() + 1;
        var day = date.getDate();
        return date.getFullYear() + '-' + (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day;
    }

    function dateOnly(value) {
        return String(value || '').slice(0, 10);
    }

    function prettyWhen(value) {
        var date = prettyDate(dateOnly(value));
        var match = String(value || '').match(/T(\d{2}:\d{2})/);
        return match ? date + ' ' + match[1] : date;
    }

    function spanDateList() {
        var from = dateOnly(document.getElementById('event-from').value);
        var to = dateOnly(document.getElementById('event-to').value);
        if (!from) return [];
        if (!to || to < from) to = from;
        var out = [];
        var cursor = new Date(from + 'T00:00:00');
        var end = new Date(to + 'T00:00:00');
        var guard = 0;
        while (cursor <= end && guard < 62) {
            out.push(isoDate(cursor));
            cursor.setDate(cursor.getDate() + 1);
            guard++;
        }
        return out;
    }

    function readEntries(value, rich) {
        var raw = readDates(value);
        var out = [];
        raw.forEach(function (item) {
            var date = '';
            var note = '';
            var description = '';
            var payAmount = '';
            var payAt = '';
            if (item && typeof item === 'object') {
                date = String(item.date || '').slice(0, 10);
                note = item.note || '';
                description = item.description || '';
                payAmount = item.pay_amount === 0 || item.pay_amount ? item.pay_amount : '';
                payAt = item.pay_at || '';
            } else {
                date = String(item || '').slice(0, 10);
            }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
            var entry = {date: date, note: String(note)};
            if (rich) {
                entry.description = String(description);
                entry.pay_amount = payAmount === '' ? '' : String(parseInt(payAmount, 10) || 0);
                entry.pay_at = String(payAt).replace(' ', 'T').slice(0, 16);
            }
            out.push(entry);
        });
        out.sort(function (a, b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : 0; });
        return out;
    }

    function readSetup(value) {
        return readEntries(value, false);
    }

    function entryDates(input) {
        if (!input) return [];
        var rich = input.classList && input.classList.contains('crew-dates');
        if (rich || input.id === 'setup-dates' || input.id === 'unpack-dates') {
            return readEntries(input.value, rich).map(function (entry) { return entry.date; });
        }
        return readDates(input.value).filter(function (item) { return typeof item === 'string'; });
    }

    function readDates(value) {
        if (Array.isArray(value)) return value.slice();
        if (!value) return [];
        try {
            var parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function refresh() {
        var total = 0;
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (row) {
            var dates = readEntries(row.querySelector('.crew-dates').value, true);
            row.querySelector('.crew-dates').value = JSON.stringify(dates);
            var days = dates.length || parseInt(row.querySelector('.crew-days').value, 10) || 0;
            row.querySelector('.crew-days').value = dates.length || days;
            var button = row.querySelector('.crew-days-btn');
            if (button) button.textContent = (dates.length || days) + (dates.length === 1 || days === 1 ? ' working day' : ' working days');
            var chips = row.querySelector('.crew-date-chips');
            if (chips) {
                chips.innerHTML = '';
                dates.forEach(function (entry) {
                    var chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'ev-chip';
                    chip.setAttribute('data-date', entry.date);
                    chip.textContent = prettyDate(entry.date);
                    chips.appendChild(chip);
                });
            }
            var rate = parseInt(row.querySelector('.crew-rate').value, 10) || 0;
            var food = parseInt(row.querySelector('.crew-food').value, 10) || 0;
            var line = (rate + food) * (dates.length || days);
            row.querySelector('.crew-line').textContent = money(line);
            total += line;
        });
        document.getElementById('labour-total').textContent = money(total);
        document.getElementById('crew-empty').style.display = rows.length ? 'none' : '';
        paintSetup();
        paintUnpack();
        paintDays();
    }

    function roleLabel(role) {
        return role ? role.name + ' — ' + money(role.rate) + ' XAF' : 'Choose role';
    }

    function closeRoleMenu() {
        roleMenu.hidden = true;
        roleMenu._row = null;
    }

    function openRoleMenu(button) {
        var row = button.closest('tr');
        var current = row.querySelector('.crew-role').value;
        roleMenu.innerHTML = '';
        roles.forEach(function (role) {
            var option = document.createElement('button');
            option.type = 'button';
            option.className = 'ev-role-option' + (String(current) === String(role.id) ? ' is-current' : '');
            option.setAttribute('data-id', role.id);
            option.innerHTML = '<strong>' + escapeHtml(role.name) + '</strong><span>' + money(role.rate) + ' XAF</span>';
            roleMenu.appendChild(option);
        });
        var rect = button.getBoundingClientRect();
        var width = Math.max(rect.width, 260);
        var left = Math.min(rect.left, window.innerWidth - width - 12);
        roleMenu.style.left = Math.max(8, left) + 'px';
        roleMenu.style.top = (rect.bottom + 6) + 'px';
        roleMenu.style.width = width + 'px';
        roleMenu._row = row;
        roleMenu.hidden = false;
    }

    function applyRole(row, role) {
        var input = row.querySelector('.crew-role');
        var button = row.querySelector('.ev-role-btn');
        var rate = row.querySelector('.crew-rate');
        var note = row.querySelector('.ev-rate-note');
        input.value = role ? role.id : '';
        button.textContent = roleLabel(role);
        button.classList.toggle('is-empty', !role);
        button.classList.remove('is-invalid');
        if (role) {
            rate.value = role.rate;
            note.textContent = 'You can change this rate';
            rememberRole(row, role.id);
        } else {
            note.textContent = 'Choose a role, then set the rate';
        }
        refresh();
    }

    function addRow(person) {
        var key = person.kind + ':' + person.ref;
        if (tbody.querySelector('tr[data-key="' + key + '"]')) return;
        var i = rowIndex++;
        var dates = readEntries(person.work_dates, true);
        var auto = dates.length ? '0' : '1';
        if (!dates.length) dates = spanDateList().map(function (date) { return {date: date, note: '', description: '', pay_amount: '', pay_at: ''}; });
        var role = roleById(person.category_id);
        var rate = (person.rate || person.rate === 0) && person.work_dates ? (parseInt(person.rate, 10) || 0) : (role ? role.rate : (parseInt(person.rate, 10) || 0));
        if (person.assignment_id) rate = parseInt(person.rate, 10) || 0;
        var tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.setAttribute('data-kind', person.kind);
        tr.setAttribute('data-ref', person.ref || '');
        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm crew-name" name="crew[' + i + '][name]" value="' + escapeHtml(person.name || '') + '" required>' +
                '<div class="small text-muted">' + escapeHtml(person.phone || '') + '</div>' +
                '<input type="hidden" name="crew[' + i + '][assignment_id]" value="' + escapeHtml(person.assignment_id || '') + '">' +
                '<input type="hidden" name="crew[' + i + '][kind]" value="' + escapeHtml(person.kind) + '">' +
                '<input type="hidden" name="crew[' + i + '][ref]" value="' + escapeHtml(person.ref || '') + '">' +
                '<input type="hidden" name="crew[' + i + '][phone]" value="' + escapeHtml(person.phone || '') + '">' +
            '</td>' +
            '<td><div class="ev-role">' +
                '<input type="hidden" class="crew-role" name="crew[' + i + '][category_id]" value="' + escapeHtml(person.category_id || '') + '">' +
                '<button type="button" class="ev-role-btn' + (role ? '' : ' is-empty') + '">' + escapeHtml(roleLabel(role)) + '</button>' +
            '</div></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-primary crew-days-btn">Working days</button>' +
                '<div class="ev-chips crew-date-chips"></div>' +
                '<input type="hidden" class="crew-dates" name="crew[' + i + '][work_dates]" data-auto="' + auto + '" value="' + escapeHtml(JSON.stringify(dates)) + '">' +
                '<input type="hidden" class="crew-days" name="crew[' + i + '][days]" value="' + dates.length + '">' +
            '</td>' +
            '<td><input type="number" class="form-control form-control-sm crew-rate" name="crew[' + i + '][rate]" min="0" value="' + rate + '" required>' +
                '<span class="ev-rate-note">' + (role ? 'You can change this rate' : 'Set the daily rate') + '</span></td>' +
            '<td><input type="number" class="form-control form-control-sm crew-food" name="crew[' + i + '][food]" min="0" value="' + (parseInt(person.food, 10) || 0) + '" placeholder="0"></td>' +
            '<td class="crew-line font-weight-bold">0</td>' +
            '<td><button type="button" class="btn btn-sm btn-light crew-remove" aria-label="Remove">×</button></td>';
        tbody.appendChild(tr);
        refresh();
    }

    function rememberRole(row, categoryId) {
        var kind = row.getAttribute('data-kind');
        if (kind !== 'profile' && kind !== 'customer' && kind !== 'user' && kind !== 'employee' && kind !== 'portal') return;
        var body = new FormData();
        body.append('_token', token);
        body.append('kind', kind);
        body.append('ref', row.getAttribute('data-ref'));
        body.append('category_id', categoryId);
        fetch(roleUrl, {method: 'POST', body: body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}})
            .then(function (res) { return res.json().then(function (data) { return {ok: res.ok, data: data}; }); })
            .then(function (res) {
                if (!res.ok) return;
                people.forEach(function (person) {
                    if (person.kind === kind && String(person.ref) === String(row.getAttribute('data-ref'))) {
                        person.category_id = res.data.category_id;
                        person.role = res.data.role;
                        person.rate = res.data.rate;
                    }
                });
            });
    }

    function paintMenu(menu, items, render, emptyText) {
        menu.innerHTML = '';
        activeIndex = -1;
        if (!items.length) {
            menu.innerHTML = '<div class="px-3 py-2 text-muted small">' + emptyText + '</div>';
            menu.hidden = false;
            return;
        }
        items.slice(0, 20).forEach(function (item, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'ev-combo-item';
            button.setAttribute('data-index', index);
            button.innerHTML = render(item);
            menu.appendChild(button);
        });
        menu.hidden = false;
    }

    function filterList(list, query) {
        var q = query.trim().toLowerCase();
        if (!q) return list.slice(0, 20);
        return list.filter(function (item) {
            return ((item.name || '') + ' ' + (item.phone || '') + ' ' + (item.email || '') + ' ' + (item.role || '') + ' ' + (item.source || '')).toLowerCase().indexOf(q) !== -1;
        });
    }

    function customerMenu() {
        var menu = document.getElementById('customer-menu');
        var items = filterList(customers, document.getElementById('customer-search').value);
        paintMenu(menu, items, function (item) {
            return '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.phone || '') + '</small></span>';
        }, 'No customer matches that search.');
        menu._items = items.slice(0, 20);
    }

    function engineerMenu() {
        var menu = document.getElementById('engineer-menu');
        var items = filterList(people, document.getElementById('engineer-search').value);
        paintMenu(menu, items, function (item) {
            var detail = item.phone || item.email || 'No phone';
            if (item.source) detail += ' · ' + item.source;
            var badge = item.role
                ? '<span class="ev-role-pill">' + escapeHtml(item.role) + '</span>'
                : (item.source ? '<span class="ev-source-pill">' + escapeHtml(item.source) + '</span>' : '');
            return '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(detail) + '</small></span>' + badge;
        }, 'No account matches that search.');
        menu._items = items.slice(0, 20);
    }

    function chooseCustomer(item) {
        document.getElementById('customer-id').value = item.id;
        document.getElementById('customer-search').value = item.name + (item.phone ? ' — ' + item.phone : '');
        document.getElementById('customer-menu').hidden = true;
    }

    function chooseEngineer(item) {
        addRow(item);
        document.getElementById('engineer-search').value = '';
        document.getElementById('engineer-menu').hidden = true;
    }

    function moveActive(menu, delta) {
        var buttons = menu.querySelectorAll('.ev-combo-item');
        if (!buttons.length) return;
        activeIndex = (activeIndex + delta + buttons.length) % buttons.length;
        buttons.forEach(function (button, index) {
            button.classList.toggle('is-active', index === activeIndex);
        });
        buttons[activeIndex].scrollIntoView({block: 'nearest'});
    }

    function bindCombo(inputId, menuId, open, choose) {
        var input = document.getElementById(inputId);
        var menu = document.getElementById(menuId);
        input.addEventListener('focus', open);
        input.addEventListener('input', function () {
            open();
        });
        input.addEventListener('blur', function () {
            if (inputId !== 'customer-search') return;
            var typed = input.value.trim().toLowerCase();
            if (!typed) {
                document.getElementById('customer-id').value = '';
                return;
            }
            var exact = customers.filter(function (item) {
                var label = (item.name + (item.phone ? ' — ' + item.phone : '')).toLowerCase();
                return label === typed || (item.name || '').toLowerCase() === typed;
            });
            if (exact.length === 1) chooseCustomer(exact[0]);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') { event.preventDefault(); moveActive(menu, 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); moveActive(menu, -1); }
            else if (event.key === 'Escape') { menu.hidden = true; }
            else if (event.key === 'Enter') {
                if (menu.hidden) return;
                event.preventDefault();
                var items = menu._items || [];
                var pick = items[activeIndex >= 0 ? activeIndex : 0];
                if (pick) choose(pick);
            }
        });
        menu.addEventListener('mousedown', function (event) {
            var button = event.target.closest('.ev-combo-item');
            if (!button) return;
            event.preventDefault();
            var items = menu._items || [];
            var pick = items[parseInt(button.getAttribute('data-index'), 10)];
            if (pick) choose(pick);
        });
    }

    bindCombo('customer-search', 'customer-menu', customerMenu, chooseCustomer);
    bindCombo('engineer-search', 'engineer-menu', engineerMenu, chooseEngineer);
    document.addEventListener('click', function (event) {
        if (!event.target.closest('#customer-combo')) document.getElementById('customer-menu').hidden = true;
        if (!event.target.closest('#engineer-combo')) document.getElementById('engineer-menu').hidden = true;
    });

    document.getElementById('show-new-person').addEventListener('click', function () {
        var box = document.getElementById('new-person');
        box.style.display = box.style.display === 'none' ? '' : 'none';
        if (box.style.display !== 'none') document.getElementById('new-phone').focus();
    });

    document.getElementById('find-name').addEventListener('click', function () {
        var result = document.getElementById('find-result');
        var phone = document.getElementById('new-phone').value.trim();
        result.className = 'small text-muted';
        result.textContent = 'Checking Campay…';
        var body = new FormData();
        body.append('_token', token);
        body.append('phone', phone);
        fetch(lookupUrl, {method: 'POST', body: body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}})
            .then(function (res) { return res.json().then(function (data) { return {ok: res.ok, data: data}; }); })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    result.className = 'small text-danger';
                    result.textContent = (res.data && res.data.message) ? res.data.message : 'Campay has no registered name for this number.';
                    return;
                }
                result.className = 'small text-success';
                result.textContent = res.data.name + ' added. Choose their role.';
                addRow({kind: 'phone', ref: res.data.phone, name: res.data.name, phone: res.data.phone, rate: 0, days: spanDays()});
                document.getElementById('new-phone').value = '';
            })
            .catch(function () {
                result.className = 'small text-danger';
                result.textContent = 'The name check did not complete. Try that number again.';
            });
    });

    tbody.addEventListener('input', function (event) {
        if (event.target.classList.contains('crew-days')) event.target.setAttribute('data-auto', '0');
        refresh();
    });
    tbody.addEventListener('click', function (event) {
        var button = event.target.closest('.ev-role-btn');
        if (!button) return;
        event.preventDefault();
        if (!roleMenu.hidden && roleMenu._row === button.closest('tr')) {
            closeRoleMenu();
            return;
        }
        openRoleMenu(button);
    });
    roleMenu.addEventListener('click', function (event) {
        var option = event.target.closest('.ev-role-option');
        if (!option || !roleMenu._row) return;
        applyRole(roleMenu._row, roleById(option.getAttribute('data-id')));
        closeRoleMenu();
    });
    document.addEventListener('click', function (event) {
        if (event.target.closest('.ev-role-btn') || event.target.closest('.ev-role-menu')) return;
        closeRoleMenu();
    });
    window.addEventListener('scroll', function (event) {
        if (event.target && event.target.closest && event.target.closest('.ev-role-menu')) return;
        closeRoleMenu();
    }, true);
    window.addEventListener('resize', closeRoleMenu);
    tbody.addEventListener('click', function (event) {
        if (!event.target.classList.contains('crew-remove')) return;
        event.target.closest('tr').remove();
        refresh();
    });

    function applySpan() {
        var dates = spanDateList();
        spanDays();
        tbody.querySelectorAll('.crew-dates').forEach(function (input) {
            if (input.getAttribute('data-auto') === '1') {
                input.value = JSON.stringify(dates.map(function (date) {
                    return {date: date, note: '', description: '', pay_amount: '', pay_at: ''};
                }));
            }
        });
        refresh();
    }
    document.getElementById('event-from').addEventListener('change', applySpan);
    document.getElementById('event-to').addEventListener('change', applySpan);
    spanDays();

    var pop = document.createElement('div');
    pop.className = 'ev-popcal';
    pop.hidden = true;
    document.body.appendChild(pop);
    var popState = {input: null, year: 2026, month: 0};

    function paintDateNotes(inputId, boxId, notesId, openId, single, plural, placeholder) {
        var box = document.getElementById(boxId);
        var notes = document.getElementById(notesId);
        var input = document.getElementById(inputId);
        if (!box || !input) return;
        var entries = readSetup(input.value);
        input.value = JSON.stringify(entries);
        box.innerHTML = '';
        if (notes) notes.innerHTML = '';
        entries.forEach(function (entry) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ev-chip';
            chip.setAttribute('data-date', entry.date);
            chip.textContent = prettyDate(entry.date);
            box.appendChild(chip);
            if (!notes) return;
            var row = document.createElement('div');
            row.className = 'form-row align-items-center mb-2';
            row.innerHTML = '<div class="col-md-4"><strong>' + escapeHtml(prettyDate(entry.date)) + '</strong></div>' +
                '<div class="col-md-8"><input type="text" class="form-control form-control-sm date-note" data-date="' + entry.date + '" maxlength="240" placeholder="' + escapeHtml(placeholder) + '" value="' + escapeHtml(entry.note) + '"></div>';
            notes.appendChild(row);
        });
        var opener = document.getElementById(openId);
        if (opener) opener.textContent = entries.length ? entries.length + ' ' + (entries.length === 1 ? single : plural) : 'Choose ' + plural;
    }

    function paintSetup() {
        paintDateNotes('setup-dates', 'setup-chips', 'setup-notes', 'setup-open', 'setup date', 'setup dates', 'Note for this setup date');
    }

    function paintUnpack() {
        paintDateNotes('unpack-dates', 'unpack-chips', 'unpack-notes', 'unpack-open', 'unpacking date', 'unpacking dates', 'Note for this unpacking date');
    }

    function paintDays() {
        var box = document.getElementById('day-panels');
        if (!box) return;
        if (document.activeElement && document.activeElement.closest && document.activeElement.closest('#day-panels')) return;
        box.innerHTML = '';
        tbody.querySelectorAll('tr').forEach(function (row) {
            var input = row.querySelector('.crew-dates');
            var entries = readEntries(input.value, true);
            if (!entries.length) return;
            var name = row.querySelector('.crew-name');
            var rate = parseInt(row.querySelector('.crew-rate').value, 10) || 0;
            var food = parseInt(row.querySelector('.crew-food').value, 10) || 0;
            var card = document.createElement('div');
            card.className = 'border rounded p-3 mb-3';
            var html = '<strong>' + escapeHtml(name ? name.value : 'Engineer') + '</strong>';
            html += '<p class="small text-muted mb-2">Add a note and a description for each day this person works.</p>';
            if (isAdmin) {
                html += '<div class="form-row align-items-end mb-2">' +
                    '<div class="col-md-5"><label class="small mb-1">Automatic payment time for this person</label><input type="datetime-local" class="form-control form-control-sm day-auto-time" data-key="' + escapeHtml(row.getAttribute('data-key')) + '"></div>' +
                    '<div class="col-md-4"><button type="button" class="btn btn-sm btn-outline-primary day-auto-apply" data-key="' + escapeHtml(row.getAttribute('data-key')) + '">Use this time for every day</button></div>' +
                    '</div>';
                html += '<p class="small text-muted">Set a different amount and time for each day. Leave the time empty when that day should not be paid automatically.</p>';
            }
            entries.forEach(function (entry) {
                var amount = entry.pay_amount === '' ? (rate + food) : entry.pay_amount;
                html += '<div class="border-top pt-2 mt-2" data-date="' + entry.date + '" data-key="' + escapeHtml(row.getAttribute('data-key')) + '">';
                html += '<div class="font-weight-bold mb-1">' + escapeHtml(prettyDate(entry.date)) + '</div>';
                html += '<input type="text" class="form-control form-control-sm mb-1 day-note" maxlength="240" placeholder="Note" value="' + escapeHtml(entry.note) + '">';
                html += '<input type="text" class="form-control form-control-sm mb-1 day-description" maxlength="500" placeholder="Description" value="' + escapeHtml(entry.description) + '">';
                if (isAdmin) {
                    html += '<div class="form-row"><div class="col-md-4"><input type="number" min="0" class="form-control form-control-sm day-amount" placeholder="Amount" value="' + escapeHtml(amount) + '"></div>' +
                        '<div class="col-md-8"><input type="datetime-local" class="form-control form-control-sm day-pay" value="' + escapeHtml(entry.pay_at) + '"></div></div>';
                }
                html += '</div>';
            });
            card.innerHTML = html;
            box.appendChild(card);
        });
    }

    function writeDay(key, date, changes) {
        var row = tbody.querySelector('tr[data-key="' + key + '"]');
        if (!row) return;
        var input = row.querySelector('.crew-dates');
        var entries = readEntries(input.value, true);
        entries.forEach(function (entry) {
            if (entry.date !== date) return;
            Object.keys(changes).forEach(function (name) { entry[name] = changes[name]; });
        });
        input.value = JSON.stringify(entries);
        input.setAttribute('data-auto', '0');
    }

    function renderPop() {
        var input = popState.input;
        if (!input) return;
        var selected = entryDates(input);
        var from = dateOnly(document.getElementById('event-from').value);
        var to = dateOnly(document.getElementById('event-to').value);
        var names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var first = new Date(popState.year, popState.month, 1);
        var startPad = first.getDay();
        var daysInMonth = new Date(popState.year, popState.month + 1, 0).getDate();
        var html = '<div class="ev-popcal-head"><button type="button" data-nav="-1">‹</button><strong>' + months[popState.month] + ' ' + popState.year + '</strong><button type="button" data-nav="1">›</button></div><div class="ev-popcal-grid">';
        names.forEach(function (name) { html += '<span>' + name + '</span>'; });
        var cell = 0;
        for (var pad = 0; pad < startPad; pad++) { html += '<span></span>'; cell++; }
        for (var day = 1; day <= daysInMonth; day++) {
            var date = isoDate(new Date(popState.year, popState.month, day));
            var classes = '';
            if (selected.indexOf(date) !== -1) classes += ' is-on';
            if (from && to && date >= from && date <= to) classes += ' is-event';
            html += '<button type="button" data-date="' + date + '" class="' + classes + '">' + day + '</button>';
        }
        html += '</div><div class="small text-muted mt-2">Click a day to select it. Click it again to clear it.</div>';
        pop.innerHTML = html;
    }

    function openPop(button, input) {
        var dates = entryDates(input);
        var seed = dates[0] || dateOnly(document.getElementById('event-from').value) || isoDate(new Date());
        var parts = seed.split('-');
        popState.input = input;
        popState.year = parseInt(parts[0], 10);
        popState.month = parseInt(parts[1], 10) - 1;
        renderPop();
        var rect = button.getBoundingClientRect();
        var left = Math.min(rect.left, window.innerWidth - 316);
        pop.style.left = Math.max(8, left) + 'px';
        pop.style.top = Math.min(rect.bottom + 8, window.innerHeight - 360) + 'px';
        pop.hidden = false;
    }

    function toggleDate(date) {
        if (!popState.input) return;
        if (popState.input.id === 'setup-dates' || popState.input.id === 'unpack-dates' || popState.input.classList.contains('crew-dates')) {
            var rich = popState.input.classList.contains('crew-dates');
            var entries = readEntries(popState.input.value, rich);
            var found = -1;
            entries.forEach(function (entry, index) { if (entry.date === date) found = index; });
            if (found === -1) {
                var added = {date: date, note: ''};
                if (rich) { added.description = ''; added.pay_amount = ''; added.pay_at = ''; }
                entries.push(added);
            } else {
                entries.splice(found, 1);
            }
            entries.sort(function (a, b) { return a.date < b.date ? -1 : 1; });
            popState.input.value = JSON.stringify(entries);
            if (rich) popState.input.setAttribute('data-auto', '0');
        } else {
            var dates = readDates(popState.input.value);
            var index = dates.indexOf(date);
            if (index === -1) dates.push(date); else dates.splice(index, 1);
            dates.sort();
            popState.input.value = JSON.stringify(dates);
            popState.input.setAttribute('data-auto', '0');
        }
        renderPop();
        refresh();
    }

    document.getElementById('setup-dates').value = JSON.stringify(readSetup(initialSetup || []));
    document.getElementById('unpack-dates').value = JSON.stringify(readSetup(initialUnpack || []));
    function bindDateNotes(notesId, inputId) {
        document.getElementById(notesId).addEventListener('input', function (event) {
            var field = event.target.closest('.date-note');
            if (!field) return;
            var input = document.getElementById(inputId);
            var entries = readSetup(input.value);
            entries.forEach(function (entry) {
                if (entry.date === field.getAttribute('data-date')) entry.note = field.value;
            });
            input.value = JSON.stringify(entries);
        });
    }
    bindDateNotes('setup-notes', 'setup-dates');
    bindDateNotes('unpack-notes', 'unpack-dates');
    document.getElementById('day-panels').addEventListener('input', function (event) {
        var block = event.target.closest('[data-key][data-date]');
        if (!block) return;
        var changes = {};
        if (event.target.classList.contains('day-note')) changes.note = event.target.value;
        if (event.target.classList.contains('day-description')) changes.description = event.target.value;
        if (event.target.classList.contains('day-amount')) changes.pay_amount = event.target.value;
        if (event.target.classList.contains('day-pay')) changes.pay_at = event.target.value;
        if (!Object.keys(changes).length) return;
        writeDay(block.getAttribute('data-key'), block.getAttribute('data-date'), changes);
    });
    document.getElementById('day-panels').addEventListener('click', function (event) {
        var button = event.target.closest('.day-auto-apply');
        if (!button) return;
        var wrap = button.closest('.border');
        var time = wrap ? wrap.querySelector('.day-auto-time') : null;
        var when = time ? time.value : '';
        if (!when) return;
        var row = tbody.querySelector('tr[data-key="' + button.getAttribute('data-key') + '"]');
        if (!row) return;
        var input = row.querySelector('.crew-dates');
        var rate = parseInt(row.querySelector('.crew-rate').value, 10) || 0;
        var food = parseInt(row.querySelector('.crew-food').value, 10) || 0;
        var entries = readEntries(input.value, true);
        entries.forEach(function (entry) {
            entry.pay_at = when;
            if (entry.pay_amount === '') entry.pay_amount = String(rate + food);
        });
        input.value = JSON.stringify(entries);
        input.setAttribute('data-auto', '0');
        paintDays();
    });
    document.getElementById('setup-open').addEventListener('click', function () {
        openPop(this, document.getElementById('setup-dates'));
    });
    document.getElementById('unpack-open').addEventListener('click', function () {
        openPop(this, document.getElementById('unpack-dates'));
    });
    document.getElementById('unpack-chips').addEventListener('click', function (event) {
        var chip = event.target.closest('.ev-chip');
        if (!chip) return;
        popState.input = document.getElementById('unpack-dates');
        toggleDate(chip.getAttribute('data-date'));
        if (pop.hidden) refresh();
    });
    document.getElementById('setup-chips').addEventListener('click', function (event) {
        var chip = event.target.closest('.ev-chip');
        if (!chip) return;
        popState.input = document.getElementById('setup-dates');
        toggleDate(chip.getAttribute('data-date'));
        if (pop.hidden) refresh();
    });
    tbody.addEventListener('click', function (event) {
        var opener = event.target.closest('.crew-days-btn');
        if (opener) {
            event.preventDefault();
            openPop(opener, opener.parentNode.querySelector('.crew-dates'));
            return;
        }
        var chip = event.target.closest('.crew-date-chips .ev-chip');
        if (!chip) return;
        var row = chip.closest('tr');
        popState.input = row.querySelector('.crew-dates');
        toggleDate(chip.getAttribute('data-date'));
    });
    pop.addEventListener('click', function (event) {
        var nav = event.target.closest('[data-nav]');
        if (nav) {
            var next = popState.month + parseInt(nav.getAttribute('data-nav'), 10);
            var moved = new Date(popState.year, next, 1);
            popState.year = moved.getFullYear();
            popState.month = moved.getMonth();
            renderPop();
            return;
        }
        var day = event.target.closest('[data-date]');
        if (day) toggleDate(day.getAttribute('data-date'));
    });
    document.addEventListener('click', function (event) {
        if (event.target.closest('.ev-popcal') || event.target.closest('.crew-days-btn') || event.target.closest('#setup-open') || event.target.closest('#unpack-open') || event.target.closest('.ev-chip')) return;
        pop.hidden = true;
    });

    document.getElementById('event-form').addEventListener('submit', function (event) {
        var formError = document.getElementById('form-error');
        formError.style.display = 'none';
        if (!roles.length) return;
        var missing = false;
        tbody.querySelectorAll('.crew-role').forEach(function (input) {
            var button = input.parentNode.querySelector('.ev-role-btn');
            if (!input.value) {
                missing = true;
                if (button) button.classList.add('is-invalid');
            }
        });
        if (missing) {
            formError.textContent = 'A person has no role yet. The event will still be created, and the settings price applies once a role is chosen.';
            formError.style.display = '';
        }
    });

    (oldCrew || []).forEach(function (row) {
        if (!row || !row.kind) return;
        addRow({
            kind: row.kind,
            ref: row.ref || row.phone || '',
            name: row.name || row.phone || '',
            phone: row.phone || '',
            rate: row.rate || 0,
            days: row.days || spanDays(),
            food: parseInt(row.food, 10) || 0,
            category_id: row.category_id || '',
            assignment_id: row.assignment_id || '',
            work_dates: row.work_dates || ''
        });
    });
    paintSetup();
    paintUnpack();
    paintDays();
})();
</script>
@endsection
