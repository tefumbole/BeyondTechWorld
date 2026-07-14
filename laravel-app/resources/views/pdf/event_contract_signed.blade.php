<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222}
.header{text-align:center;margin-bottom:16px;font-weight:bold}
.sig img{max-height:60px}
table{width:100%;margin-top:20px}
td{padding:8px;vertical-align:top;width:50%}
</style></head>
<body>
@if($general_setting && $general_setting->site_title)
    <div class="header">{{ $general_setting->site_title }}</div>
@endif
<h3>{{ $contract->title }}</h3>
<p>Reference: {{ $contract->reference_no }} · Event: {{ $contract->event->name }}</p>
<hr>
{!! $contract->rendered_body !!}
<table>
<tr>
<td class="sig">
<strong>Worker signature</strong><br>
@if($workerSigPath)<img src="{{ $workerSigPath }}">@endif<br>
{{ optional($contract->assignment->workerProfile)->displayName() }}<br>
{{ optional($contract->worker_signed_at)->format('d M Y H:i') }}
</td>
<td class="sig">
<strong>Company signature</strong><br>
@if($adminSigPath)<img src="{{ $adminSigPath }}">@endif<br>
{{ optional($contract->adminSigner)->name ?? 'Beyond Enterprise' }}<br>
{{ optional($contract->admin_signed_at)->format('d M Y H:i') }}
</td>
</tr>
</table>
</body>
</html>
