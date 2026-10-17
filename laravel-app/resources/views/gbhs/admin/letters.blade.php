@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Letters & WhatsApp groups</h3>
    @include('gbhs.admin.nav')
    <p>Letters are written in the existing Letters module and use the GBHS letter organization. This screen sends one of those letters to WhatsApp groups that already end with @g.us.</p>
    <form class="card p-3" method="post" action="{{ route('gbhs.groups.preview') }}">
        @csrf
        <label>Letter</label>
        <select class="form-control" name="letter_id" required>
            @foreach($letters as $letter)
                <option value="{{ $letter->id }}">{{ $letter->reference }} · {{ $letter->subject }}</option>
            @endforeach
        </select>
        <label class="mt-2">Groups</label>
        @forelse($groups as $group)
            <label><input type="checkbox" name="jids[]" value="{{ $group->whatsapp_group_jid }}"> {{ $group->name }} · {{ $group->whatsapp_group_jid }}</label>
        @empty
            <p>No batch has a WhatsApp group id yet.</p>
        @endforelse
        <button class="btn btn-primary btn-sm" type="submit">Preview</button>
    </form>
    <h5 class="mt-3">Deliveries</h5>
    @foreach($deliveries as $delivery)
        <div>{{ $delivery->target }} · letter {{ $delivery->letter_id }} · {{ $delivery->status }} {{ $delivery->error }}</div>
    @endforeach
</div></section>
@endsection
