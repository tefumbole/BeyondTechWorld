<script>
    window.gbhsFaceCheck = function (root) {
        var video = root.video;
        var stepEl = root.stepEl;
        var meter = root.meter;
        var dots = root.dots;
        var canvas = document.createElement('canvas');
        var W = 96;
        var H = 120;
        canvas.width = W;
        canvas.height = H;
        var ctx = canvas.getContext('2d');
        var stream = null;
        var running = false;
        var step = 0;
        var baseline = null;
        var held = 0;
        var last = 0;
        var smooth = [];
        var prompts = [
            'Look straight at the camera',
            'Slowly turn your head left',
            'Slowly turn your head right',
            'Look straight again'
        ];

        function readFrame() {
            if (!video.videoWidth) return null;
            ctx.save();
            ctx.translate(W, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, W, H);
            ctx.restore();
            return ctx.getImageData(0, 0, W, H).data;
        }

        function turnAmount(base, now) {
            var y0 = Math.round(H * 0.1);
            var y1 = Math.round(H * 0.62);
            var left = 0;
            var right = 0;
            var mid = W / 2;
            for (var y = y0; y < y1; y += 2) {
                for (var x = 0; x < W; x += 2) {
                    var i = (y * W + x) * 4;
                    var delta = Math.abs(now[i] - base[i]) + Math.abs(now[i + 1] - base[i + 1]) + Math.abs(now[i + 2] - base[i + 2]);
                    if (delta < 30) continue;
                    if (x < mid) left += delta;
                    else right += delta;
                }
            }
            var total = left + right;
            if (total < 400) return { left: 0, right: 0 };
            return {
                left: Math.max(0, Math.min(1, (right - left) / (total * 0.18))),
                right: Math.max(0, Math.min(1, (left - right) / (total * 0.18)))
            };
        }

        function percent(value) {
            smooth.push(value);
            if (smooth.length > 10) smooth.shift();
            var sum = 0;
            for (var i = 0; i < smooth.length; i++) sum += smooth[i];
            return sum / smooth.length;
        }

        function paint(value) {
            var shown = Math.round(Math.max(0, Math.min(1, value)) * 100);
            if (step === 1 || step === 2) stepEl.textContent = prompts[step] + ' — ' + shown + '%';
            else stepEl.textContent = prompts[step] || 'Photo ready. Continue when you are happy with it.';
            if (meter) {
                meter.parentNode.hidden = step !== 1 && step !== 2;
                meter.style.width = shown + '%';
            }
        }

        function capture() {
            running = false;
            var shot = document.createElement('canvas');
            shot.width = video.videoWidth || 640;
            shot.height = video.videoHeight || 480;
            shot.getContext('2d').drawImage(video, 0, 0);
            if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
            shot.toBlob(function (blob) {
                if (blob && root.onCapture) root.onCapture(blob);
            }, 'image/jpeg', 0.9);
        }

        function advance() {
            held = 0;
            smooth = [];
            step += 1;
            if (dots) dots.forEach(function (dot, index) { dot.className = index <= step ? 'on' : ''; });
            if (step >= prompts.length) {
                capture();
                return;
            }
            paint(0);
        }

        function tick(now) {
            if (!running) return;
            var elapsed = last ? Math.min(90, now - last) : 16;
            last = now;
            var data = readFrame();
            if (!data) {
                requestAnimationFrame(tick);
                return;
            }
            if (!baseline) {
                paint(0);
                var center = (Math.round(H / 2) * W + Math.round(W / 2)) * 4;
                if (data[center] + data[center + 1] + data[center + 2] < 24) held = 0;
                else held += elapsed;
                if (held > 1100) {
                    baseline = new Uint8ClampedArray(data);
                    advance();
                }
                requestAnimationFrame(tick);
                return;
            }
            if (step === 3) {
                paint(0);
                held += elapsed;
                if (held > 700) advance();
                requestAnimationFrame(tick);
                return;
            }
            var amount = turnAmount(baseline, data);
            var value = percent(step === 1 ? amount.left : amount.right);
            paint(value);
            if (value >= 0.8) held += elapsed;
            else held = Math.max(0, held - elapsed);
            if (held > 420) advance();
            requestAnimationFrame(tick);
        }

        return {
            start: function () {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    return Promise.reject(new Error('camera'));
                }
                return navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false
                }).then(function (media) {
                    stream = media;
                    video.srcObject = media;
                    video.play();
                    root.box.hidden = false;
                    step = 0;
                    baseline = null;
                    held = 0;
                    last = 0;
                    smooth = [];
                    running = true;
                    paint(0);
                    if (dots) dots.forEach(function (dot, index) { dot.className = index === 0 ? 'on' : ''; });
                    requestAnimationFrame(tick);
                });
            },
            stop: function () {
                running = false;
                if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
                if (root.box) root.box.hidden = true;
            }
        };
    };

    window.gbhsPhoneHandoff = function (kind, els) {
        var tokenInput = document.querySelector('input[name=_token]');
        els.wrap.hidden = false;
        els.note.textContent = 'Opening a phone link…';
        fetch(els.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenInput ? tokenInput.value : ''
            },
            body: JSON.stringify({ kind: kind })
        }).then(function (response) {
            if (!response.ok) throw new Error('link');
            return response.json();
        }).then(function (data) {
            els.img.src = data.qr;
            els.img.hidden = false;
            els.link.href = data.url;
            els.link.textContent = data.url;
            els.note.textContent = 'Scan this code with your phone. This page updates when the photo is received.';
            var timer = setInterval(function () {
                fetch(els.status + '/' + encodeURIComponent(data.token), { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.json(); })
                    .then(function (row) {
                        if (row && row.status === 'done') {
                            clearInterval(timer);
                            if (row.message) sessionStorage.setItem('gbhsCaptureNote', row.message);
                            window.location = els.back;
                        }
                    })
                    .catch(function () {});
            }, 2000);
        }).catch(function () {
            els.note.textContent = 'The phone link could not be opened. Stay on this page and try again.';
        });
    };
    (function () {
        var note = sessionStorage.getItem('gbhsCaptureNote');
        if (!note) return;
        sessionStorage.removeItem('gbhsCaptureNote');
        var card = document.querySelector('.join-card');
        if (!card) return;
        var line = document.createElement('p');
        line.className = 'alert';
        line.textContent = note;
        card.insertBefore(line, card.firstChild);
    })();
</script>
