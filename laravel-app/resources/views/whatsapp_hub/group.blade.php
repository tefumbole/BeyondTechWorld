@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p class="mb-2"><a href="{{ route('whatsapp.groups') }}">All groups</a></p>
        <h1 class="wa-title">{{ $groupName }}</h1>
        <p class="wa-sub">{{ number_format(count($contacts)) }} {{ count($contacts) === 1 ? 'contact' : 'contacts' }}. A name is the one on WhatsApp, then a name saved for that number, then the Cameroon mobile-money name.</p>
        @if(!empty($listError))<div class="alert alert-danger">{{ $listError }}</div>@endif
        <p class="mb-3">
            <input id="contact-filter" type="search" class="form-control" style="max-width:420px" placeholder="Search a name or phone number">
        </p>
        <div class="wa-card table-responsive">
            <table class="table" id="contact-table">
                <thead>
                    <tr><th>Name</th><th>Phone</th></tr>
                </thead>
                <tbody>
                @forelse($contacts as $contact)
                    @php
                        $name = trim((string) $contact['name']);
                        $phone = trim((string) $contact['phone']);
                    @endphp
                    <tr data-name="{{ $name }}" data-phone="{{ $phone }}">
                        <td class="contact-name">{{ $name !== '' ? $name : 'Looking up name…' }}</td>
                        <td class="contact-phone">{{ $phone !== '' ? $phone : '—' }}</td>
                    </tr>
                @empty
                    <tr class="contact-empty-group"><td colspan="2">No contacts were returned for this group.</td></tr>
                @endforelse
                    <tr id="contact-no-match" style="display:none"><td colspan="2">No contacts match that search.</td></tr>
                </tbody>
            </table>
        </div>
        <script>
            (function () {
                var input = document.getElementById('contact-filter');
                var table = document.getElementById('contact-table');
                var none = document.getElementById('contact-no-match');
                if (!input || !table) return;

                function rows() {
                    return Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-phone]'));
                }

                input.addEventListener('input', function () {
                    var q = input.value.toLowerCase().trim();
                    var qDigits = q.replace(/\D+/g, '');
                    var shown = 0;
                    rows().forEach(function (row) {
                        var name = (row.getAttribute('data-name') || '').toLowerCase();
                        var phone = (row.getAttribute('data-phone') || '').toLowerCase();
                        var phoneDigits = phone.replace(/\D+/g, '');
                        var hit = !q
                            || name.indexOf(q) !== -1
                            || phone.indexOf(q) !== -1
                            || (qDigits !== '' && phoneDigits.indexOf(qDigits) !== -1);
                        row.style.display = hit ? '' : 'none';
                        if (hit) shown++;
                    });
                    if (none) none.style.display = q && !shown ? '' : 'none';
                });

                var namesUrl = @json(route('whatsapp.groups.names', ['jid' => $jid]));
                function unnamed() {
                    return rows().filter(function (row) {
                        var label = row.querySelector('.contact-name');
                        return label && (label.textContent === 'Looking up name…' || label.textContent === 'No WhatsApp name');
                    });
                }
                function refreshNames() {
                    if (!unnamed().length) return;
                    fetch(namesUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (payload) {
                            var contacts = payload && payload.contacts ? payload.contacts : [];
                            contacts.forEach(function (contact) {
                                var match = rows().filter(function (row) {
                                    return (row.getAttribute('data-phone') || '') === (contact.phone || '');
                                })[0];
                                if (!match) return;
                                var label = match.querySelector('.contact-name');
                                if (!label) return;
                                if (contact.name) {
                                    label.textContent = contact.name;
                                    match.setAttribute('data-name', contact.name);
                                } else if (!contact.pending) {
                                    label.textContent = 'No name';
                                }
                            });
                            if (unnamed().length) setTimeout(refreshNames, 8000);
                        })
                        .catch(function () {
                            if (unnamed().length) setTimeout(refreshNames, 15000);
                        });
                }
                if (unnamed().length) setTimeout(refreshNames, 8000);
            })();
        </script>
    </div>
</section>
@endsection
