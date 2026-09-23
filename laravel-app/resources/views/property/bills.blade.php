@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Bill payment requests</h1>
        <div class="wa-card">
            <table class="table table-sm">
                <thead><tr><th>Id</th><th>Customer</th><th>Category</th><th>Status</th><th>Amount</th><th>Reference tail</th></tr></thead>
                <tbody>
                @foreach($requests as $row)
                    @php
                        $ref = (string) $row->account_reference;
                        $tail = $ref === '' ? '' : (strlen($ref) > 4 ? substr($ref, -4) : 'set');
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->customer_id }}</td>
                        <td>{{ $row->bill_category }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->amount }} {{ $row->currency }}</td>
                        <td>{{ $tail }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
