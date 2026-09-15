<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Wealth Manager Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2a44; }
        h1 { color: #0b3f90; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #e3e9f4; padding: 6px; text-align: left; }
        th { background: #f3f6fb; }
    </style>
</head>
<body>
    <h1>Wealth Manager Report</h1>
    <p>{{ $filter->startDate }} to {{ $filter->endDate }}</p>
    <p>Income {{ number_format($data['income'], 2) }} · Expenses {{ number_format($data['expenses'], 2) }} · Balance {{ number_format($data['balance'], 2) }}</p>
    <p>Financial Health {{ $data['health']['overall_score'] }} / 100 — {{ $data['health']['status_label'] }} (internal discipline score, not a credit score)</p>
    <h3>70 / 20 / 10</h3>
    <table>
        <tr><th>Bucket</th><th>Expected</th><th>Used</th><th>Remaining</th></tr>
        @foreach($data['allocation']['rows'] as $row)
            <tr>
                <td>{{ optional($row['bucket'])->label }}</td>
                <td>{{ number_format($row['expected'], 2) }}</td>
                <td>{{ number_format($row['used'], 2) }}</td>
                <td>{{ number_format($row['remaining'], 2) }}</td>
            </tr>
        @endforeach
    </table>
    @if(!empty($data['health']['recommendations']))
        <h3>Recommendations</h3>
        <ul>@foreach($data['health']['recommendations'] as $r)<li>{{ $r }}</li>@endforeach</ul>
    @endif
</body>
</html>
