<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            padding: 22px 28px 26px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2a44;
        }
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: #0b3f90;
        }
        .logo { height: 48px; width: 48px; }
        .company { font-size: 20px; font-weight: bold; color: #0b3f90; line-height: 1.1; }
        .doctype { font-size: 11px; font-weight: bold; color: #0b3f90; letter-spacing: 0.6px; margin-top: 2px; }
        .paid {
            background: #c6ab47;
            color: #0b3f90;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.4px;
            padding: 7px 16px;
            border-radius: 16px;
        }
        .hero {
            background: #0b3f90;
            color: #ffffff;
            border-radius: 10px;
            padding: 14px 18px 12px;
            margin-top: 16px;
        }
        .kicker { color: #c6ab47; font-size: 10px; font-weight: bold; letter-spacing: 0.8px; }
        .figure { font-size: 28px; font-weight: bold; color: #ffffff; line-height: 1.15; }
        .xaf { color: #c6ab47; font-size: 13px; font-weight: bold; }
        .method { color: #ffffff; font-size: 12px; }
        .meta { width: 100%; margin-top: 12px; }
        .meta td { font-size: 11px; color: #1f2a44; padding: 0; }
        .card {
            background: #eef3fb;
            border-radius: 8px;
            padding: 10px 12px 8px;
        }
        .card-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #0b3f90;
        }
        .bar { color: #c6ab47; font-weight: bold; }
        .field { font-size: 11px; color: #1f2a44; padding-top: 4px; }
        .field strong { font-weight: bold; }
        .line { border-bottom: 1px solid #c6ab47; color: #c6ab47; }
        .reason {
            background: #fff6dd;
            border-radius: 8px;
            padding: 8px 12px 10px;
            margin-top: 10px;
        }
        .reason-label { font-size: 10px; font-weight: bold; letter-spacing: 0.6px; color: #0b3f90; }
        .items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .items th {
            background: #0b3f90;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.4px;
            padding: 8px 10px;
            text-align: left;
        }
        .items th.num, .items td.num { text-align: right; }
        .items td { padding: 8px 10px; border-bottom: 1px solid #e4e9f2; font-size: 12px; }
        .desc { font-weight: bold; }
        .total-row td { background: #e8eefb; font-weight: bold; font-size: 13px; border-bottom: 0; }
        .paid-row td { padding-top: 8px; border-bottom: 1px solid #d5dced; }
        .sign { width: 100%; margin-top: 16px; }
        .sign td { vertical-align: top; width: 33%; padding-right: 8px; }
        .sign .lbl { font-size: 10px; font-weight: bold; letter-spacing: 0.5px; color: #0b3f90; }
        .sign .who { margin-top: 4px; font-size: 12px; }
        .sign img { height: 46px; margin-top: 4px; }
        .thanks {
            background: #0b3f90;
            color: #c6ab47;
            text-align: center;
            font-size: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-top: 18px;
        }
    </style>
</head>
<body>
    <div class="topbar"></div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="74%">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-right:10px;vertical-align:middle;">
                            @if($logo)
                                <img src="{{ $logo }}" class="logo" alt="">
                            @endif
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="company">{{ $company }}</div>
                            <div class="doctype">SALES INVOICE</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td align="right" style="vertical-align:middle;">
                <span class="paid">PAID</span>
            </td>
        </tr>
    </table>

    <div class="hero">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="kicker">TOTAL PAID</div>
                    <div class="figure">{{ $amount }} &nbsp;<span class="xaf">XAF</span></div>
                </td>
                <td align="right" class="method" style="vertical-align:middle;">{{ $method }}</td>
            </tr>
        </table>
    </div>

    <table class="meta" cellpadding="0" cellspacing="0">
        <tr>
            <td>{{ $reference }}</td>
            <td align="right">{{ $date }}</td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
        <tr>
            <td width="49%" style="vertical-align:top;padding-right:8px;">
                <div class="card">
                    <div class="card-label"><span class="bar">|</span> {{ $partyLabel }}</div>
                    <div class="field">Name &nbsp; @if($partyName !== '')<strong>{{ $partyName }}</strong>@else<span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>@endif</div>
                    <div class="field">Phone &nbsp; @if($partyPhone !== ''){{ $partyPhone }}@else<span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>@endif</div>
                </div>
            </td>
            <td width="2%"></td>
            <td width="49%" style="vertical-align:top;padding-left:8px;">
                <div class="card">
                    <div class="card-label"><span class="bar">|</span> REQUESTED BY</div>
                    <div class="field">Name &nbsp; @if($requestedName !== '')<strong>{{ $requestedName }}</strong>@else<span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>@endif</div>
                    <div class="field">Phone &nbsp; @if($requestedPhone !== ''){{ $requestedPhone }}@else<span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>@endif</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="reason">
        <div class="reason-label">REASON</div>
        <div class="field">
            @if($reason !== '')
                {{ $reason }}
            @else
                <span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
            @endif
        </div>
    </div>

    <table class="items" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>DESCRIPTION</th>
                <th class="num" width="70">QTY</th>
                <th class="num" width="110">UNIT PRICE</th>
                <th class="num" width="110">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="desc">{{ $description }}</td>
                <td class="num">1</td>
                <td class="num">{{ $amount }}</td>
                <td class="num"><strong>{{ $amount }}</strong></td>
            </tr>
            <tr class="total-row">
                <td colspan="3">Invoice total</td>
                <td class="num">{{ $invoiceTotal ?: $amount }} XAF</td>
            </tr>
            <tr class="paid-row">
                <td colspan="3">Amount paid</td>
                <td class="num">{{ $amount }} XAF</td>
            </tr>
            @if($fee !== '')
            <tr class="paid-row">
                <td colspan="3">{{ $feeLabel }}</td>
                <td class="num">{{ $fee }} XAF</td>
            </tr>
            @endif
            @if($pending !== null)
            <tr class="paid-row">
                <td colspan="3">Pending</td>
                <td class="num">{{ $pending }} XAF</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="sign" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="lbl">CREATED BY</div>
                <div class="who">{{ $createdBy }}</div>
            </td>
            <td>
                <div class="lbl">APPROVED BY</div>
                <div class="who">{{ $approvedBy }}</div>
            </td>
            <td>
                <div class="lbl">SIGNATURE</div>
                @if($signature)
                    <img src="{{ $signature }}" alt="">
                @else
                    <div class="who"><span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></div>
                @endif
            </td>
        </tr>
    </table>

    <div class="thanks">Thank you for shopping with us.</div>
</body>
</html>
