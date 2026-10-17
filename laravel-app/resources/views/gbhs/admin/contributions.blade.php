@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Contributions & Donations</h3>
    @include('gbhs.admin.nav')
    <p><a href="{{ route('gbhs.reconcile') }}">Reconciliation</a> · <a href="{{ route('gbhs.payments') }}">Live payments</a> · <a href="{{ route('gbhs.export') }}">CSV</a></p>
    <form class="card p-3" method="post" action="{{ route('gbhs.contributions.store') }}">
        @csrf
        <div class="row">
            <div class="col-md-3"><input class="form-control" name="contributor_name" placeholder="Name"></div>
            <div class="col-md-2">
                <select class="form-control" name="classification">
                    @foreach(['cash','bank','momo','card','pledge','in_kind','earmarked','logistics','adjustment','reversal'] as $class)
                        <option>{{ $class }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" name="amount" placeholder="Amount"></div>
            <div class="col-md-2"><input class="form-control" name="in_kind_qty" placeholder="Qty"></div>
            <div class="col-md-2"><input class="form-control" name="in_kind_value" placeholder="Stated value"></div>
        </div>
        <select class="form-control mt-2" name="batch_id"><option value="">Batch</option>@foreach($batches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }}</option>@endforeach</select>
        <input class="form-control mt-2" name="source_note" placeholder="Source note">
        <button class="btn btn-primary btn-sm mt-2" type="submit">Add unverified line</button>
    </form>
    @foreach($rows as $row)
        <form class="border-bottom py-2" method="post" action="{{ route('gbhs.contributions.verify', $row->id) }}" enctype="multipart/form-data">
            @csrf
            {{ $row->contributor_name }} · {{ $row->classification }} · {{ number_format($row->amount) }}
            @if($row->in_kind_qty) · qty {{ $row->in_kind_qty }} value {{ number_format($row->in_kind_value) }} @endif
            · {{ $row->verified ? 'verified' : 'unverified' }}
            · {{ $row->source_note }}
            <input type="file" name="receipt">
            <button class="btn btn-sm btn-success" name="decision" value="verify">Verify</button>
            <button class="btn btn-sm btn-outline-secondary" name="decision" value="cancel">Mark reversal</button>
        </form>
    @endforeach
</div></section>
@endsection
