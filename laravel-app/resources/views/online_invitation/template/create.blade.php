@extends('layout.main')
@section('content')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        @foreach($errors->all() as $error) {{ $error }} <br> @endforeach
    </div>
@endif

@php
    $borderColor = old('border_color', '#c8a75e');
    $fontColor = old('font_color', '#f3e7c1');
    $fontSize = (int) old('font_size', 16);
    if ($fontSize < 12 || $fontSize > 28) {
        $fontSize = 16;
    }
@endphp

<style>
    .oi-tpl-preview-wrap {
        position: sticky; top: 80px;
    }
    .oi-tpl-preview {
        width: 100%; max-width: 320px; margin: 0 auto;
        aspect-ratio: 2 / 3;
        border-radius: 10px; overflow: hidden; position: relative;
        background: #111; box-shadow: 0 10px 30px rgba(15,23,42,.18);
    }
    .oi-tpl-preview-bg {
        position: absolute; inset: 0;
        background-size: cover; background-position: center;
        background-color: #111;
    }
    .oi-tpl-preview-overlay {
        position: absolute; inset: 0;
        background: rgba(0,0,0,.42);
    }
    .oi-tpl-preview-frame {
        position: absolute; inset: 10px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 12px;
        z-index: 2;
    }
    .oi-tpl-preview-border {
        width: 100%; height: 100%;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 14px 10px;
        box-sizing: border-box;
    }
    .oi-tpl-pv-title { font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin: 0; }
    .oi-tpl-pv-sub { letter-spacing: .2em; text-transform: uppercase; opacity: .9; margin: 2px 0 10px; font-weight: 600; }
    .oi-tpl-pv-dear { font-style: italic; margin: 0 0 6px; }
    .oi-tpl-pv-line { margin: 0 0 4px; font-weight: 500; }
    .oi-tpl-pv-event { font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 10px; padding-bottom: 8px; }
    .oi-tpl-pv-meta { font-weight: 700; line-height: 1.3; }
    .oi-tpl-hint { font-size: 12px; color: #64748b; }
</style>

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h4>Create Template</h4></div>
            <div class="card-body">
                <form action="{{ route('online_invitation.templates.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                            <div class="form-group">
                                <label>Background Image</label>
                                <input type="file" name="background_image" id="background_image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Any portrait image is fine — it will be auto-fitted to 1024×1536 px. Preview updates as you pick colors/size.</small>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Border color</label>
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <input type="color" id="border_color_picker" value="{{ $borderColor }}">
                                            <input type="text" name="border_color" id="border_color" class="form-control" value="{{ $borderColor }}" maxlength="7" placeholder="#c8a75e">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Text color</label>
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <input type="color" id="font_color_picker" value="{{ $fontColor }}">
                                            <input type="text" name="font_color" id="font_color" class="form-control" value="{{ $fontColor }}" maxlength="7" placeholder="#f3e7c1">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Font size</label>
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            <input type="range" id="font_size_range" min="12" max="28" step="1" value="{{ $fontSize }}" style="flex:1;">
                                            <input type="number" name="font_size" id="font_size" class="form-control" style="width:84px;" min="12" max="28" value="{{ $fontSize }}">
                                        </div>
                                        <small class="form-text text-muted">Base text size in px (12–28). Guest apply &amp; sent invites use these template styles.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <button class="btn btn-primary" type="submit">{{ trans('file.submit') }}</button>
                                <a class="btn btn-link" href="{{ route('online_invitation.templates.index') }}">Back</a>
                            </div>
                        </div>
                        <div class="col-lg-5 mt-4 mt-lg-0">
                            <div class="oi-tpl-preview-wrap">
                                <label class="d-block mb-2 font-weight-bold">Live preview</label>
                                <p class="oi-tpl-hint mb-2">Adjust text color and font size until it matches the background.</p>
                                <div class="oi-tpl-preview" id="oi-tpl-preview">
                                    <div class="oi-tpl-preview-bg" id="oi-tpl-preview-bg"></div>
                                    <div class="oi-tpl-preview-overlay"></div>
                                    <div class="oi-tpl-preview-frame">
                                        <div class="oi-tpl-preview-border" id="oi-tpl-preview-border">
                                            <div class="oi-tpl-pv-title" id="oi-tpl-pv-title">Silver</div>
                                            <div class="oi-tpl-pv-sub" id="oi-tpl-pv-sub">Invitation</div>
                                            <div class="oi-tpl-pv-dear" id="oi-tpl-pv-dear">Dear Guest,</div>
                                            <div class="oi-tpl-pv-line" id="oi-tpl-pv-line">You are cordially invited as Guest to</div>
                                            <div class="oi-tpl-pv-event" id="oi-tpl-pv-event">Event Name</div>
                                            <div class="oi-tpl-pv-meta" id="oi-tpl-pv-meta">Sun, Aug 30, 2026<br>Venue Hall</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
(function () {
    function normalizeHex(value, fallback) {
        var v = (value || '').toString().trim();
        if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) {
            if (v.length === 4) v = '#' + v[1]+v[1]+v[2]+v[2]+v[3]+v[3];
            return v.toLowerCase();
        }
        return fallback;
    }
    function bindColor(textId, pickerId, fallback) {
        var text = document.getElementById(textId);
        var picker = document.getElementById(pickerId);
        if (!text || !picker) return;
        picker.addEventListener('input', function () { text.value = picker.value; refreshPreview(); });
        text.addEventListener('input', function () {
            var hex = normalizeHex(text.value, fallback);
            if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(text.value.trim())) {
                picker.value = hex;
            }
            refreshPreview();
        });
    }
    function clampSize(n) {
        n = parseInt(n, 10);
        if (isNaN(n)) return 16;
        return Math.max(12, Math.min(28, n));
    }
    function refreshPreview() {
        var border = normalizeHex(document.getElementById('border_color').value, '#c8a75e');
        var font = normalizeHex(document.getElementById('font_color').value, '#f3e7c1');
        var size = clampSize(document.getElementById('font_size').value);
        var scale = size / 16;
        var borderEl = document.getElementById('oi-tpl-preview-border');
        borderEl.style.border = '3px solid ' + border;
        borderEl.style.color = font;
        document.getElementById('oi-tpl-pv-title').style.fontSize = (22 * scale) + 'px';
        document.getElementById('oi-tpl-pv-title').style.color = font;
        document.getElementById('oi-tpl-pv-sub').style.fontSize = (9 * scale) + 'px';
        document.getElementById('oi-tpl-pv-dear').style.fontSize = (14 * scale) + 'px';
        document.getElementById('oi-tpl-pv-line').style.fontSize = (11 * scale) + 'px';
        document.getElementById('oi-tpl-pv-event').style.fontSize = (16 * scale) + 'px';
        document.getElementById('oi-tpl-pv-event').style.color = font;
        document.getElementById('oi-tpl-pv-event').style.borderBottom = '2px solid ' + border;
        document.getElementById('oi-tpl-pv-meta').style.fontSize = (12 * scale) + 'px';
        var name = (document.querySelector('input[name="name"]').value || 'Template').trim();
        document.getElementById('oi-tpl-pv-title').textContent = name || 'Template';
    }
    var range = document.getElementById('font_size_range');
    var sizeInput = document.getElementById('font_size');
    range.addEventListener('input', function () {
        sizeInput.value = range.value;
        refreshPreview();
    });
    sizeInput.addEventListener('input', function () {
        var v = clampSize(sizeInput.value);
        sizeInput.value = v;
        range.value = v;
        refreshPreview();
    });
    bindColor('border_color', 'border_color_picker', '#c8a75e');
    bindColor('font_color', 'font_color_picker', '#f3e7c1');
    document.querySelector('input[name="name"]').addEventListener('input', refreshPreview);
    document.getElementById('background_image').addEventListener('change', function (e) {
        var file = e.target.files && e.target.files[0];
        var bg = document.getElementById('oi-tpl-preview-bg');
        if (!file) {
            bg.style.backgroundImage = '';
            return;
        }
        var url = URL.createObjectURL(file);
        bg.style.backgroundImage = "url('" + url + "')";
    });
    refreshPreview();
})();
$("ul#online_invitation").siblings('a').attr('aria-expanded','true');
$("ul#online_invitation").addClass("show");
$("ul#online_invitation #online-invitation-template-menu").addClass("active");
</script>

@endsection
