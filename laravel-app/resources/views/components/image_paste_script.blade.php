{{-- Global image paste + preview enhancer. Included once in the admin layout.
     Auto-enhances any image file input (by accept="image/*" or common names),
     including inputs added later inside Bootstrap modals. Safe to include twice. --}}
<script>
(function () {
    if (window.__imagePasteInit) return;
    window.__imagePasteInit = true;

    var active = null;
    var IMG_NAMES = ['image', 'images', 'site_logo', 'logo', 'favicon', 'site_icon',
        'watermark', 'water_mark', 'email_header', 'email_footer', 'photo', 'picture',
        'avatar', 'featured_image', 'banner', 'icon', 'sign', 'stemp', 'thumbnail', 'cover'];

    function isImageInput(inp) {
        if (!inp || inp.type !== 'file') return false;
        var accept = (inp.getAttribute('accept') || '').toLowerCase();
        if (accept.indexOf('image') >= 0) return true;
        var name = (inp.getAttribute('name') || '').toLowerCase().replace('[]', '');
        return IMG_NAMES.indexOf(name) >= 0;
    }

    function showFile(inp, file) {
        if (!inp.__ipPrev) return;
        var r = new FileReader();
        r.onload = function (ev) { inp.__ipPrev.src = ev.target.result; inp.__ipPrev.style.display = ''; };
        r.readAsDataURL(file);
    }

    function assign(inp, file) {
        try { var dt = new DataTransfer(); dt.items.add(file); inp.files = dt.files; } catch (e) {}
        showFile(inp, file);
    }

    function enhance(inp) {
        if (inp.__ip) return;
        inp.__ip = true;

        var hint = document.createElement('div');
        hint.tabIndex = 0;
        hint.style.cssText = 'margin-top:6px;border:1px dashed #b5b5b5;border-radius:6px;padding:6px 10px;text-align:center;color:#777;font-size:12px;cursor:pointer;background:#fafafa;';
        hint.innerHTML = 'Click here, then paste an image (Ctrl+V / \u2318V) \u2014 or drop a file';

        var prev = document.createElement('img');
        prev.style.cssText = 'margin-top:8px;max-height:90px;border-radius:6px;border:1px solid #eee;display:none;';
        var cur = inp.getAttribute('data-current');
        if (cur) { prev.src = cur; prev.style.display = ''; }

        inp.parentNode.insertBefore(hint, inp.nextSibling);
        hint.parentNode.insertBefore(prev, hint.nextSibling);
        inp.__ipPrev = prev;

        function activate() { active = inp; }
        inp.addEventListener('focus', activate);
        inp.addEventListener('click', activate);
        hint.addEventListener('focus', activate);
        hint.addEventListener('click', activate);
        inp.addEventListener('change', function () { if (inp.files && inp.files[0]) showFile(inp, inp.files[0]); });
        hint.addEventListener('dragover', function (e) { e.preventDefault(); });
        hint.addEventListener('drop', function (e) {
            e.preventDefault();
            if (e.dataTransfer.files && e.dataTransfer.files[0]) assign(inp, e.dataTransfer.files[0]);
        });
    }

    document.addEventListener('paste', function (e) {
        if (!active) return;
        var cd = e.clipboardData || window.clipboardData;
        if (!cd || !cd.items) return;
        for (var i = 0; i < cd.items.length; i++) {
            if (cd.items[i].type.indexOf('image') === 0) {
                var f = cd.items[i].getAsFile();
                if (f) { assign(active, f); e.preventDefault(); }
            }
        }
    });

    function scan(root) {
        var inputs = (root || document).querySelectorAll('input[type=file]');
        Array.prototype.forEach.call(inputs, function (inp) { if (isImageInput(inp)) enhance(inp); });
    }

    if (document.readyState !== 'loading') scan(document);
    else document.addEventListener('DOMContentLoaded', function () { scan(document); });

    // Inputs revealed inside Bootstrap modals / focused later
    if (window.jQuery) { window.jQuery(document).on('shown.bs.modal', function (e) { scan(e.target); }); }
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.type === 'file' && isImageInput(e.target)) {
            if (!e.target.__ip) enhance(e.target);
            active = e.target;
        }
    });
})();
</script>
