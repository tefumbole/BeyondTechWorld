(function () {
    function num(value) {
        if (value === null || value === undefined || value === '') return null;
        var n = parseFloat(value);
        return isNaN(n) ? null : n;
    }

    function biquad(type, freq, gain, q) {
        var fs = 48000;
        var f0 = Math.max(20, Math.min(freq || 1000, 20000));
        var A = Math.pow(10, (gain || 0) / 40);
        var w0 = 2 * Math.PI * f0 / fs;
        var cw = Math.cos(w0);
        var sw = Math.sin(w0);
        var qq = q && q > 0 ? q : 0.707;
        var alpha = sw / (2 * qq);
        var b0, b1, b2, a0, a1, a2;
        if (type === 'lowshelf' || type === 'highshelf') {
            var S = 1;
            var alphaS = sw / 2 * Math.sqrt((A + 1 / A) * (1 / S - 1) + 2);
            var two = 2 * Math.sqrt(A) * alphaS;
            if (type === 'lowshelf') {
                b0 = A * ((A + 1) - (A - 1) * cw + two);
                b1 = 2 * A * ((A - 1) - (A + 1) * cw);
                b2 = A * ((A + 1) - (A - 1) * cw - two);
                a0 = (A + 1) + (A - 1) * cw + two;
                a1 = -2 * ((A - 1) + (A + 1) * cw);
                a2 = (A + 1) + (A - 1) * cw - two;
            } else {
                b0 = A * ((A + 1) + (A - 1) * cw + two);
                b1 = -2 * A * ((A - 1) + (A + 1) * cw);
                b2 = A * ((A + 1) + (A - 1) * cw - two);
                a0 = (A + 1) - (A - 1) * cw + two;
                a1 = 2 * ((A - 1) - (A + 1) * cw);
                a2 = (A + 1) - (A - 1) * cw - two;
            }
        } else if (type === 'hpf') {
            b0 = (1 + cw) / 2; b1 = -(1 + cw); b2 = (1 + cw) / 2;
            a0 = 1 + alpha; a1 = -2 * cw; a2 = 1 - alpha;
        } else if (type === 'lpf') {
            b0 = (1 - cw) / 2; b1 = 1 - cw; b2 = (1 - cw) / 2;
            a0 = 1 + alpha; a1 = -2 * cw; a2 = 1 - alpha;
        } else {
            b0 = 1 + alpha * A; b1 = -2 * cw; b2 = 1 - alpha * A;
            a0 = 1 + alpha / A; a1 = -2 * cw; a2 = 1 - alpha / A;
        }
        return { b0: b0 / a0, b1: b1 / a0, b2: b2 / a0, a1: a1 / a0, a2: a2 / a0 };
    }

    function magnitude(coeff, f) {
        var w = 2 * Math.PI * f / 48000;
        var c1 = Math.cos(w), s1 = Math.sin(w), c2 = Math.cos(2 * w), s2 = Math.sin(2 * w);
        var nr = coeff.b0 + coeff.b1 * c1 + coeff.b2 * c2;
        var ni = -(coeff.b1 * s1 + coeff.b2 * s2);
        var dr = 1 + coeff.a1 * c1 + coeff.a2 * c2;
        var di = -(coeff.a1 * s1 + coeff.a2 * s2);
        var mag = Math.sqrt(nr * nr + ni * ni) / Math.max(1e-12, Math.sqrt(dr * dr + di * di));
        return 20 * Math.log10(Math.max(mag, 1e-12));
    }

    function response(bands, f) {
        var db = 0;
        bands.forEach(function (band) {
            if (!band.enabled || !band.frequency_hz) return;
            db += magnitude(biquad(band.filter_type, band.frequency_hz, band.gain_db, band.q), f);
        });
        return db;
    }

    function logX(f, width) {
        var min = Math.log(20), max = Math.log(20000);
        return (Math.log(f) - min) / (max - min) * width;
    }

    function freqAt(x, width) {
        var min = Math.log(20), max = Math.log(20000);
        return Math.exp(min + (x / width) * (max - min));
    }

    function svgEl(name, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.keys(attrs).forEach(function (key) { el.setAttribute(key, attrs[key]); });
        return el;
    }

    function drawEq(svg, bands, interactive, onDrag) {
        var width = 640, height = 220, pad = 28;
        var plotW = width - pad * 2, plotH = height - pad * 2;
        var minG = -18, maxG = 18;
        bands.forEach(function (band) {
            if (!band.enabled || band.gain_db === null) return;
            minG = Math.min(minG, band.gain_db - 3);
            maxG = Math.max(maxG, band.gain_db + 3);
        });
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.innerHTML = '';
        svg.appendChild(svgEl('rect', { x: 0, y: 0, width: width, height: height, fill: '#121820' }));
        var zeroY = pad + (maxG / (maxG - minG)) * plotH;
        svg.appendChild(svgEl('line', { x1: pad, y1: zeroY, x2: width - pad, y2: zeroY, stroke: '#2a3548', 'stroke-width': 1 }));
        [20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000].forEach(function (f) {
            var x = pad + logX(f, plotW);
            svg.appendChild(svgEl('line', { x1: x, y1: pad, x2: x, y2: height - pad, stroke: '#1d2736' }));
        });
        var d = '';
        var steps = 160;
        for (var i = 0; i <= steps; i++) {
            var f = freqAt(i / steps * plotW, plotW);
            var db = response(bands, f);
            var x = pad + logX(f, plotW);
            var y = pad + ((maxG - db) / (maxG - minG)) * plotH;
            d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1) + ' ';
        }
        svg.appendChild(svgEl('path', { d: d, fill: 'none', stroke: '#3ec6ff', 'stroke-width': 2.2 }));
        bands.forEach(function (band, index) {
            if (!band.frequency_hz || band.locked) return;
            var x = pad + logX(band.frequency_hz, plotW);
            var y = pad + ((maxG - (band.gain_db || 0)) / (maxG - minG)) * plotH;
            var dot = svgEl('circle', {
                cx: x, cy: y, r: band.enabled ? 7 : 5,
                fill: band.enabled ? '#f5b942' : '#5c6b82',
                stroke: '#081018', 'stroke-width': 2
            });
            if (interactive && band.enabled) {
                dot.style.cursor = 'grab';
                dot.addEventListener('pointerdown', function (event) {
                    event.preventDefault();
                    var move = function (ev) {
                        var rect = svg.getBoundingClientRect();
                        var px = (ev.clientX - rect.left) / rect.width * width - pad;
                        var py = (ev.clientY - rect.top) / rect.height * height - pad;
                        px = Math.max(0, Math.min(plotW, px));
                        py = Math.max(0, Math.min(plotH, py));
                        var nextF = Math.round(freqAt(px, plotW) * 100) / 100;
                        var nextG = Math.round((maxG - (py / plotH) * (maxG - minG)) * 100) / 100;
                        onDrag(index, nextF, nextG);
                    };
                    var up = function () {
                        window.removeEventListener('pointermove', move);
                        window.removeEventListener('pointerup', up);
                    };
                    window.addEventListener('pointermove', move);
                    window.addEventListener('pointerup', up);
                });
            }
            svg.appendChild(dot);
        });
    }

    function drawDynamics(svg, kind, block) {
        var width = 420, height = 200, pad = 24;
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.innerHTML = '';
        svg.appendChild(svgEl('rect', { x: 0, y: 0, width: width, height: height, fill: '#121820' }));
        if (!block || block.threshold_db === null || block.enabled === false) {
            var note = svgEl('text', { x: 20, y: 40, fill: '#8ea0b8', 'font-size': 13 });
            note.textContent = kind === 'gate' ? 'Set a gate threshold to draw the curve.' : 'Set a threshold to draw the curve.';
            svg.appendChild(note);
            return;
        }
        if (kind === 'gate' && block.range_db === null) {
            var missing = svgEl('text', { x: 20, y: 40, fill: '#8ea0b8', 'font-size': 13 });
            missing.textContent = 'Gate range is empty, so the reduction is not drawn.';
            svg.appendChild(missing);
            return;
        }
        var min = -72, max = 0;
        var plotW = width - pad * 2, plotH = height - pad * 2;
        function xy(db) {
            return pad + ((db - min) / (max - min)) * plotW;
        }
        function yy(db) {
            return pad + ((max - db) / (max - min)) * plotH;
        }
        svg.appendChild(svgEl('line', { x1: xy(min), y1: yy(0), x2: xy(0), y2: yy(min), stroke: '#243044' }));
        var d = '';
        for (var i = 0; i <= 80; i++) {
            var input = min + (i / 80) * (max - min);
            var output = input;
            if (kind === 'gate') {
                output = input < block.threshold_db ? input + block.range_db : input;
            } else {
                var ratio = block.ratio && block.ratio > 0 ? block.ratio : 1;
                var knee = block.knee === 'soft' ? 6 : 0;
                var th = block.threshold_db;
                if (knee > 0 && input > th - knee / 2 && input < th + knee / 2) {
                    var delta = input - th + knee / 2;
                    output = input + ((1 / ratio) - 1) * delta * delta / (2 * knee);
                } else if (input > th) {
                    output = th + (input - th) / ratio;
                }
            }
            d += (i === 0 ? 'M' : 'L') + xy(input).toFixed(1) + ' ' + yy(output).toFixed(1) + ' ';
        }
        svg.appendChild(svgEl('path', { d: d, fill: 'none', stroke: kind === 'gate' ? '#7d6bff' : '#3ec6ff', 'stroke-width': 2.2 }));
    }

    function readBands(form) {
        var rows = form.querySelectorAll('[data-eq-band]');
        var bands = [];
        rows.forEach(function (row) {
            bands.push({
                enabled: !row.querySelector('[data-eq="enabled"]') || row.querySelector('[data-eq="enabled"]').checked,
                filter_type: row.querySelector('[data-eq="filter_type"]').value,
                frequency_hz: num(row.querySelector('[data-eq="frequency_hz"]').value),
                gain_db: num(row.querySelector('[data-eq="gain_db"]').value) || 0,
                q: num(row.querySelector('[data-eq="q"]').value) || 1
            });
        });
        var hpf = form.querySelector('[name="input[hpf_hz]"]');
        if (hpf && num(hpf.value)) {
            bands.unshift({ enabled: true, filter_type: 'hpf', frequency_hz: num(hpf.value), gain_db: 0, q: 0.707, locked: true });
        }
        return bands;
    }

    function readBlock(form, kind) {
        function field(name) {
            var el = form.querySelector('[name="' + kind + '[' + name + ']"]');
            return el ? el.value : '';
        }
        var enabled = form.querySelector('[name="' + kind + '[enabled]"]');
        return {
            enabled: !enabled || enabled.checked,
            threshold_db: num(field('threshold_db')),
            ratio: num(field('ratio')),
            knee: field('knee'),
            range_db: num(field('range_db'))
        };
    }

    function mountEditor(form) {
        var eqSvg = form.querySelector('[data-graph="eq"]');
        var compSvg = form.querySelector('[data-graph="compressor"]');
        var gateSvg = form.querySelector('[data-graph="gate"]');
        function redraw() {
            var bands = readBands(form);
            drawEq(eqSvg, bands, true, function (index, freq, gain) {
                var locked = bands[0] && bands[0].locked ? 1 : 0;
                var row = form.querySelectorAll('[data-eq-band]')[index - locked];
                if (!row) return;
                var type = row.querySelector('[data-eq="filter_type"]').value;
                row.querySelector('[data-eq="frequency_hz"]').value = freq;
                if (type !== 'hpf' && type !== 'lpf') {
                    row.querySelector('[data-eq="gain_db"]').value = gain;
                }
                redraw();
            });
            drawDynamics(compSvg, 'compressor', readBlock(form, 'compressor'));
            drawDynamics(gateSvg, 'gate', readBlock(form, 'gate'));
        }
        form.addEventListener('input', redraw);
        form.addEventListener('change', redraw);
        var add = form.querySelector('[data-add-band]');
        if (add) {
            add.addEventListener('click', function () {
                var list = form.querySelector('[data-eq-list]');
                var first = list.querySelector('[data-eq-band]');
                var clone = first.cloneNode(true);
                clone.querySelectorAll('input').forEach(function (input) {
                    if (input.type === 'checkbox') input.checked = true;
                    else input.value = '';
                });
                list.appendChild(clone);
                reindex(list);
                redraw();
            });
        }
        redraw();
    }

    function reindex(list) {
        var rows = list.querySelectorAll('[data-eq-band]');
        rows.forEach(function (row, index) {
            row.querySelectorAll('input, select').forEach(function (input) {
                input.name = input.name.replace(/eq\[\d+\]/, 'eq[' + index + ']');
            });
        });
    }

    function mountReadonly(node) {
        var data = JSON.parse(node.getAttribute('data-mix') || '{}');
        drawEq(node.querySelector('[data-graph="eq"]'), data.bands || [], false);
        drawDynamics(node.querySelector('[data-graph="compressor"]'), 'compressor', data.compressor);
        drawDynamics(node.querySelector('[data-graph="gate"]'), 'gate', data.gate);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mix-editor]').forEach(mountEditor);
        document.querySelectorAll('[data-mix-readonly]').forEach(mountReadonly);
        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var node = document.getElementById(button.getAttribute('data-copy-target'));
                if (!node) return;
                var text = node.value || node.textContent;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text);
                }
                button.textContent = 'Copied';
            });
        });
    });
})();
