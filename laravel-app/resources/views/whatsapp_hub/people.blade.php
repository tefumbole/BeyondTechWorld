@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">People I know</h1>
        <p class="wa-sub">Choose a contact and tell the assistant who they are, what you call them, and which language they speak. The name saved on WhatsApp can be different.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <form method="get" action="{{ route('whatsapp.people') }}" class="mb-3" style="max-width:520px;">
            <div class="input-group">
                <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Search by name or phone">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </div>
        </form>
        <div class="row">
            <div class="col-md-5">
                <div class="wa-card">
                    @if($contacts->count())
                    <form method="post" action="{{ route('whatsapp.people.delete') }}" id="peopleDeleteForm">
                        @csrf
                        <input type="hidden" name="q" value="{{ $q }}">
                        <input type="hidden" name="all" id="peopleAllMatching" value="0">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                            <label style="margin:0; font-weight:600;">
                                <input type="checkbox" id="peopleAll"> Select all
                            </label>
                            <button type="submit" class="btn btn-danger btn-sm">Delete selected</button>
                        </div>
                        @foreach($contacts as $row)
                            <div class="mb-2" style="display:flex; gap:10px; align-items:flex-start;">
                                <input type="checkbox" class="people-row" name="ids[]" value="{{ $row->id }}" style="margin-top:4px;">
                                <div>
                                    <a href="{{ route('whatsapp.people', array_filter(['q' => $q, 'contact' => $row->id])) }}">
                                        <strong>{{ $row->displayName() }}</strong>
                                    </a>
                                    <div class="small text-muted">{{ $row->display_phone ?: $row->normalized_phone }}
                                        @if($row->call_name) · you call them {{ $row->call_name }}@endif
                                        @if($row->relationship) · {{ $row->relationship }}@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </form>
                    @else
                        <p class="text-muted mb-0">No contacts match.</p>
                    @endif
                    <div class="mt-3">{{ $contacts->appends(['q' => $q])->links() }}</div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="wa-card">
                    @if($selected)
                        <h5>{{ $selected->displayName() }}</h5>
                        <div class="small text-muted mb-2">{{ $selected->display_phone ?: $selected->normalized_phone }}</div>
                        @include('whatsapp_hub.partials.contact_voice', ['contact' => $selected])
                    @else
                        <p class="text-muted mb-0">Select a contact to tell the assistant who they are.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    $("ul#whatsapp-module").siblings('a').attr('aria-expanded','true');
    $("ul#whatsapp-module").addClass("show");
    $("#whatsapp-people-menu").addClass("active");
    (function () {
        var form = document.getElementById('peopleDeleteForm');
        if (!form) return;
        var allBox = document.getElementById('peopleAll');
        var allMatching = document.getElementById('peopleAllMatching');
        var total = {{ (int) $contacts->total() }};
        function rows() { return form.querySelectorAll('.people-row'); }
        if (allBox) {
            allBox.addEventListener('change', function () {
                rows().forEach(function (box) { box.checked = allBox.checked; });
                allMatching.value = allBox.checked ? '1' : '0';
            });
        }
        rows().forEach(function (box) {
            box.addEventListener('change', function () {
                if (!box.checked && allBox) {
                    allBox.checked = false;
                    allMatching.value = '0';
                }
            });
        });
        form.addEventListener('submit', function (event) {
            var chosen = form.querySelectorAll('.people-row:checked').length;
            if (allMatching.value === '1') {
                if (!confirm('Delete all ' + total + ' contact' + (total === 1 ? '' : 's') + ' in this list?')) {
                    event.preventDefault();
                }
                return;
            }
            if (!chosen) {
                event.preventDefault();
                alert('Select at least one contact.');
                return;
            }
            if (!confirm('Delete ' + chosen + ' contact' + (chosen === 1 ? '' : 's') + '?')) {
                event.preventDefault();
            }
        });
    })();
</script>
@endsection
