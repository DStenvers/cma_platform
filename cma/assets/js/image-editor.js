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
            this.cfg = window.IMAGE_EDITOR_CONFIG || {};
            if (!this.cfg.basePath || !this.cfg.file) {
                this.showInfo('Geen afbeelding opgegeven.');
                return;
            }
            this.renderRuleHint();
            this.reload();
        },

        // ── Server I/O ──────────────────────────────────────────────────────
        opUrl: function () {
            return this.cfg.endpoint
                + '?basepath=' + encodeURIComponent(this.cfg.basePath)
                + '&path=' + encodeURIComponent(this.cfg.path || '');
        },

        // POST a mutating image operation, then refresh the preview/details.
        editOp: function (extra) {
            var self = this;
            var fd = new FormData();
            fd.append('action', extra.action);
            fd.append('file', this.cfg.file);
            for (var k in extra) {
                if (extra.hasOwnProperty(k) && k !== 'action') fd.append(k, String(extra[k]));
            }
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
                        return self.reload();
                    }
                    self.toast('Bewerking mislukt: ' + ((data && data.error) || 'onbekende fout'), true);
                    return null;
                })
                .catch(function (err) {
                    log.error('[ImageEditor] op failed:', err && err.message);
                    self.toast('Bewerking mislukt — ' + (err && err.message ? err.message : 'netwerkfout'), true);
                    return null;
                });
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
                    var img = document.getElementById('editorImage');
                    if (img) img.src = data.url; // url already carries ?versie= cache-buster
                    self.paintInfo();
                    var restore = document.getElementById('ieRestore');
                    if (restore) restore.style.display = self.hasOriginal ? '' : 'none';
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

        // ── Interactive crop (drag a rectangle, then apply) ─────────────────
        startCrop: function () {
            var self = this;
            var wrap = document.getElementById('previewWrap');
            var img = document.getElementById('editorImage');
            if (!wrap || !img) return;
            this.cancelCrop();
            wrap.classList.add('cropping');

            var overlay = document.createElement('div');
            overlay.className = 'crop-overlay';
            var sel = document.createElement('div');
            sel.className = 'crop-selection';
            sel.style.display = 'none';
            overlay.appendChild(sel);
            wrap.appendChild(overlay);

            // Fixed-size fields lock the crop to the required ratio and output W x H.
            var ratio = 0, targetW = 0, targetH = 0;
            if (Number(this.cfg.resizeType) === 2 && Number(this.cfg.resizeWidth) > 0 && Number(this.cfg.resizeHeight) > 0) {
                targetW = Number(this.cfg.resizeWidth);
                targetH = Number(this.cfg.resizeHeight);
                ratio = targetW / targetH;
            }

            var actions = document.createElement('div');
            actions.className = 'crop-actions';
            var html = '<button type="button" class="btn btn-primary" onclick="imgEditor.applyCrop()">Bijsnijden</button> '
                     + '<button type="button" class="btn" onclick="imgEditor.cancelCrop()">Annuleren</button>';
            if (ratio > 0) html += ' <span class="crop-target">Vaste maat: ' + targetW + '×' + targetH + ' px</span>';
            actions.innerHTML = html;
            wrap.parentNode.insertBefore(actions, wrap.nextSibling);

            this._cropState = { overlay: overlay, sel: sel, actions: actions, img: img, dragging: false, sx: 0, sy: 0, ratio: ratio, targetW: targetW, targetH: targetH };
            overlay.addEventListener('mousedown', function (e) { self._cropDown(e); });
            overlay.addEventListener('mousemove', function (e) { self._cropMove(e); });
            this._cropUpBound = function () { self._cropUp(); };
            window.addEventListener('mouseup', this._cropUpBound);
        },

        _cropDown: function (e) {
            var st = this._cropState;
            if (!st) return;
            var r = st.overlay.getBoundingClientRect();
            st.dragging = true;
            st.sx = e.clientX - r.left;
            st.sy = e.clientY - r.top;
            var s = st.sel;
            s.style.display = 'block';
            s.style.left = st.sx + 'px'; s.style.top = st.sy + 'px';
            s.style.width = '0px'; s.style.height = '0px';
            e.preventDefault();
        },

        _cropMove: function (e) {
            var st = this._cropState;
            if (!st || !st.dragging) return;
            var r = st.overlay.getBoundingClientRect();
            var cx = Math.max(0, Math.min(e.clientX - r.left, r.width));
            var cy = Math.max(0, Math.min(e.clientY - r.top, r.height));
            var sx = st.sx, sy = st.sy, left, top, w, h;
            if (st.ratio > 0) {
                var signX = (cx >= sx) ? 1 : -1;
                var signY = (cy >= sy) ? 1 : -1;
                w = Math.abs(cx - sx);
                h = w / st.ratio;
                var maxW = signX > 0 ? (r.width - sx) : sx;
                var maxH = signY > 0 ? (r.height - sy) : sy;
                if (w > maxW) { w = maxW; h = w / st.ratio; }
                if (h > maxH) { h = maxH; w = h * st.ratio; }
                left = signX > 0 ? sx : sx - w;
                top = signY > 0 ? sy : sy - h;
            } else {
                left = Math.min(cx, sx);
                top = Math.min(cy, sy);
                w = Math.abs(cx - sx);
                h = Math.abs(cy - sy);
            }
            var s = st.sel;
            s.style.left = left + 'px';
            s.style.top = top + 'px';
            s.style.width = w + 'px';
            s.style.height = h + 'px';
        },

        _cropUp: function () { if (this._cropState) this._cropState.dragging = false; },

        cancelCrop: function () {
            var wrap = document.getElementById('previewWrap');
            if (wrap) wrap.classList.remove('cropping');
            if (this._cropState) {
                if (this._cropUpBound) window.removeEventListener('mouseup', this._cropUpBound);
                if (this._cropState.overlay) this._cropState.overlay.remove();
                if (this._cropState.actions) this._cropState.actions.remove();
                this._cropState = null;
            }
        },

        applyCrop: function () {
            var st = this._cropState;
            if (!st) return;
            var img = st.img;
            var selR = st.sel.getBoundingClientRect();
            var imgR = img.getBoundingClientRect();
            if (selR.width < 5 || selR.height < 5) { this.toast('Selecteer eerst een gebied', true); return; }
            var scaleX = img.naturalWidth / imgR.width;
            var scaleY = img.naturalHeight / imgR.height;
            var x = Math.max(0, Math.round((selR.left - imgR.left) * scaleX));
            var y = Math.max(0, Math.round((selR.top - imgR.top) * scaleY));
            var cw = Math.round(selR.width * scaleX);
            var ch = Math.round(selR.height * scaleY);
            var destW = st.ratio > 0 ? st.targetW : 0;
            var destH = st.ratio > 0 ? st.targetH : 0;
            this.cancelCrop();
            this.editOp({ action: 'crop', x: x, y: y, width: cw, height: ch, destWidth: destW, destHeight: destH });
        },

        // ── Finish / cancel ─────────────────────────────────────────────────
        finish: function () {
            var self = this;
            this.cancelCrop();
            var rt = Number(this.cfg.resizeType);
            var mw = Number(this.cfg.resizeWidth) || 0;
            var mh = Number(this.cfg.resizeHeight) || 0;

            // Maximum rule: ensure the saved image fits within W x H (scale down).
            if (rt === 1 && (mw > 0 || mh > 0) &&
                ((mw > 0 && this.width > mw) || (mh > 0 && this.height > mh))) {
                this.editOp({ action: 'resize', width: mw || this.width, height: mh || this.height })
                    .then(function () { self.postComplete(); });
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
            var rt = Number(this.cfg.resizeType);
            var w = Number(this.cfg.resizeWidth) || 0, h = Number(this.cfg.resizeHeight) || 0;
            if (rt === 2 && w > 0 && h > 0) el.textContent = 'Vaste maat: ' + w + ' × ' + h + ' px';
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
