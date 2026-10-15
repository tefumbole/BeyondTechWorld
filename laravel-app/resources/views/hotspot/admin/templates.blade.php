@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot messages</h3>
    @include('hotspot.admin.nav')
    <p>Placeholders: {company} {package} {amount} {username} {password} {expires} {support} {portal}. Each event sends one WhatsApp message.</p>
    @foreach($rows as $row)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('hotspot.templates.save', $row->id) }}">
                @csrf
                <strong>{{ $row->key }}</strong>
                @if($row->key === 'reminder')
                    <div class="mt-2"><label>Hours before expiry</label><input class="form-control" type="number" min="1" name="hours_before" value="{{ $row->hours_before }}"></div>
                @endif
                <textarea class="form-control mt-2" name="body" rows="5">{{ $row->body }}</textarea>
                <button class="btn btn-sm btn-primary mt-2" type="submit">Save</button>
            </form>
        </div>
    @endforeach
</div></section>
@endsection
