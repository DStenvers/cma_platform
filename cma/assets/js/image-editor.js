/**
 * Stand-alone image editor controller (image-editor.php).
 *
 * Edits an image that already exists on the server. Every operation is delegated
 * to wizards/file-browser.php's image-op endpoint — the same one the files-wizard
 * right-pane edit bar uses (rotate / filter / crop / autocrop / restore / resize) —
 * which saves in place, keeps an original backup and regenerates responsive
 * variants. After each op we re-fetch the file's details so the preview busts its
 * cache (?versie=) and the dimensions update.
 *
 * Field-definition rules (resizeType from FormControlHelper):
 *   0 free    — crop unconstrained, no forced output size.
 *   1 maximum — crop unconstrained; on Klaar, scale down to fit W x H (ratio kept).
 *   2 fixed   — crop locked to W/H ratio and output exactly W x H.
 *
 * On Klaar it posts {type:'image-editor-complete', file, width, height} to the
 * parent window and window.opener.
 */
(function () {
    'use strict';

    var log = (typeof cmaLog !== 'undefined' && cmaLog) ? cmaLog : console;

    var imgEditor = {
        cfg: null,
        width: 0,
        height: 0,
        hasOriginal: false,
        _cropState: null,

        init: function () {
            var self = this;
            this.cfg = window.IMAGE_EDITOR_CONFIG || {};
            if (!this.cfg.basePath || !this.cfg.file) {
                this.showInfo('Geen afbeelding opgegeven.');
                return;
            }
            this.renderRuleHint();
            this.showLoader();
            this.reload().then(function () { self.hideLoader(); });
        },

        // ── Loading spinner (lib-loader has a built-in delay so quick ops don't
        // flicker; only genuinely slow ops — GD on a big image — show it). ──────
        showLoader: function () {
            var l = document.getElementById('ieLoader');
            if (l) { if (typeof l.show === 'function') l.show(); else l.setAttribute('active', ''); }
        },
        hideLoader: function () {
            var l = document.getElementById('ieLoader');
            if (l) { if (typeof l.hide === 'function') l.hide(); else l.removeAttribute('active'); }
        },

        // ── Before/after compare slider ─────────────────────────────────────
        toggleCompare: function () {
            var pane = document.getElementById('ieComparePane');
            if (!pane) return;
            if (pane.classList.contains('is-open')) { this.closeCompare(); return; }
            this.cancelRgb();
            if (!this.originalUrl) { this.toast('Geen origineel om mee te vergelijken.', true); return; }
            var bg = document.getElementById('ieCompareBg');   // edited, full (right)
            var fg = document.getElementById('ieCompareFg');   // original, clipped (left)
            if (bg) bg.style.backgroundImage = 'url("' + this.currentUrl + '")';
            if (fg) fg.style.backgroundImage = 'url("' + this.originalUrl + '")';
            pane.classList.add('is-open');
            this._setComparePos(50);
            this._initCompareDrag();
        },

        closeCompare: function () {
            var pane = document.getElementById('ieComparePane');
            if (!pane || !pane.classList.contains('is-open')) return;
            pane.classList.remove('is-open');
            if (this._cmpDownB) pane.removeEventListener('pointerdown', this._cmpDownB);
            if (this._cmpMoveB) window.removeEventListener('pointermove', this._cmpMoveB);
            if (this._cmpUpB) window.removeEventListener('pointerup', this._cmpUpB);
        },

        _setComparePos: function (pct) {
            pct = Math.max(0, Math.min(100, pct));
            var fg = document.getElementById('ieCompareFg');
            var split = document.getElementById('ieCompareSplit');
            if (fg) fg.style.clipPath = 'inset(0 ' + (100 - pct) + '% 0 0)';
            if (split) split.style.left = pct + '%';
        },

        _initCompareDrag: function () {
            var self = this;
            var pane = document.getElementById('ieComparePane');
            if (!pane) return;
            var dragging = false;
            var toPct = function (e) {
                var r = pane.getBoundingClientRect();
                return ((e.clientX - r.left) / r.width) * 100;
            };
            this._cmpDownB = function (e) {
                // Ignore clicks on the close button.
                if (e.target && e.target.closest && e.target.closest('.ie-compare-close')) return;
                dragging = true; self._setComparePos(toPct(e)); e.preventDefault();
            };
            this._cmpMoveB = function (e) { if (dragging) self._setComparePos(toPct(e)); };
            this._cmpUpB = function () { dragging = false; };
            pane.addEventListener('pointerdown', this._cmpDownB);
            window.addEventListener('pointermove', this._cmpMoveB);
            window.addEventListener('pointerup', this._cmpUpB);
        },

        // ── Colour balance (R/G/B) with live canvas preview ─────────────────
        toggleRgb: function () {
            var panel = document.getElementById('ieRgbPanel');
            if (!panel) return;
            if (panel.classList.contains('is-open')) { this.cancelRgb(); return; }
            this.cancelCrop();
            this.closeCompare();
            var img = document.getElementById('editorImage');
            var canvas = document.getElementById('ieRgbCanvas');
            if (!img || !canvas || !img.complete || !img.naturalWidth) {
                this.toast('Afbeelding is nog niet geladen.', true);
                return;
            }
            // Preview at the displayed size, capped so the per-pixel update stays real-time.
            var w = img.clientWidth || img.naturalWidth;
            var h = img.clientHeight || img.naturalHeight;
            var cap = 1000, sc = Math.min(1, cap / Math.max(w, h));
            w = Math.max(1, Math.round(w * sc));
            h = Math.max(1, Math.round(h * sc));
            canvas.width = w; canvas.height = h;
            var ctx = canvas.getContext('2d');
            try {
                ctx.drawImage(img, 0, 0, w, h);
                this._rgbOrig = ctx.getImageData(0, 0, w, h);
            } catch (e) {
                log.error('[ImageEditor] rgb preview blocked:', e && e.message);
                this.toast('Live voorbeeld niet beschikbaar voor deze afbeelding.', true);
                return;
            }
            this._rgbCtx = ctx;
            ['R', 'G', 'B'].forEach(function (c) {
                var s = document.getElementById('ieRgb' + c); if (s) s.value = 0;
                var v = document.getElementById('ieRgb' + c + 'v'); if (v) v.textContent = '0';
            });
            this._wb = null;
            var wbBtn = document.getElementById('ieRgbWb');
            if (wbBtn) wbBtn.classList.remove('is-active');
            img.style.display = 'none';
            canvas.classList.add('is-open');
            panel.classList.add('is-open');
            this.previewRgb();
        },

        _rgbVals: function () {
            var g = function (id) { var el = document.getElementById(id); return el ? (parseInt(el.value, 10) || 0) : 0; };
            return { r: g('ieRgbR'), g: g('ieRgbG'), b: g('ieRgbB') };
        },

        // Auto white balance (gray-world): scale each channel so its average becomes
        // the overall gray, neutralising a colour cast. Stored as factors and applied
        // (multiply) before the additive R/G/B sliders, both here and on the server.
        whiteBalance: function () {
            if (!this._rgbOrig) return;
            var btn = document.getElementById('ieRgbWb');
            if (this._wb) {
                this._wb = null;
                if (btn) btn.classList.remove('is-active');
            } else {
                var data = this._rgbOrig.data, sr = 0, sg = 0, sb = 0, n = 0;
                for (var i = 0; i < data.length; i += 4) { sr += data[i]; sg += data[i + 1]; sb += data[i + 2]; n++; }
                if (!n) return;
                var ar = sr / n, ag = sg / n, ab = sb / n, gray = (ar + ag + ab) / 3;
                var cl = function (f) { return Math.max(0.2, Math.min(5, f)); };
                this._wb = {
                    rf: cl(ar > 0 ? gray / ar : 1),
                    gf: cl(ag > 0 ? gray / ag : 1),
                    bf: cl(ab > 0 ? gray / ab : 1)
                };
                if (btn) btn.classList.add('is-active');
            }
            this.previewRgb();
        },

        previewRgb: function () {
            if (!this._rgbOrig || !this._rgbCtx) return;
            var v = this._rgbVals();
            var wb = this._wb || { rf: 1, gf: 1, bf: 1 };
            var src = this._rgbOrig.data;
            var out = this._rgbCtx.createImageData(this._rgbOrig.width, this._rgbOrig.height);
            var d = out.data;
            for (var i = 0; i < src.length; i += 4) {
                var r = Math.round(src[i] * wb.rf) + v.r;
                var g = Math.round(src[i + 1] * wb.gf) + v.g;
                var b = Math.round(src[i + 2] * wb.bf) + v.b;
                d[i]     = r < 0 ? 0 : (r > 255 ? 255 : r);
                d[i + 1] = g < 0 ? 0 : (g > 255 ? 255 : g);
                d[i + 2] = b < 0 ? 0 : (b > 255 ? 255 : b);
                d[i + 3] = src[i + 3];
            }
            this._rgbCtx.putImageData(out, 0, 0);
            var setv = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = String(val); };
            setv('ieRgbRv', v.r); setv('ieRgbGv', v.g); setv('ieRgbBv', v.b);
        },

        applyRgb: function () {
            var v = this._rgbVals();
            var wb = this._wb || { rf: 1, gf: 1, bf: 1 };
            var noop = v.r === 0 && v.g === 0 && v.b === 0 && wb.rf === 1 && wb.gf === 1 && wb.bf === 1;
            if (noop) { this.cancelRgb(); return; }
            // editOp() closes the RGB UI at its start; values are already captured here.
            var arg = v.r + ',' + v.g + ',' + v.b + ',' + wb.rf.toFixed(4) + ',' + wb.gf.toFixed(4) + ',' + wb.bf.toFixed(4);
            this.editOp({ action: 'filter', filter: 'colorbalance', arg: arg });
        },

        cancelRgb: function () { this._closeRgbUi(); },

        _closeRgbUi: function () {
            var panel = document.getElementById('ieRgbPanel');
            var canvas = document.getElementById('ieRgbCanvas');
            var img = document.getElementById('editorImage');
            if (panel) panel.classList.remove('is-open');
            if (canvas) canvas.classList.remove('is-open');
            if (img) img.style.display = '';
            this._wb = null;
            var wbBtn = document.getElementById('ieRgbWb');
            if (wbBtn) wbBtn.classList.remove('is-active');
            this._rgbOrig = null;
            this._rgbCtx = null;
        },

        // ── Server I/O ──────────────────────────────────────────────────────
        opUrl: function () {
            return this.cfg.endpoint
                + '?basepath=' + encodeURIComponent(this.cfg.basePath)
                + '&path=' + encodeURIComponent(this.cfg.path || '');
        },

        // POST a mutating image operation, then refresh the preview/details.
        // Resolves to true on success, false on failure. Shows the spinner for the
        // whole op (the op fetch itself is the slow part on a large image).
        editOp: function (extra) {
            var self = this;
            var fd = new FormData();
            fd.append('action', extra.action);
            fd.append('file', this.cfg.file);
            for (var k in extra) {
                if (extra.hasOwnProperty(k) && k !== 'action') fd.append(k, String(extra[k]));
            }
            self.closeCompare(); // the comparison would be stale once the image changes
            self.cancelRgb();    // close the colour-balance preview before any op
            self.showLoader();
            return fetch(this.opUrl(), { method: 'POST', body: fd })
                .then(function (r) {
                    // A server fatal (e.g. GD out-of-memory on a huge image) returns a
                    // non-JSON 500; surface the status rather than a generic message.
                    if (!r.ok) {
                        return r.text().then(function (t) {
                            throw new Error('Server (' + r.status + '): ' + (t || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160));
                        });
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (data && data.success) {
                        return self.reload().then(function () { return true; });
                    }
                    self.toast('Bewerking mislukt: ' + ((data && data.error) || 'onbekende fout'), true);
                    return false;
                })
                .catch(function (err) {
                    log.error('[ImageEditor] op failed:', err && err.message);
                    self.toast('Bewerking mislukt — ' + (err && err.message ? err.message : 'netwerkfout'), true);
                    return false;
                })
                .then(function (ok) { self.hideLoader(); return ok; });
        },

        // Re-fetch file details (url, dimensions, hasOriginal) and repaint.
        reload: function () {
            var self = this;
            var url = this.cfg.endpoint
                + '?action=details'
                + '&basepath=' + encodeURIComponent(this.cfg.basePath)
                + '&path=' + encodeURIComponent(this.cfg.path || '')
                + '&file=' + encodeURIComponent(this.cfg.file);
            return fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        self.showInfo((data && data.error) || 'Afbeelding niet gevonden.');
                        return;
                    }
                    self.width = parseInt(data.width, 10) || 0;
                    self.height = parseInt(data.height, 10) || 0;
                    self.hasOriginal = !!data.hasOriginal;
                    self.currentUrl = data.url || '';
                    self.originalUrl = data.originalUrl || '';
                    var img = document.getElementById('editorImage');
                    if (img) img.src = data.url; // url already carries ?versie= cache-buster
                    self.paintInfo();
                    // "Origineel terugzetten" and "Vergelijk" are only relevant once an
                    // edit (and therefore an original backup) exists.
                    var restore = document.getElementById('ieRestore');
                    if (restore) restore.classList.toggle('is-shown', self.hasOriginal);
                    var cmp = document.getElementById('ieCompareBtn');
                    if (cmp) cmp.classList.toggle('is-shown', self.hasOriginal && !!self.originalUrl);
                })
                .catch(function (err) {
                    log.error('[ImageEditor] reload failed:', err && err.message);
                    self.showInfo('Kan afbeelding niet laden.');
                });
        },

        // ── Operations ──────────────────────────────────────────────────────
        rotate: function (deg) { this.cancelCrop(); this.editOp({ action: 'rotate', degrees: deg }); },
        flip: function (dir) { this.cancelCrop(); this.editOp({ action: 'filter', filter: 'flip', arg: dir }); },
        filter: function (filter, arg) { this.cancelCrop(); this.editOp({ action: 'filter', filter: filter, arg: arg }); },
        restore: function () { this.cancelCrop(); this.editOp({ action: 'restore' }); },
        autocrop: function () {
            this.cancelCrop();
            var m = 10;
            var inp = document.getElementById('autocropMargin');
            if (inp) { m = parseInt(inp.value, 10); if (isNaN(m) || m < 0) m = 0; if (m > 50) m = 50; }
            this.editOp({ action: 'autocrop', margin: m });
        },

        // ── Interactive crop: a default selection is placed, then it can be
        // moved (drag inside), resized (drag a corner handle) or redrawn (drag on
        // empty space). Uses pointer events so it works with mouse AND touch. ────
        startCrop: function () {
            var self = this;
            var wrap = document.getElementById('previewWrap');
            var img = document.getElementById('editorImage');
            if (!wrap || !img) return;
            this.cancelCrop();
            this.cancelRgb();
            wrap.classList.add('cropping');

            var overlay = document.createElement('div');
            overlay.className = 'crop-overlay';
            var sel = document.createElement('div');
            sel.className = 'crop-selection';
            ['nw', 'ne', 'sw', 'se'].forEach(function (c) {
                var hd = document.createElement('div');
                hd.className = 'crop-handle crop-handle--' + c;
                hd.setAttribute('data-corner', c);
                sel.appendChild(hd);
            });
            overlay.appendChild(sel);
            wrap.appendChild(overlay);

            // Locked ratio: aspect (ratio only) > resizetype=fixed (ratio + size) > free.
            var ratio = 0, targetW = 0, targetH = 0, label = '';
            var aw = Number(this.cfg.aspectW) || 0, ah = Number(this.cfg.aspectH) || 0;
            if (aw > 0 && ah > 0) {
                ratio = aw / ah;
                label = 'Verhouding: ' + aw + ':' + ah;
            } else if (Number(this.cfg.resizeType) === 2 && Number(this.cfg.resizeWidth) > 0 && Number(this.cfg.resizeHeight) > 0) {
                targetW = Number(this.cfg.resizeWidth);
                targetH = Number(this.cfg.resizeHeight);
                ratio = targetW / targetH;
                label = 'Vaste maat: ' + targetW + '×' + targetH + ' px';
            }

            var actions = document.createElement('div');
            actions.className = 'crop-actions';
            var html = '<button type="button" class="btn btn-primary" onclick="imgEditor.applyCrop()">Bijsnijden</button> '
                     + '<button type="button" class="btn btn-secondary" onclick="imgEditor.cancelCrop()">Annuleren</button>';
            if (label) html += ' <span class="crop-target">' + label + '</span>';
            actions.innerHTML = html;
            wrap.parentNode.insertBefore(actions, wrap.nextSibling);

            var st = {
                overlay: overlay, sel: sel, actions: actions, img: img,
                ratio: ratio, targetW: targetW, targetH: targetH,
                box: null, mode: null, corner: null, start: null, orig: null
            };
            this._cropState = st;

            // Default selection: ~80% centered (at the locked ratio if any), so there
            // is immediately something to move/resize.
            var ow = overlay.clientWidth, oh = overlay.clientHeight;
            var bw, bh;
            if (ratio > 0) {
                bw = ow * 0.8; bh = bw / ratio;
                if (bh > oh * 0.9) { bh = oh * 0.9; bw = bh * ratio; }
            } else {
                bw = ow * 0.7; bh = oh * 0.7;
            }
            st.box = this._clampBox({ l: (ow - bw) / 2, t: (oh - bh) / 2, w: bw, h: bh }, ow, oh, ratio);
            this._renderCrop();

            this._cropDownB = function (e) { self._cropPointerDown(e); };
            this._cropMoveB = function (e) { self._cropPointerMove(e); };
            this._cropUpB = function (e) { self._cropPointerUp(e); };
            overlay.addEventListener('pointerdown', this._cropDownB);
            window.addEventListener('pointermove', this._cropMoveB);
            window.addEventListener('pointerup', this._cropUpB);
        },

        _overlayPoint: function (e) {
            var r = this._cropState.overlay.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(e.clientX - r.left, r.width)),
                y: Math.max(0, Math.min(e.clientY - r.top, r.height)),
                ow: r.width, oh: r.height
            };
        },

        _clampBox: function (b, ow, oh, ratio) {
            var w = Math.max(10, b.w), h = Math.max(10, b.h);
            if (ratio > 0) h = w / ratio;
            if (w > ow) { w = ow; if (ratio > 0) h = w / ratio; }
            if (h > oh) { h = oh; if (ratio > 0) w = h * ratio; }
            var l = b.l, t = b.t;
            if (l + w > ow) l = ow - w;
            if (t + h > oh) t = oh - h;
            if (l < 0) l = 0;
            if (t < 0) t = 0;
            return { l: l, t: t, w: w, h: h };
        },

        _renderCrop: function () {
            var st = this._cropState;
            if (!st || !st.box) return;
            var s = st.sel, b = st.box;
            s.style.display = 'block';
            s.style.left = b.l + 'px';
            s.style.top = b.t + 'px';
            s.style.width = b.w + 'px';
            s.style.height = b.h + 'px';
        },

        _cropPointerDown: function (e) {
            var st = this._cropState;
            if (!st) return;
            e.preventDefault();
            var p = this._overlayPoint(e), b = st.box;
            var corner = (e.target && e.target.getAttribute) ? e.target.getAttribute('data-corner') : null;
            if (corner) {
                st.mode = 'resize'; st.corner = corner; st.orig = { l: b.l, t: b.t, w: b.w, h: b.h }; st.start = p;
            } else if (b && p.x >= b.l && p.x <= b.l + b.w && p.y >= b.t && p.y <= b.t + b.h) {
                st.mode = 'move'; st.orig = { l: b.l, t: b.t, w: b.w, h: b.h }; st.start = p;
            } else {
                st.mode = 'draw'; st.start = p; st.box = { l: p.x, t: p.y, w: 0, h: 0 };
            }
        },

        _cropPointerMove: function (e) {
            var st = this._cropState;
            if (!st || !st.mode) return;
            var p = this._overlayPoint(e), ow = p.ow, oh = p.oh;
            if (st.mode === 'move') {
                var l = st.orig.l + (p.x - st.start.x), t = st.orig.t + (p.y - st.start.y);
                if (l < 0) l = 0;
                if (t < 0) t = 0;
                if (l + st.orig.w > ow) l = ow - st.orig.w;
                if (t + st.orig.h > oh) t = oh - st.orig.h;
                st.box = { l: l, t: t, w: st.orig.w, h: st.orig.h };
            } else if (st.mode === 'draw') {
                st.box = this._rectFromDrag(st.start, p, ow, oh, st.ratio);
            } else if (st.mode === 'resize') {
                st.box = this._rectFromResize(st.orig, st.corner, p, ow, oh, st.ratio);
            }
            this._renderCrop();
        },

        _cropPointerUp: function () {
            if (this._cropState) { this._cropState.mode = null; this._cropState.corner = null; }
        },

        _rectFromDrag: function (s, p, ow, oh, ratio) {
            var left, top, w, h;
            if (ratio > 0) {
                var sX = (p.x >= s.x) ? 1 : -1, sY = (p.y >= s.y) ? 1 : -1;
                w = Math.abs(p.x - s.x); h = w / ratio;
                var mW = sX > 0 ? (ow - s.x) : s.x, mH = sY > 0 ? (oh - s.y) : s.y;
                if (w > mW) { w = mW; h = w / ratio; }
                if (h > mH) { h = mH; w = h * ratio; }
                left = sX > 0 ? s.x : s.x - w;
                top = sY > 0 ? s.y : s.y - h;
            } else {
                left = Math.min(p.x, s.x); top = Math.min(p.y, s.y);
                w = Math.abs(p.x - s.x); h = Math.abs(p.y - s.y);
            }
            return { l: left, t: top, w: w, h: h };
        },

        _rectFromResize: function (orig, corner, p, ow, oh, ratio) {
            // The corner opposite the dragged one stays fixed (the anchor).
            var anchorX = (corner === 'nw' || corner === 'sw') ? (orig.l + orig.w) : orig.l;
            var anchorY = (corner === 'nw' || corner === 'ne') ? (orig.t + orig.h) : orig.t;
            var dirX = (p.x >= anchorX) ? 1 : -1, dirY = (p.y >= anchorY) ? 1 : -1;
            var w = Math.abs(p.x - anchorX), h = Math.abs(p.y - anchorY);
            if (ratio > 0) {
                h = w / ratio;
                var mW = dirX > 0 ? (ow - anchorX) : anchorX;
                var mH = dirY > 0 ? (oh - anchorY) : anchorY;
                if (w > mW) { w = mW; h = w / ratio; }
                if (h > mH) { h = mH; w = h * ratio; }
            } else {
                var mW2 = dirX > 0 ? (ow - anchorX) : anchorX;
                var mH2 = dirY > 0 ? (oh - anchorY) : anchorY;
                if (w > mW2) w = mW2;
                if (h > mH2) h = mH2;
            }
            return { l: dirX > 0 ? anchorX : anchorX - w, t: dirY > 0 ? anchorY : anchorY - h, w: w, h: h };
        },

        cancelCrop: function () {
            var wrap = document.getElementById('previewWrap');
            if (wrap) wrap.classList.remove('cropping');
            var st = this._cropState;
            if (st) {
                if (st.overlay && this._cropDownB) st.overlay.removeEventListener('pointerdown', this._cropDownB);
                if (this._cropMoveB) window.removeEventListener('pointermove', this._cropMoveB);
                if (this._cropUpB) window.removeEventListener('pointerup', this._cropUpB);
                if (st.overlay) st.overlay.remove();
                if (st.actions) st.actions.remove();
                this._cropState = null;
            }
        },

        applyCrop: function () {
            var st = this._cropState;
            if (!st || !st.box) return;
            var b = st.box;
            if (b.w < 5 || b.h < 5) { this.toast('Selecteer eerst een gebied', true); return; }
            // The overlay matches the displayed image exactly (inset:0 of the wrap),
            // so the box is already in displayed-image pixels.
            var ovR = st.overlay.getBoundingClientRect();
            var scaleX = st.img.naturalWidth / ovR.width;
            var scaleY = st.img.naturalHeight / ovR.height;
            var x = Math.max(0, Math.round(b.l * scaleX));
            var y = Math.max(0, Math.round(b.t * scaleY));
            var cw = Math.round(b.w * scaleX);
            var ch = Math.round(b.h * scaleY);
            var destW = st.ratio > 0 ? st.targetW : 0;
            var destH = st.ratio > 0 ? st.targetH : 0;
            this.cancelCrop();
            this.editOp({ action: 'crop', x: x, y: y, width: cw, height: ch, destWidth: destW, destHeight: destH });
        },

        // ── Finish / cancel ─────────────────────────────────────────────────
        finish: function () {
            var self = this;
            this.cancelCrop();
            var aw = Number(this.cfg.aspectW) || 0, ah = Number(this.cfg.aspectH) || 0;

            // Aspect lock (e.g. 16:9): the saved image MUST match the ratio. Require
            // the user to crop it manually — don't auto-crop. If it isn't the ratio
            // yet, refuse to save and open the (ratio-locked) crop tool for them.
            if (aw > 0 && ah > 0 && this.width > 0 && this.height > 0) {
                var ratio = aw / ah;
                if (Math.abs(this.width / this.height - ratio) > 0.01) {
                    this.toast('Snijd de afbeelding eerst bij tot ' + aw + ':' + ah + ' voordat je opslaat.', true);
                    this.startCrop();
                    return;
                }
                this.postComplete();
                return;
            }

            var rt = Number(this.cfg.resizeType);
            var mw = Number(this.cfg.resizeWidth) || 0;
            var mh = Number(this.cfg.resizeHeight) || 0;

            // Maximum rule: ensure the saved image fits within W x H (scale down).
            if (rt === 1 && (mw > 0 || mh > 0) &&
                ((mw > 0 && this.width > mw) || (mh > 0 && this.height > mh))) {
                this.editOp({ action: 'resize', width: mw || this.width, height: mh || this.height })
                    .then(function (ok) { if (ok) self.postComplete(); });
                return;
            }
            this.postComplete();
        },

        postComplete: function () {
            var msg = { type: 'image-editor-complete', file: this.cfg.file, width: this.width, height: this.height };
            try { if (window.parent && window.parent !== window) window.parent.postMessage(msg, '*'); } catch (e) {}
            try { if (window.opener && !window.opener.closed) window.opener.postMessage(msg, '*'); } catch (e) {}
        },

        cancel: function () {
            this.cancelCrop();
            var msg = { type: 'image-editor-cancel' };
            try { if (window.parent && window.parent !== window) window.parent.postMessage(msg, '*'); } catch (e) {}
            try { if (window.opener && !window.opener.closed) window.opener.postMessage(msg, '*'); } catch (e) {}
        },

        // ── Info / hints ────────────────────────────────────────────────────
        paintInfo: function () {
            var el = document.getElementById('ieDimensions');
            if (el) el.textContent = (this.width && this.height) ? (this.width + ' × ' + this.height + ' px') : '–';
        },

        renderRuleHint: function () {
            var el = document.getElementById('ieRule');
            if (!el) return;
            var aw = Number(this.cfg.aspectW) || 0, ah = Number(this.cfg.aspectH) || 0;
            var rt = Number(this.cfg.resizeType);
            var w = Number(this.cfg.resizeWidth) || 0, h = Number(this.cfg.resizeHeight) || 0;
            if (aw > 0 && ah > 0) {
                // Visualise the ratio with a small box shaped to aw:ah next to the label.
                var boxH = 14, boxW = Math.max(8, Math.round(boxH * aw / ah));
                el.innerHTML = 'Vaste verhouding: '
                    + '<span class="ie-ratio">'
                    + '<span class="ie-ratio-box" style="width:' + boxW + 'px;height:' + boxH + 'px"></span>'
                    + '<span class="ie-ratio-num">' + aw + ':' + ah + '</span>'
                    + '</span>';
            }
            else if (rt === 2 && w > 0 && h > 0) el.textContent = 'Vaste maat: ' + w + ' × ' + h + ' px';
            else if (rt === 1 && (w > 0 || h > 0)) el.textContent = 'Maximaal: ' + w + ' × ' + h + ' px';
            else el.textContent = '';
        },

        showInfo: function (text) {
            var el = document.getElementById('ieDimensions');
            if (el) el.textContent = text;
        },

        toast: function (text, isError) {
            // No global toast on the front-end; surface errors, ignore successes.
            if (isError) { try { alert(text); } catch (e) { log.error('[ImageEditor]', text); } }
        }
    };

    window.imgEditor = imgEditor;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { imgEditor.init(); });
    } else {
        imgEditor.init();
    }
})();
