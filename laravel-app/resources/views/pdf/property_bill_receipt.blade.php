<!DOCTYPE html>
<html>
<body>
<p>Bill receipt {{ $bill->id }}</p>
<p>{{ $bill->bill_category }} {{ $bill->amount }} {{ $bill->currency }}</p>
<p>Status {{ $bill->status }}</p>
</body>
</html>
