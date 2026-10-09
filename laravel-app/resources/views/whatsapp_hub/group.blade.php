@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p class="mb-2"><a href="{{ route('whatsapp.groups') }}">All groups</a></p>
        <h1 class="wa-title">{{ $groupName }}</h1>
        <p class="wa-sub">{{ number_format(count($contacts)) }} {{ count($contacts) === 1 ? 'contact' : 'contacts' }}. Select contacts to delete several at once. Fetch contacts adds only numbers that are not already in this group. Add puts a number into this group. Edit the name beside a number and save it. Exclude leaves someone off announcements and reminders.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if(!empty($listError))<div class="alert alert-danger">{{ $listError }}</div>@endif
        <p class="mb-3" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <form method="POST" action="{{ route('whatsapp.groups.fetch_contacts') }}" style="margin:0">
                @csrf
                <input type="hidden" name="jid" value="{{ $jid }}">
                <button type="submit" class="btn btn-default">Fetch contacts</button>
            </form>
            @if(count($contacts) > 0)
            <form method="POST" action="{{ route('whatsapp.groups.resolve') }}" style="margin:0" id="resolve-form">
                @csrf
                <input type="hidden" name="jid" value="{{ $jid }}">
                <button type="submit" class="btn btn-primary">Resolve all</button>
            </form>
            <form method="POST" action="{{ route('whatsapp.groups.remove_many') }}" style="margin:0" id="delete-selected-form" onsubmit="return confirm('Delete the selected contacts from this group? They stay out even when you fetch contacts again.');">
                @csrf
                <input type="hidden" name="jid" value="{{ $jid }}">
                <button type="submit" class="btn btn-danger" id="delete-selected" disabled>Delete selected</button>
            </form>
            @endif
        </p>
        <form method="POST" action="{{ route('whatsapp.groups.add') }}" class="mb-3" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;max-width:720px">
            @csrf
            <input type="hidden" name="jid" value="{{ $jid }}">
            <input type="text" name="phone" class="form-control" style="max-width:220px" placeholder="Phone number" required>
            <input type="text" name="name" class="form-control" style="max-width:260px" placeholder="Name (optional)">
            <button type="submit" class="btn btn-primary">Add to group</button>
        </form>
        <p class="mb-3">
            <input id="contact-filter" type="search" class="form-control" style="max-width:420px" placeholder="Search a name or phone number">
        </p>
        <div class="wa-card table-responsive">
            <table class="table" id="contact-table">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="contact-select-all" title="Select all"></th>
                        <th>Name</th><th>Phone</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($contacts as $contact)
                    @php
                        $name = trim((string) $contact['name']);
                        $phone = trim((string) $contact['phone']);
                        $excluded = ! empty($contact['excluded']);
                    @endphp
                    <tr data-name="{{ $name }}" data-phone="{{ $phone }}" @if($excluded) style="background:#f8f9fa" @endif>
                        <td><input type="checkbox" class="contact-select" name="phones[]" value="{{ $phone }}" form="delete-selected-form"></td>
                        <td>
                            <input class="form-control contact-name" value="{{ $name }}" placeholder="Name to show for this number" style="min-width:220px">
                            <small class="contact-save text-muted"></small>
                            @if($excluded)<div class="text-muted">Excluded from announcements and reminders</div>@endif
                        </td>
                        <td class="contact-phone">{{ $phone !== '' ? $phone : '—' }}</td>
                        <td style="white-space:nowrap">
                            <form method="POST" action="{{ route('whatsapp.groups.fetch_member') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="jid" value="{{ $jid }}">
                                <input type="hidden" name="phone" value="{{ $phone }}">
                                <button type="submit" class="btn btn-sm btn-info">Resolve</button>
                            </form>
                            <form method="POST" action="{{ route($excluded ? 'whatsapp.groups.include' : 'whatsapp.groups.exclude') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="jid" value="{{ $jid }}">
                                <input type="hidden" name="phone" value="{{ $phone }}">
                                <button type="submit" class="btn btn-sm {{ $excluded ? 'btn-default' : 'btn-warning' }}">{{ $excluded ? 'Include' : 'Exclude' }}</button>
                            </form>
                            <form method="POST" action="{{ route('whatsapp.groups.remove') }}" style="display:inline" onsubmit="return confirm('Delete this person from this group? They stay out even when you fetch contacts again.');">
                                @csrf
                                <input type="hidden" name="jid" value="{{ $jid }}">
                                <input type="hidden" name="phone" value="{{ $phone }}">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="contact-empty-group"><td colspan="4">No contacts were returned for this group.</td></tr>
                @endforelse
                    <tr id="contact-no-match" style="display:none"><td colspan="4">No contacts match that search.</td></tr>
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

                var selectAll = document.getElementById('contact-select-all');
                var deleteButton = document.getElementById('delete-selected');
                function selectedBoxes() {
                    return rows().filter(function (row) {
                        return row.style.display !== 'none';
                    }).map(function (row) {
                        return row.querySelector('.contact-select');
                    }).filter(Boolean);
                }
                function refreshDelete() {
                    if (!deleteButton) return;
                    var chosen = rows().filter(function (row) {
                        var box = row.querySelector('.contact-select');
                        return box && box.checked;
                    }).length;
                    deleteButton.disabled = chosen < 1;
                    deleteButton.textContent = chosen > 0 ? 'Delete selected (' + chosen + ')' : 'Delete selected';
                }
                rows().forEach(function (row) {
                    var box = row.querySelector('.contact-select');
                    if (box) box.addEventListener('change', refreshDelete);
                });
                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        selectedBoxes().forEach(function (box) { box.checked = selectAll.checked; });
                        refreshDelete();
                    });
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
                var saveUrl = @json(route('whatsapp.groups.display_name'));
                var groupJid = @json($jid);
                var token = document.querySelector('meta[name="csrf-token"]');
                function unnamed() {
                    return rows().filter(function (row) {
                        var label = row.querySelector('.contact-name');
                        return label && !label.value.trim() && row.getAttribute('data-edited') !== '1';
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
                                if (!match || match.getAttribute('data-edited') === '1') return;
                                var label = match.querySelector('.contact-name');
                                if (!label || document.activeElement === label || label.value.trim()) return;
                                if (contact.name) {
                                    label.value = contact.name;
                                    match.setAttribute('data-name', contact.name);
                                }
                            });
                            if (unnamed().length) setTimeout(refreshNames, 8000);
                            else {
                                var resolveForm = document.getElementById('resolve-form');
                                if (resolveForm) resolveForm.style.display = 'none';
                            }
                        })
                        .catch(function () {
                            if (unnamed().length) setTimeout(refreshNames, 15000);
                        });
                }
                if (unnamed().length) setTimeout(refreshNames, 8000);
                rows().forEach(function (row) {
                    var label = row.querySelector('.contact-name');
                    var note = row.querySelector('.contact-save');
                    if (!label) return;
                    label.addEventListener('change', function () {
                        var name = label.value.trim();
                        if (!name) {
                            if (note) note.textContent = 'Enter a name';
                            return;
                        }
                        if (note) note.textContent = 'Saving…';
                        fetch(saveUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                            },
                            body: JSON.stringify({
                                jid: groupJid,
                                phone: row.getAttribute('data-phone') || '',
                                name: name
                            })
                        }).then(function (response) { return response.json().then(function (payload) { return { ok: response.ok, payload: payload }; }); })
                          .then(function (result) {
                              if (!result.ok || !result.payload || !result.payload.ok) {
                                  if (note) note.textContent = (result.payload && result.payload.error) ? result.payload.error : 'Could not save';
                                  return;
                              }
                              label.value = result.payload.name;
                              row.setAttribute('data-name', result.payload.name);
                              row.setAttribute('data-edited', '1');
                              if (note) note.textContent = 'Saved. Messages will use this name.';
                          })
                          .catch(function () {
                              if (note) note.textContent = 'Could not save';
                          });
                    });
                });
            })();
        </script>
    </div>
</section>
@endsection
