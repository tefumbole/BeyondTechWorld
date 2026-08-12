@extends('layout.main')
@section('content')
<section class="container-fluid">
    <h3>Create self-request link</h3>
    <p class="text-muted">Guests open the link, enter their phone, and receive an invitation automatically.</p>
    <form method="POST" action="{{ route('online_invitation.request_links.store') }}" class="col-md-6 pl-0">
        @csrf
        <div class="form-group">
            <label>Event *</label>
            <select name="event_id" class="form-control" required>
                <option value="">Select event...</option>
                @foreach($events as $e)
                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Invitation type *</label>
            <select name="category_id" class="form-control" required>
                <option value="">Select type...</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Max uses (optional)</label>
            <input type="number" min="1" name="max_uses" class="form-control" placeholder="Unlimited">
        </div>
        <button class="btn btn-primary" type="submit">Create link</button>
        <a class="btn btn-link" href="{{ route('online_invitation.request_links.index') }}">Back</a>
    </form>
</section>
@endsection
