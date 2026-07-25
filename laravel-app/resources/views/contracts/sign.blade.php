<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Contract — {{ $contract->number ?? '' }}</title>
    <style>
        :root {
            --primary: #0b3f90;
            --accent: #c6ab47;
            --text: #1f2a44;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f6fb;
            color: var(--text);
            min-height: 100vh;
        }
        .wrap { max-width: 860px; margin: 0 auto; padding: 24px 16px 48px; }
        .hero { text-align: center; margin-bottom: 20px; }
        .hero h1 { margin: 0 0 6px; font-size: 1.5rem; color: var(--primary); }
        .hero p { margin: 0; color: var(--muted); }
        .card {
            background: #fff;
            border: 1px solid #e5eaf3;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(15,23,42,.06);
        }
        .contract-body {
            max-height: 50vh;
            overflow-y: auto;
            border: 1px solid #eef2f7;
            border-radius: 10px;
            padding: 16px;
            background: #fafbfc;
            font-size: 14px;
            line-height: 1.6;
        }
        .label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; }
        .field {
            width: 100%;
            border: 1px solid #d7deea;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }
        .checkbox-row { display: flex; gap: 10px; align-items: flex-start; margin: 14px 0; }
        .checkbox-row input { margin-top: 4px; }
        #signature-pad {
            width: 100%;
            height: 180px;
            border: 2px solid #d7deea;
            border-radius: 10px;
            touch-action: none;
            background: #fff;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
            border: 0;
            font-size: 14px;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { background: #fff; border: 1px solid #cbd5e1; color: var(--text); }
        .btn-danger { background: #fff; border: 1px solid #dc3545; color: #dc3545; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 14px; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .decline-panel { display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #eef2f7; }
        .decline-panel.is-open { display: block; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>{{ $contract->title }}</h1>
        <p>Contract {{ $contract->number }} · Signing as <strong>{{ $signatory->display_name ?? 'Signatory' }}</strong></p>
    </div>

    @if(! empty($error))
        <div class="alert alert-danger">{{ $error }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="contract-body">{!! $bodyHtml !!}</div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('contracts.sign.submit', $token) }}" id="sign-form">
            @csrf
            <input type="hidden" name="signature_image" id="signature_image">

            <label class="label" for="typed_name">Full legal name *</label>
            <input type="text" name="typed_name" id="typed_name" class="field" required
                   value="{{ old('typed_name', $signatory->display_name ?? '') }}">

            <div class="checkbox-row">
                <input type="checkbox" name="consent" id="consent" value="1" required @if(old('consent')) checked @endif>
                <label for="consent">I have read this contract, understand its terms, and agree to be legally bound by my signature.</label>
            </div>

            <label class="label">Draw your signature *</label>
            <canvas id="signature-pad"></canvas>
            <div class="actions">
                <button type="button" class="btn btn-outline" onclick="clearSig()">Clear signature</button>
                <button type="submit" class="btn btn-primary" onclick="return captureSig()">Sign contract</button>
                <button type="button" class="btn btn-danger" onclick="toggleDecline()">Decline</button>
            </div>
        </form>

        <div class="decline-panel" id="decline-panel">
            <form method="POST" action="{{ route('contracts.sign.decline', $token) }}">
                @csrf
                <label class="label" for="declined_reason">Reason for declining *</label>
                <textarea name="declined_reason" id="declined_reason" class="field" rows="3" required>{{ old('declined_reason') }}</textarea>
                <div class="actions">
                    <button type="submit" class="btn btn-danger">Submit decline</button>
                    <button type="button" class="btn btn-outline" onclick="toggleDecline()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('signature-pad');
    var pad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });

    function resizeCanvas() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    window.clearSig = function () { pad.clear(); };
    window.captureSig = function () {
        if (pad.isEmpty()) {
            alert('Please draw your signature first.');
            return false;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
        return true;
    };
    window.toggleDecline = function () {
        document.getElementById('decline-panel').classList.toggle('is-open');
    };
})();
</script>
</body>
</html>
