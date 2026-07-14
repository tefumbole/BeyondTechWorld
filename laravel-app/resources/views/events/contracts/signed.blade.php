<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contract Signed — {{ $contract->reference_no }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5 text-center" style="max-width:600px">
    <h3 class="text-success">✓ Contract signed</h3>
    <p>{{ $contract->title }}</p>
    <p class="text-muted">Status: {{ $contract->statusLabel() }}</p>
    @if($contract->signed_pdf_path)
        <a href="{{ url($contract->signed_pdf_path) }}" class="btn btn-primary" target="_blank">Download PDF</a>
    @endif
</div>
</body>
</html>
