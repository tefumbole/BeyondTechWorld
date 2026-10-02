<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>WhatsApp capacity</title>
</head>
<body>
<h1>WhatsApp capacity</h1>
<p>Plan capacity: {{ $snapshot['limit'] === null ? 'Unknown' : $snapshot['limit'] }}</p>
<p>Beyond sessions: {{ $snapshot['beyond'] }}</p>
<p>Customer sessions: {{ $snapshot['customers'] }}</p>
<p>Provisioning: {{ $snapshot['provisioning'] }}</p>
<p>Reserved: {{ $snapshot['reserved'] }}</p>
<p>Available: {{ $snapshot['available'] === null ? 'Unknown' : $snapshot['available'] }}</p>
<table>
    <thead>
    <tr><th>Tenant</th><th>Company</th><th>Status</th><th>Provider</th><th>Phone</th><th>Session</th><th>Created</th><th>Health</th></tr>
    </thead>
    <tbody>
    @foreach($connections as $connection)
        @php $company = $tenants->get($connection->cloud_tenant_id); @endphp
        <tr>
            <td>{{ $connection->cloud_tenant_id }}</td>
            <td>{{ $company ? $company->name : '' }}</td>
            <td>{{ $connection->status }}</td>
            <td>{{ $connection->provider }}</td>
            <td>{{ $capacity->maskedPhone($connection->phone_number) }}</td>
            <td>{{ $connection->provider_connection_id }}</td>
            <td>{{ $connection->created_at }}</td>
            <td>{{ $connection->last_health_check_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
