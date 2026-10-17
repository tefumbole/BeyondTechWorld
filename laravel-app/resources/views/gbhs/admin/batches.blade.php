@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Batches & Representatives</h3>
    @include('gbhs.admin.nav')
    @foreach($batches as $batch)
        <form class="card p-3 mb-2" method="post" action="{{ route('gbhs.batches.update', $batch->id) }}">
            @csrf
            <strong>{{ $batch->year ?: 'Unknown' }}</strong>
            <input class="form-control mt-2" name="name" value="{{ $batch->name }}">
            <select class="form-control mt-2" name="status">
                @foreach(['unconfirmed','represented','active'] as $status)
                    <option value="{{ $status }}" {{ $batch->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <input class="form-control mt-2" name="representative_name" value="{{ $batch->representative_name }}" placeholder="Representative">
            <input class="form-control mt-2" name="representative_phone" value="{{ $batch->representative_phone }}" placeholder="Phone">
            <input class="form-control mt-2" name="representative_email" value="{{ $batch->representative_email }}" placeholder="Email">
            <input class="form-control mt-2" name="contribution_target" value="{{ $batch->contribution_target }}" placeholder="Target XAF">
            <input class="form-control mt-2" name="whatsapp_group_jid" value="{{ $batch->whatsapp_group_jid }}" placeholder="WhatsApp group id, ends with @g.us">
            <textarea class="form-control mt-2" name="notes">{{ $batch->notes }}</textarea>
            <select class="form-control mt-2" name="user_id">
                <option value="">Link a staff user to this batch</option>
                @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </select>
            <button class="btn btn-primary btn-sm mt-2" type="submit">Save</button>
        </form>
    @endforeach
</div></section>
@endsection
