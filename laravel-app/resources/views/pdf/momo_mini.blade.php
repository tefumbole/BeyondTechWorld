<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 8px 10px 12px; font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #14211c; }
        .center { text-align: center; }
        .logo { height: 46px; }
        h1 { font-size: 12px; margin: 4px 0 0; }
        h2 { font-size: 11px; margin: 6px 0 0; }
        .rule { border-top: 1px dashed #8aa; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .label { font-weight: bold; width: 78px; }
        .right { text-align: right; }
        .total { font-size: 12px; font-weight: bold; }
        .sign { height: 42px; }
        .thanks { text-align: center; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="center">
        @if($logo)
            <img src="{{ $logo }}" class="logo" alt="">
        @endif
        <h1>{{ $title }}</h1>
        @if($phone)<div>{{ $phone }}</div>@endif
        <h2>Sales Invoice</h2>
    </div>
    <div class="rule"></div>
    <table>
        <tr><td class="label">Reference</td><td>{{ $reference }}</td></tr>
        <tr><td class="label">Date</td><td>{{ $date }}</td></tr>
        <tr><td class="label">Sale Status</td><td>Paid</td></tr>
        <tr><td class="label">Customer</td><td>{{ $name }}</td></tr>
        <tr><td class="label">Phone Number</td><td>{{ $clientPhone }}</td></tr>
        @if($city !== '' && $city !== 'N/A')
            <tr><td class="label">City</td><td>{{ $city }}</td></tr>
        @endif
    </table>
    <div class="rule"></div>
    <table>
        <tr><td colspan="2">{{ $reason }}</td></tr>
        <tr><td>1 × {{ $amount }}</td><td class="right">{{ $amount }}</td></tr>
        <tr><td>Tax</td><td class="right">0.00</td></tr>
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>Discount</td><td class="right">0.00</td></tr>
        <tr><td>Tax</td><td class="right">0.00</td></tr>
        <tr><td class="total">Total</td><td class="right total">{{ $amount }}</td></tr>
        <tr><td>Paying Amount</td><td class="right">{{ $amount }}</td></tr>
        <tr><td>Pending Amount</td><td class="right">0.00</td></tr>
        <tr><td>Mobile Money</td><td class="right">{{ $amount }}</td></tr>
        <tr><td>Payment Status</td><td class="right">Paid</td></tr>
    </table>
    @if($words !== '')
        <p>In Words: XAF {{ $words }}</p>
    @endif
    <p class="thanks">Thank you for shopping with us. Please come again.</p>
    <table>
        <tr>
            <td>
                <div>Created By</div>
                <div>{{ $admin }}</div>
                @if($signature)
                    <img src="{{ $signature }}" class="sign" alt="">
                @endif
            </td>
            <td class="right">Paid</td>
        </tr>
    </table>
</body>
</html>
