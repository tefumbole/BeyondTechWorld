<script>
(function () {
    var MAX_EDGE = 1920;
    var BIG_BYTES = 1.5 * 1048576;
    var TARGET_BYTES = 2 * 1048576;

    function keepName(file, ext) {
        var base = (file && file.name ? file.name : 'upload').replace(/\.[^.]+$/, '');
        return base + ext;
    }

    window.compressStudentEvidence = function (file, callback) {
        if (!file) {
            callback(null);
            return;
        }
        var type = (file.type || '').toLowerCase();
        if (type.indexOf('image/') !== 0 || type === 'image/gif' || type === 'image/svg+xml' || type.indexOf('heic') !== -1 || type.indexOf('heif') !== -1) {
            callback(file);
            return;
        }

        var reader = new FileReader();
        reader.onerror = function () { callback(file); };
        reader.onload = function (event) {
            var img = new Image();
            img.onerror = function () { callback(file); };
            img.onload = function () {
                var width = img.width || 1;
                var height = img.height || 1;
                var tooBig = file.size > BIG_BYTES || width > MAX_EDGE || height > MAX_EDGE;
                if (!tooBig) {
                    callback(file);
                    return;
                }
                if (width > MAX_EDGE || height > MAX_EDGE) {
                    var scale = Math.min(MAX_EDGE / width, MAX_EDGE / height);
                    width = Math.max(1, Math.round(width * scale));
                    height = Math.max(1, Math.round(height * scale));
                }
                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);

                var quality = file.size > 6 * 1048576 ? 0.58 : 0.72;
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        callback(file);
                        return;
                    }
                    if (blob.size >= file.size && file.size <= TARGET_BYTES) {
                        callback(file);
                        return;
                    }
                    callback(new File([blob], keepName(file, '.jpg'), { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    };
})();
</script>
