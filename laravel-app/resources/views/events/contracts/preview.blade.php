<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $contract->reference_no }}</title>
<style>body{font-family:Arial,sans-serif;padding:24px;max-width:800px;margin:0 auto}</style></head>
<body>
<h2>{{ $contract->title }}</h2>
<p><code>{{ $contract->reference_no }}</code> — {{ $contract->statusLabel() }}</p>
<hr>
{!! $contract->rendered_body !!}
</body></html>
