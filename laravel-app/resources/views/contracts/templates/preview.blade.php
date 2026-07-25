<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $templateName ?? 'Template preview' }}</title>
    <style>
        body { margin: 0; background: #e8eef7; font-family: DejaVu Sans, Georgia, serif; }
        .toolbar {
            position: sticky; top: 0; z-index: 2;
            background: #0b3f90; color: #fff;
            padding: 12px 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .toolbar strong { font-size: 15px; }
        .toolbar span { opacity: .85; font-size: 13px; }
        .page {
            max-width: 820px;
            margin: 24px auto;
            background: #fff;
            padding: 48px 56px;
            box-shadow: 0 10px 30px rgba(11,63,144,.12);
            min-height: 1000px;
        }
        .page h1 { color: #0b3f90; font-size: 22px; }
        .page h2 { color: #0b3f90; font-size: 18px; margin-top: 1.4em; }
        .page p { line-height: 1.55; font-size: 14px; }
        .page table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .page td, .page th { border: 1px solid #d7e0ef; padding: 8px; font-size: 13px; }
        .sample-note {
            max-width: 820px; margin: 0 auto 8px;
            color: #6b7280; font-size: 12px; font-family: system-ui, sans-serif;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $templateName ?? 'Template preview' }}</strong><br>
            <span>Rendered preview with sample party / event data</span>
        </div>
        <button onclick="window.close()" style="background:#fff;color:#0b3f90;border:0;border-radius:8px;padding:8px 14px;font-weight:600;cursor:pointer;">Close</button>
    </div>
    <p class="sample-note">Placeholders are filled with sample values for preview only. Real contracts use the parties and links you select at create time.</p>
    <div class="page">
        {!! $bodyHtml !!}
    </div>
</body>
</html>
