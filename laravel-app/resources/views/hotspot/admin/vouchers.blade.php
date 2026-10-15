@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Vouchers</h3>
    @include('hotspot.admin.nav')
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('hotspot.vouchers.store') }}">
            @csrf
            <div class="form-inline">
                <select class="form-control mr-2" name="package_id" required>
                    <option value="">Package</option>
                    @foreach($packages as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach
                </select>
                <input class="form-control mr-2" type="number" min="1" max="200" name="quantity" value="10" required>
                <input class="form-control mr-2" name="note" placeholder="Batch note">
                <button class="btn btn-primary" type="submit">Create codes</button>
            </div>
        </form>
        <a class="d-inline-block mt-2" href="{{ route('hotspot.vouchers.export') }}">Export CSV</a>
    </div>
    @forelse($batches as $batch)
        <div><a href="{{ route('hotspot.vouchers.print', $batch->id) }}">Batch {{ $batch->id }}</a> · {{ $batch->quantity }} · {{ optional($batch->package)->name }}</div>
    @empty
        <div class="alert alert-info">No voucher batches yet.</div>
    @endforelse
    <table class="table table-bordered mt-3">
        <thead><tr><th>Code</th><th>State</th><th>Package</th><th></th></tr></thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row->code }}</td>
                <td>{{ $row->state }}</td>
                <td>{{ optional($row->package)->name }}</td>
                <td>
                    @if($row->state !== 'revoked')
                        <form method="POST" action="{{ route('hotspot.vouchers.revoke', $row->id) }}">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></section>
@endsection
