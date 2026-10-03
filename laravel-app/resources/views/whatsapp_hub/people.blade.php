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
                    @forelse($contacts as $row)
                        <div class="mb-2">
                            <a href="{{ route('whatsapp.people', array_filter(['q' => $q, 'contact' => $row->id])) }}">
                                <strong>{{ $row->displayName() }}</strong>
                            </a>
                            <div class="small text-muted">{{ $row->display_phone ?: $row->normalized_phone }}
                                @if($row->call_name) · you call them {{ $row->call_name }}@endif
                                @if($row->relationship) · {{ $row->relationship }}@endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No contacts match.</p>
                    @endforelse
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
</script>
@endsection
