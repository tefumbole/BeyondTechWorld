@extends('layout.main')

@section('content')
@include('events.partials.modern')
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-create-hero">
            <div>
                <div class="ev-kicker">Events</div>
                <h2>Create event</h2>
                <p class="ev-lead">Set the dates and the engineers. Notes go to the staff. The client is not notified.</p>
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

        <form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data" id="event-form">
            @csrf
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
                        <input type="date" name="event_start_at" id="event-from" class="form-control" required value="{{ old('event_start_at') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>To <span class="text-danger">*</span></label>
                        <input type="date" name="event_end_at" id="event-to" class="form-control" required value="{{ old('event_end_at') }}">
                    </div>
                </div>
                <div class="ev-duration-note" id="span-note">Choose the first and last date. Each person's days start from that span.</div>
                <div class="form-group mb-0">
                    <label>Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="3" placeholder="Schedule notes, call times, or anything to remember about these dates">{{ old('internal_notes') }}</textarea>
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
                <p class="ev-lead mt-2">Search covers customers, users, staff, interns, and every other account. Choosing a name adds them at once. Their role stays for the next event, and the settings price fills the daily rate.</p>
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
                                <th>Days</th>
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
                    <input type="checkbox" name="publish_on_website" value="1" {{ old('publish_on_website') ? 'checked' : '' }}>
                    Publish on the website
                </label>
            </div>

            <div class="ev-actions">
                <button type="submit" class="btn btn-primary btn-lg">Create event</button>
                <a href="{{ route('events.index') }}" class="btn btn-light btn-lg">Cancel</a>
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
    var oldCrew = @json(old('crew', []));
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
            note.textContent = 'Choose the first and last date. Each person\'s days start from that span.';
            return 1;
        }
        var ms = Date.parse(to) - Date.parse(from);
        var days = ms < 0 || isNaN(ms) ? 1 : Math.floor(ms / 86400000) + 1;
        note.textContent = days + (days === 1 ? ' day' : ' days') + ', from ' + prettyDate(from) + ' to ' + prettyDate(to) + '.';
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

    function refresh() {
        var total = 0;
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (row) {
            var days = parseInt(row.querySelector('.crew-days').value, 10) || 0;
            var rate = parseInt(row.querySelector('.crew-rate').value, 10) || 0;
            var food = parseInt(row.querySelector('.crew-food').value, 10) || 0;
            var line = (rate + food) * days;
            row.querySelector('.crew-line').textContent = money(line);
            total += line;
        });
        document.getElementById('labour-total').textContent = money(total);
        document.getElementById('crew-empty').style.display = rows.length ? 'none' : '';
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
            note.textContent = 'Settings price';
            rememberRole(row, role.id);
        } else {
            note.textContent = 'Choose a role';
        }
        refresh();
    }

    function addRow(person) {
        var key = person.kind + ':' + person.ref;
        if (tbody.querySelector('tr[data-key="' + key + '"]')) return;
        var i = rowIndex++;
        var days = person.days || spanDays();
        var role = roleById(person.category_id);
        var rate = role ? role.rate : (parseInt(person.rate, 10) || 0);
        var tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.setAttribute('data-kind', person.kind);
        tr.setAttribute('data-ref', person.ref || '');
        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm crew-name" name="crew[' + i + '][name]" value="' + escapeHtml(person.name || '') + '" required>' +
                '<div class="small text-muted">' + escapeHtml(person.phone || '') + '</div>' +
                '<input type="hidden" name="crew[' + i + '][kind]" value="' + escapeHtml(person.kind) + '">' +
                '<input type="hidden" name="crew[' + i + '][ref]" value="' + escapeHtml(person.ref || '') + '">' +
                '<input type="hidden" name="crew[' + i + '][phone]" value="' + escapeHtml(person.phone || '') + '">' +
            '</td>' +
            '<td><div class="ev-role">' +
                '<input type="hidden" class="crew-role" name="crew[' + i + '][category_id]" value="' + escapeHtml(person.category_id || '') + '">' +
                '<button type="button" class="ev-role-btn' + (role ? '' : ' is-empty') + '">' + escapeHtml(roleLabel(role)) + '</button>' +
            '</div></td>' +
            '<td><input type="number" class="form-control form-control-sm crew-days" name="crew[' + i + '][days]" min="1" max="365" value="' + days + '" data-auto="' + (person.days ? '0' : '1') + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm crew-rate" name="crew[' + i + '][rate]" min="0" value="' + rate + '" readonly required>' +
                '<span class="ev-rate-note">' + (role ? 'Settings price' : 'Choose a role') + '</span></td>' +
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
        var days = spanDays();
        tbody.querySelectorAll('.crew-days').forEach(function (input) {
            if (input.getAttribute('data-auto') === '1') input.value = days;
        });
        refresh();
    }
    document.getElementById('event-from').addEventListener('change', applySpan);
    document.getElementById('event-to').addEventListener('change', applySpan);
    spanDays();

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
            category_id: row.category_id || ''
        });
    });
})();
</script>
@endsection
