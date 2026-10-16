<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>body { font-family: DejaVu Sans, sans-serif; font-size: 12px; } h1 { font-size: 18px; }</style>
</head><body>
<h1>{{ $preset->name }}</h1>
<p>{{ $preset->sourceName() }} · {{ optional($preset->instrument)->name }} · {{ optional($preset->console)->name }}</p>
<p>Version {{ $version->version_number }} — {{ $version->label }}</p>
@include('mix.admin.version_summary', ['version' => $version])
<p>{{ $preset->notes }}</p>
<p>Reference settings only.</p>
</body></html>
