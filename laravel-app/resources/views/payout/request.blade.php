@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Request for Payment</h2></div>
        <div class="pay-card-body">
            <p class="pay-help">Choose people from the system, or add a number. They receive a WhatsApp, in the name saved here, asking them to submit payment information. You can also copy the link.</p>
            <form method="POST" action="{{ route('payout.request.invite') }}" id="inviteForm">
                @csrf
                <div class="pay-search">
                    <label>People</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="search" id="inviteSearch" class="form-control" placeholder="Search by name or phone number" autocomplete="off">
                        <button class="pay-plus" type="button" id="inviteAddPhone" title="Add a number">+</button>
                    </div>
                    <div id="inviteHits" class="list-group" style="position:absolute;z-index:5;width:100%;max-height:240px;overflow:auto"></div>
                    <div id="invitePhoneRow" style="display:none;margin-top:8px">
                        <input type="tel" id="invitePhone" class="form-control" placeholder="Phone number, 6xxxxxxxx" autocomplete="off">
                    </div>
                </div>
                <div class="table-responsive" style="margin-top:14px">
                    <table class="pay-table">
                        <thead><tr><th>Name</th><th>Number</th><th></th></tr></thead>
                        <tbody id="inviteBody">
                            <tr id="inviteEmpty"><td colspan="3" class="pay-muted">No one selected yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="form-group" style="max-width:520px;margin-top:14px">
                    <label>Reason</label>
                    <input type="text" name="reason" class="form-control" maxlength="180" placeholder="Why this payment is being requested">
                </div>
                <button class="pay-go" type="submit">Send request</button>
            </form>
            <div class="pay-linkbox" style="margin-top:18px">
                <input type="text" id="requestUrl" class="form-control" readonly value="{{ $url }}">
                <button class="pay-go" type="button" id="copyLink">Copy link</button>
            </div>
        </div>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('inviteForm');
    var body = document.getElementById('inviteBody');
    var empty = document.getElementById('inviteEmpty');
    var hits = document.getElementById('inviteHits');
    var search = document.getElementById('inviteSearch');
    var phoneRow = document.getElementById('invitePhoneRow');
    var phoneInput = document.getElementById('invitePhone');
    var searchUrl = @json(route('payout.search'));
    var lookupUrl = @json(route('payout.lookup'));
    var added = {};
    function refresh() {
        empty.style.display = body.querySelectorAll('tr[data-key]').length ? 'none' : '';
    }
    function addPerson(key, name, phone, fields) {
        if (added[key]) return;
        added[key] = true;
        var row = document.createElement('tr');
        row.setAttribute('data-key', key);
        var nameCell = document.createElement('td');
        nameCell.textContent = name;
        var phoneCell = document.createElement('td');
        phoneCell.textContent = phone;
        fields.forEach(function (field) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            nameCell.appendChild(input);
        });
        var removeCell = document.createElement('td');
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-link text-danger';
        remove.textContent = 'Remove';
        remove.addEventListener('click', function () {
            delete added[key];
            row.remove();
            refresh();
        });
        removeCell.appendChild(remove);
        row.appendChild(nameCell);
        row.appendChild(phoneCell);
        row.appendChild(removeCell);
        body.appendChild(row);
        refresh();
    }
    var timer = null;
    search.addEventListener('input', function () {
        clearTimeout(timer);
        var q = search.value.trim();
        if (q.length < 1) {
            hits.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    hits.innerHTML = '';
                    (data.people || []).forEach(function (person) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = person.name + ' (' + person.phone + ')';
                        button.addEventListener('click', function () {
                            var idName = person.kind === 'user' ? 'user_id[]' : 'customer_id[]';
                            addPerson(person.kind + ':' + person.id, person.name, person.phone, [{name: idName, value: person.id}]);
                            hits.innerHTML = '';
                            search.value = '';
                        });
                        hits.appendChild(button);
                    });
                    if (!hits.children.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item text-muted';
                        none.textContent = 'No matching name or number';
                        hits.appendChild(none);
                    }
                });
        }, 200);
    });
    document.getElementById('inviteAddPhone').addEventListener('click', function () {
        phoneRow.style.display = 'block';
        phoneInput.focus();
    });
    var phoneWait = null;
    phoneInput.addEventListener('input', function () {
        clearTimeout(phoneWait);
        var raw = phoneInput.value.trim();
        if (raw.replace(/\D/g, '').length < 9) return;
        phoneWait = setTimeout(function () {
            fetch(lookupUrl + '?phone=' + encodeURIComponent(raw), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (info) {
                    if (!info || !info.ok) return;
                    var label = info.name ? info.name : info.phone;
                    addPerson('phone:' + info.phone, label, info.phone, [{name: 'extra_phone[]', value: info.phone}]);
                    phoneInput.value = '';
                });
        }, 400);
    });
    form.addEventListener('submit', function (event) {
        if (!body.querySelector('tr[data-key]')) event.preventDefault();
    });
    document.getElementById('copyLink').addEventListener('click', function () {
        var input = document.getElementById('requestUrl');
        var button = this;
        input.focus();
        input.select();
        var done = function () { button.textContent = 'Copied'; };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done);
            return;
        }
        document.execCommand('copy');
        done();
    });
})();
</script>
@endsection
