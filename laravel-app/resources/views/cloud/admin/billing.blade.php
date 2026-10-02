<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Beyond Cloud payments</title>
</head>
<body>
<section>
    <div class="container-fluid">
        <h3>Beyond Cloud payments</h3>
        <form method="GET" action="{{ route('cloud.admin.billing') }}">
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['PAID','PENDING','FAILED','RECONCILE','REFUNDED'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            <input name="provider" value="{{ request('provider') }}" placeholder="provider">
            <input name="tenant" value="{{ request('tenant') }}" placeholder="tenant id">
            <button type="submit">Filter</button>
        </form>
        <table class="table">
            <thead>
            <tr>
                <th>Date</th><th>Tenant</th><th>Amount</th><th>Provider</th><th>Status</th><th>Source</th><th>Reference</th>
            </tr>
            </thead>
            <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->created_at }}</td>
                    <td>{{ $payment->cloud_tenant_id }}</td>
                    <td>{{ $payment->amount }} {{ $payment->currency }}</td>
                    <td>{{ $payment->provider }}</td>
                    <td>{{ $payment->status }}</td>
                    <td>{{ $payment->confirmation_source }}</td>
                    <td>{{ $payment->internal_reference }} {{ $payment->provider_reference }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
</body>
</html>
