@extends('gbhs.public.layout')
@section('content')
<h1>Donate</h1>
<form class="card" method="post" action="{{ route('gbhs.donate.store') }}">
    @csrf
    <label>Name</label><input name="donor_name" required>
    <label>Amount, XAF</label><input name="amount" type="number" min="100" required>
    <label>Method</label>
    <select name="method" required>
        @foreach($methods as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    <label>Mobile Money number</label><input name="phone">
    <label>Email</label><input name="email" type="email">
    <label>Project</label>
    <select name="project_id"><option value="">General</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->title }}</option>@endforeach</select>
    <label>Batch</label>
    <select name="batch_id"><option value="">Not linked</option>@foreach($batches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }}</option>@endforeach</select>
    <label>Note</label><input name="message">
    <label><input type="checkbox" name="anonymous" value="1" style="width:auto"> Do not show my name</label>
    <button type="submit">Continue</button>
</form>
<p class="muted">A Mobile Money or card payment stays pending until the provider confirms it. A bank transfer is recorded by the association after the money arrives.</p>
@endsection
