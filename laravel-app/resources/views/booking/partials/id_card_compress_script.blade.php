<script>
(function () {
    function compressImageFile(file, maxWidth, quality, callback) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            callback(file);
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            var img = new Image();
            img.onload = function () {
                var width = img.width;
                var height = img.height;
                if (width > maxWidth) {
                    height = Math.round(height * (maxWidth / width));
                    width = maxWidth;
                }

                var canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        callback(file);
                        return;
                    }
                    var compressed = new File([blob], 'id_card.jpg', { type: 'image/jpeg' });
                    callback(compressed);
                }, 'image/jpeg', quality);
            };
            img.onerror = function () {
                callback(file);
            };
            img.src = event.target.result;
        };
        reader.onerror = function () {
            callback(file);
        };
        reader.readAsDataURL(file);
    }

    window.bindCompressedIdCardInput = function (input, targetInput, onReady) {
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                return;
            }

            compressImageFile(input.files[0], 1200, 0.72, function (compressed) {
                var dt = new DataTransfer();
                dt.items.add(compressed);
                targetInput.files = dt.files;
                if (typeof onReady === 'function') {
                    onReady(compressed.name || 'id_card.jpg');
                }
            });
        });
    };
})();
</script>
