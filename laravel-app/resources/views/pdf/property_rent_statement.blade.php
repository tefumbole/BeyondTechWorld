<!DOCTYPE html>
<html>
<body>
<p>Rent statement for tenancy {{ $tenancy->id }}</p>
@foreach($rows as $row)
<p>{{ $row->period_key }} due {{ $row->due_date }} {{ $row->amount_due }} paid {{ $row->amount_paid }} {{ $row->status }}</p>
@endforeach
@foreach($payments as $payment)
<p>Payment {{ $payment->id }} {{ $payment->amount }} {{ $payment->paid_on }}</p>
@endforeach
</body>
</html>
