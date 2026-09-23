<!DOCTYPE html>
<html>
<body>
<p>Rent receipt {{ $payment->id }}</p>
<p>Tenancy {{ $payment->tenancy_id }}</p>
<p>{{ $payment->amount }} {{ $payment->currency }}</p>
<p>{{ $payment->paid_on }}</p>
</body>
</html>
