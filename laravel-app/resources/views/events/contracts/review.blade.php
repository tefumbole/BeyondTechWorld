@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h4>Review contract {{ $contract->reference_no }}</h4>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-3"><div class="card-body">{!! $contract->rendered_body !!}</div></div>
    @if($contract->worker_signed_at)
        <p class="text-success">Worker signed: {{ $contract->worker_signed_at->format('d M Y H:i') }}</p>
    @endif
    <form method="POST" action="{{ route('events.contracts.approve', $contract->id) }}">
        @csrf
        <label>Admin countersignature (optional)</label>
        <canvas id="sig" width="500" height="120" style="border:1px solid #ccc;background:#fff"></canvas>
        <input type="hidden" name="admin_signature" id="admin_signature">
        <div class="mt-3">
            <button type="button" class="btn btn-light" onclick="pad.clear()">Clear</button>
            <button type="submit" class="btn btn-success" onclick="capture()">Approve & generate PDF</button>
            <a href="{{ route('events.show', ['id'=>$contract->event_id,'tab'=>'contracts']) }}" class="btn btn-secondary">Back</a>
        </div>
    </form>
</div></section>
@endsection
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>var pad=new SignaturePad(document.getElementById('sig'));function capture(){if(!pad.isEmpty())document.getElementById('admin_signature').value=pad.toDataURL('image/png');}</script>
@endsection
