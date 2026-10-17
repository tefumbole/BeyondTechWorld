@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Confirm group send</h3>
    @include('gbhs.admin.nav')
    <div class="card p-3">
        <p>{{ $letter->reference }} · {{ $letter->subject }}</p>
        @forelse($jids as $jid)
            <div>{{ $jid }}</div>
        @empty
            <p>Nothing new to send. Those groups already have a sent row for this letter, or the id is not a WhatsApp group.</p>
        @endforelse
    </div>
    @if(count($jids))
    <form method="post" action="{{ route('gbhs.groups.confirm') }}">
        @csrf
        <input type="hidden" name="confirm" value="1">
        <button class="btn btn-primary" type="submit">Send now</button>
    </form>
    @endif
</div></section>
@endsection
