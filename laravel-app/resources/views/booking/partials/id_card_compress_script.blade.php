<script>
(function () {
    function isLikelyHeic(file) {
        if (!file) return false;
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        return type.indexOf('heic') !== -1
            || type.indexOf('heif') !== -1
            || /\.heic$/.test(name)
            || /\.heif$/.test(name);
    }

    function compressImageFile(file, maxWidth, quality, callback) {
        if (!file) {
            callback(null, false);
            return;
        }

        // PDF and non-images: keep as-is
        if (!file.type || file.type.indexOf('image/') !== 0) {
            callback(file, true);
            return;
        }

        if (isLikelyHeic(file)) {
            // Many phones cannot decode HEIC in-browser; ask for camera JPEG or convert.
            alert('This phone photo format (HEIC) is not supported. Please use “Snap ID” (camera) or attach a JPG/PNG/PDF.');
            callback(null, false);
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            var img = new Image();
            img.onload = function () {
                var width = img.width || 1;
                var height = img.height || 1;
                if (width > maxWidth) {
                    height = Math.round(height * (maxWidth / width));
                    width = maxWidth;
                }

                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        callback(file, true);
                        return;
                    }
                    var compressed = new File([blob], 'id_card.jpg', { type: 'image/jpeg' });
                    callback(compressed, true);
                }, 'image/jpeg', quality);
            };
            img.onerror = function () {
                alert('Could not read that image. Please use “Snap ID” (camera) or attach a JPG/PNG/PDF.');
                callback(null, false);
            };
            img.src = event.target.result;
        };
        reader.onerror = function () {
            alert('Could not read that file. Please try again with a JPG, PNG, or PDF.');
            callback(null, false);
        };
        reader.readAsDataURL(file);
    }

    window.bindCompressedIdCardInput = function (input, targetInput, onReady) {
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                return;
            }

            compressImageFile(input.files[0], 1000, 0.6, function (compressed, ok) {
                if (!ok || !compressed) {
                    try { input.value = ''; } catch (e) {}
                    if (typeof onReady === 'function') {
                        onReady('', false);
                    }
                    return;
                }

                var assigned = false;
                if (input === targetInput && compressed === input.files[0]) {
                    assigned = true;
                } else {
                    try {
                        if (typeof DataTransfer !== 'undefined') {
                            var dt = new DataTransfer();
                            dt.items.add(compressed);
                            targetInput.files = dt.files;
                            assigned = !!(targetInput.files && targetInput.files.length);
                        }
                    } catch (e) {
                        assigned = false;
                    }
                }

                if (!assigned && input === targetInput && input.files && input.files.length) {
                    assigned = true;
                } else if (!assigned && input !== targetInput && input.files && input.files.length) {
                    try {
                        targetInput.removeAttribute('name');
                        input.setAttribute('name', targetInput.getAttribute('name') || input.getAttribute('name') || 'id_card');
                        assigned = true;
                    } catch (e2) {}
                }

                if (typeof onReady === 'function') {
                    onReady((compressed && compressed.name) || (input.files[0] && input.files[0].name) || 'id_card.jpg', true);
                }
            });
        });
    };
})();
</script>
