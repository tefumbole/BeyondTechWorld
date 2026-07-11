<h1>Payment Details</h1>
<p><strong>Sale Reference: </strong>{{$sale_reference}}</p>
<p><strong>Payment Reference: </strong>{{$payment_reference}}</p>
<p><strong>Payment Method: </strong>{{$payment_method}}</p>
<p><strong>Grand Total: </strong>{{number_format($grand_total, 2)}} {{$currency->code}}</p>
<p><strong>Paid Amount: </strong>{{number_format($paid_amount, 2)}} {{$currency->code}}</p>
<p><strong>Due: </strong>{{number_format((float)($grand_total - $paid_amount), 2)}} {{$currency->code}}</p>
<p>Thank You</p>
