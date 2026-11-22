/**
 * lib-uploader Web Component — bestanden plaatsen, in één opmaak.
 *
 * Een knop (ook een dropzone) met daaronder de geplaatste bestanden, als één aansluitend geheel in lichtpaars. Het bestand wordt met een XMLHttpRequest naar de server gebracht
 * (veld `qqfile`, het antwoord is JSON {success, filename, error}: het protocol van upload_handler.php), en de bestandsnaam komt in een verborgen invoerveld van het formulier.
 * Geen jQuery, geen FineUploader.
 *
 * Gebruik:
 *   <input type="hidden" id="cv" name="cv">
 *   <lib-uploader field="cv" endpoint="upload_handler.php" path="uploads/sollicitaties/" extensions="pdf,doc,docx"
 *                 max-size="10485760" button-text="Selecteer bestand (max. 10 MB)"></lib-uploader>
 *
 * Attributen:
 *   - field:       id van het verborgen invoerveld waarin de bestandsnaam komt (aanbevolen)
 *   - endpoint:    adres van de upload-handler (standaard "upload_handler.php")
 *   - path:        de map op de server (query-parameter `path`)
 *   - path-extra:  voorvoegsel voor de bestandsnaam (query-parameter `path_extra`); het veld krijgt "<path-extra><bestandsnaam>"
 *   - extensions:  toegestane extensies, gescheiden door komma's (standaard pdf,doc,docx,rtf,jpg,jpeg)
 *   - max-size:    maximale grootte in bytes (standaard 10 MB; of window.CMA.settings.uploadMaxMb in het CMA)
 *   - button-text: tekst op de knop (standaard "Plaats bestand")
 *   - drop-text:   tekst tijdens het slepen (standaard "Laat het bestand hier los")
 *   - type-error:  eigen melding bij een niet toegestaan type (html); standaard uit de extensies
 *   - multiple:    meerdere bestanden; het veld krijgt de namen gescheiden door ";"
 *   - link-base:   adres waarop een geplaatst bestand te openen is (standaard `path`)
 *   - removable:   toon een kruisje om een geplaatst bestand te verwijderen (uit de lijst en uit het veld)
 *   - disabled:    geen upload mogelijk
 *
 * Opmaak (CSS-eigenschappen, te overschrijven door de pagina):
 *   --lib-uploader-accent   rand en pictogrammen      (standaard var(--lighter_purple, #b25cff))
 *   --lib-uploader-text     tekst                      (standaard var(--dark_purple, #220037))
 *   --lib-uploader-tint     vulling van de knop        (standaard rgba(178, 92, 255, .10))
 *   --lib-uploader-hover    vulling bij hover/slepen   (standaard #ead9ff)
 *   --lib-uploader-danger   foutmelding                (standaard var(--btn-delete, #c0392b))
 *   Pictogrammen: het lettertype "LinearIcons" van de pagina (upload e8f4, bestand e6b3, afbeelding e90d, kruis e92a); is dat er niet, dan staan er geen pictogrammen.
 *
 * Gebeurtenissen (bubbelen):
 *   - upload-start:    detail { fileName }
 *   - upload-complete: detail { filename, originalName, path }
 *   - upload-error:    detail { error, fileName }
 *
 * Methoden / eigenschappen:
 *   - reset():  alles leegmaken (lijst en veld)
 *   - files:    de namen van de geplaatste bestanden (zoals de server ze teruggaf)
 */

if (!customElements.get('lib-uploader')) {

class LibUploader extends HTMLElement {
    static get observedAttributes() {
        return ['button-text', 'drop-text', 'disabled'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._files = [];
        this._busy = 0;
    }

    connectedCallback() {
        if (!this.shadowRoot.querySelector('.uploader')) {
            this._render();
            this._bind();
        }
        this._iconen();
    }

    attributeChangedCallback(name) {
        var root = this.shadowRoot;
        if (!root || !root.querySelector('.uploader')) { return; }
        if (name === 'button-text') { root.querySelector('.label').textContent = this.buttonText; }
        if (name === 'drop-text') { root.querySelector('.drop').textContent = this.dropText; }
        if (name === 'disabled') { root.querySelector('.uploader').classList.toggle('disabled', this.hasAttribute('disabled')); }
    }

    // --- Eigenschappen ---

    get endpoint() { return this.getAttribute('endpoint') || 'upload_handler.php'; }
    get field() { return this.getAttribute('field') || ''; }
    get path() { return this.getAttribute('path') || ''; }
    get pathExtra() { return this.getAttribute('path-extra') || ''; }
    get extensions() {
        var e = this.getAttribute('extensions');
        return e ? e.split(',').map(function (x) { return x.trim().toLowerCase(); }).filter(Boolean) : ['pdf', 'doc', 'docx', 'rtf', 'jpg', 'jpeg'];
    }
    get maxSize() {
        var eigen = parseInt(this.getAttribute('max-size'), 10);
        if (eigen > 0) { return eigen; }
        var mb = window.CMA && window.CMA.settings && parseInt(window.CMA.settings.uploadMaxMb, 10);
        return mb > 0 ? mb * 1048576 : 10485760;
    }
    get buttonText() { return this.getAttribute('button-text') || 'Plaats bestand'; }
    get dropText() { return this.getAttribute('drop-text') || 'Laat het bestand hier los'; }
    get typeError() { return this.getAttribute('type-error') || ''; }
    get isMultiple() { return this.hasAttribute('multiple'); }
    get linkBase() { return this.getAttribute('link-base') || this.path; }
    get files() { return this._files.slice(); }

    /** Alles leegmaken: de lijst, de keuze en het verborgen veld. */
    reset() {
        this._files = [];
        var root = this.shadowRoot;
        root.querySelector('.list').innerHTML = '';
        root.querySelector('input[type="file"]').value = '';
        root.querySelector('.uploader').classList.remove('has-files');
        this._schrijfVeld();
    }

    // --- Opbouw ---

    _render() {
        this.shadowRoot.innerHTML = '<style>' + LibUploader.CSS + '</style>'
            + '<div class="uploader' + (this.hasAttribute('disabled') ? ' disabled' : '') + '">'
            + '<div class="button" role="button" tabindex="0"><span class="icon up"></span><span class="label"></span><input type="file"' + (this.isMultiple ? ' multiple' : '') + ' tabindex="-1"></div>'
            + '<div class="drop"></div>'
            + '<ul class="list"></ul>'
            + '</div>';
        this.shadowRoot.querySelector('.label').textContent = this.buttonText;
        this.shadowRoot.querySelector('.drop').textContent = this.dropText;
        var accept = this.extensions.map(function (e) { return '.' + e; }).join(',');
        this.shadowRoot.querySelector('input[type="file"]').setAttribute('accept', accept);
    }

    /** De pictogrammen verschijnen alleen als het lettertype LinearIcons op de pagina aanwezig is (anders zouden het lege vakjes zijn). */
    _iconen() {
        var self = this;
        var zet = function () {
            var ok = false;
            try { ok = document.fonts && document.fonts.check('1em LinearIcons'); } catch (e) { ok = false; }
            self.shadowRoot.querySelector('.uploader').classList.toggle('iconen', !!ok);
        };
        zet();
        // Een lettertype dat de pagina wel declareert maar nog niet gebruikt is nog niet geladen: laat het laden en kijk daarna opnieuw.
        if (document.fonts && document.fonts.load) { document.fonts.load('1em LinearIcons').then(zet, zet); }
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(zet); }
    }

    _bind() {
        var self = this;
        var root = this.shadowRoot;
        var knop = root.querySelector('.button');
        var invoer = root.querySelector('input[type="file"]');
        var wrapper = root.querySelector('.uploader');

        knop.addEventListener('click', function (e) {
            if (e.target === invoer) { return; }
            if (!self.hasAttribute('disabled')) { invoer.click(); }
        });
        knop.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && !self.hasAttribute('disabled')) { e.preventDefault(); invoer.click(); }
        });
        invoer.addEventListener('change', function () {
            if (invoer.files.length) { self._verwerk(invoer.files); }
            invoer.value = '';
        });

        // Slepen: het dropvlak verschijnt zodra er bestanden boven de uploader hangen.
        var teller = 0;
        var heeftBestanden = function (e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1; };
        this.addEventListener('dragenter', function (e) { if (heeftBestanden(e) && !self.hasAttribute('disabled')) { e.preventDefault(); teller++; wrapper.classList.add('dragover'); } });
        this.addEventListener('dragover', function (e) { if (heeftBestanden(e) && !self.hasAttribute('disabled')) { e.preventDefault(); } });
        this.addEventListener('dragleave', function () { teller = Math.max(0, teller - 1); if (teller === 0) { wrapper.classList.remove('dragover'); } });
        this.addEventListener('drop', function (e) {
            if (!heeftBestanden(e) || self.hasAttribute('disabled')) { return; }
            e.preventDefault();
            teller = 0;
            wrapper.classList.remove('dragover');
            self._verwerk(e.dataTransfer.files);
        });
    }

    // --- Verwerken ---

    _verwerk(lijst) {
        var bestanden = Array.prototype.slice.call(lijst);
        if (!this.isMultiple) { bestanden = bestanden.slice(0, 1); }
        for (var i = 0; i < bestanden.length; i++) {
            var f = bestanden[i];
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (this.extensions.indexOf(ext) === -1) { this._fout(this.typeError || this._typeMelding(), f.name); continue; }
            if (f.size > this.maxSize) { this._fout('Bestand te groot (max. ' + Math.round(this.maxSize / 1048576) + ' MB)', f.name); continue; }
            this._upload(f);
        }
    }

    _upload(file) {
        var self = this;
        var lijst = this.shadowRoot.querySelector('.list');
        // Eén bestand: een eerder bestand (en een eerdere foutmelding) maakt plaats.
        if (!this.isMultiple) { this._files = []; lijst.innerHTML = ''; }
        var rij = document.createElement('li');
        rij.className = 'bezig';
        rij.innerHTML = '<span class="spinner"></span><span class="naam"></span><span class="grootte"></span>';
        rij.querySelector('.naam').textContent = file.name;
        rij.querySelector('.grootte').textContent = this._grootte(file.size);
        lijst.appendChild(rij);
        this.shadowRoot.querySelector('.uploader').classList.add('has-files');
        this._busy++;
        this.dispatchEvent(new CustomEvent('upload-start', { bubbles: true, detail: { fileName: file.name } }));

        var url = this.endpoint, q = [];
        if (this.path) { q.push('path=' + encodeURIComponent(this.path)); }
        if (this.pathExtra) { q.push('path_extra=' + encodeURIComponent(this.pathExtra)); }
        if (q.length) { url += (url.indexOf('?') === -1 ? '?' : '&') + q.join('&'); }
        var data = new FormData();
        data.append('qqfile', file);

        var klaar = function () { self._busy--; };
        var xhr = new XMLHttpRequest();
        xhr.addEventListener('load', function () {
            klaar();
            var antwoord;
            try { antwoord = JSON.parse(xhr.responseText); } catch (e) { antwoord = null; }
            if (!antwoord) { rij.remove(); self._fout('Ongeldig antwoord van de server', file.name); return; }
            if (!antwoord.success) { rij.remove(); self._fout(antwoord.error || 'Upload mislukt', file.name); return; }
            var naam = antwoord.filename || file.name;
            self._geplaatst(rij, naam, file.name);
            self.dispatchEvent(new CustomEvent('upload-complete', { bubbles: true, detail: { filename: naam, originalName: file.name, path: antwoord.path || '' } }));
        });
        xhr.addEventListener('error', function () { klaar(); rij.remove(); self._fout('Netwerkfout bij het uploaden', file.name); });
        xhr.open('POST', url, true);
        xhr.send(data);
    }

    /** De rij van een geplaatst bestand: pictogram, de naam als link (de hele rij is klikbaar), de grootte en eventueel een kruisje. */
    _geplaatst(rij, serverNaam, oorspronkelijk) {
        var self = this;
        var ext = (oorspronkelijk.split('.').pop() || '').toLowerCase();
        var basis = this.linkBase;
        if (basis && basis.charAt(basis.length - 1) !== '/') { basis += '/'; }
        var link = document.createElement('a');
        link.href = basis + this.pathExtra + serverNaam;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = oorspronkelijk;
        link.title = oorspronkelijk;
        var grootte = rij.querySelector('.grootte').textContent;
        rij.className = 'klaar' + (/^(jpe?g|png|gif|bmp|webp)$/.test(ext) ? ' afbeelding' : '');
        rij.innerHTML = '<span class="icon file"></span><span class="naam"></span><span class="grootte"></span>';
        rij.querySelector('.naam').appendChild(link);
        rij.querySelector('.grootte').textContent = grootte;
        if (this.hasAttribute('removable')) {
            var kruis = document.createElement('button');
            kruis.type = 'button';
            kruis.className = 'verwijder';
            kruis.title = 'Verwijderen';
            kruis.innerHTML = '<span class="icon cross"></span>';
            kruis.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                rij.remove();
                self._files = self._files.filter(function (n) { return n !== serverNaam; });
                if (!self._files.length) { self.shadowRoot.querySelector('.uploader').classList.remove('has-files'); }
                self._schrijfVeld();
            });
            rij.appendChild(kruis);
        }
        this._files.push(serverNaam);
        this._schrijfVeld();
    }

    /** Het verborgen veld van het formulier bijwerken: "<path-extra><naam>", bij meerdere bestanden gescheiden door ";". */
    _schrijfVeld() {
        if (!this.field) { return; }
        var veld = document.getElementById(this.field);
        if (!veld) { return; }
        var voor = this.pathExtra;
        veld.value = this._files.map(function (n) { return voor + n; }).join(';');
        veld.dispatchEvent(new Event('change', { bubbles: true }));
    }

    _fout(melding, bestand) {
        var lijst = this.shadowRoot.querySelector('.list');
        if (!this.isMultiple) { lijst.innerHTML = ''; this.shadowRoot.querySelector('.uploader').classList.remove('has-files'); }
        var rij = document.createElement('li');
        rij.className = 'fout';
        rij.innerHTML = '<span class="naam"></span>';
        rij.querySelector('.naam').innerHTML = melding;
        lijst.appendChild(rij);
        this.shadowRoot.querySelector('.uploader').classList.add('has-files');
        this.dispatchEvent(new CustomEvent('upload-error', { bubbles: true, detail: { error: melding, fileName: bestand } }));
        // Ook een melding in een venster als de pagina die heeft (zoals bij de eerdere uploaders).
        if (typeof window.libAlert === 'function') { window.libAlert(melding, { type: 'danger' }); }
        else if (typeof window.modal_alert === 'function') { window.modal_alert(melding); }
    }

    _typeMelding() {
        var namen = { pdf: 'Adobe Acrobat (.pdf)', doc: 'Word (.doc)', docx: 'Word (.docx)', rtf: 'Rich Text (.rtf)', jpg: 'JPG afbeeldingen', jpeg: 'JPEG afbeeldingen', png: 'PNG afbeeldingen',
            ppt: 'Powerpoint (.ppt)', pptx: 'Powerpoint (.pptx)', xls: 'Excel (.xls)', xlsx: 'Excel (.xlsx)', m4a: 'Audio (.m4a)', mov: 'Video (.mov)', mp4: 'Video (.mp4)' };
        var groep = { jpeg: 'jpg', docx: 'doc', pptx: 'ppt' };
        var gezien = {}, regels = [];
        this.extensions.forEach(function (e) {
            var g = groep[e] || e;
            if (g !== e && gezien[g]) { return; }
            gezien[e] = true;
            regels.push('- ' + (namen[e] || '.' + e));
        });
        return 'De volgende bestandstypen zijn toegestaan:<br>' + regels.join('<br>');
    }

    _grootte(bytes) {
        if (bytes >= 1048576) { return (Math.round(bytes / 104857.6) / 10) + ' MB'; }
        return Math.max(0.1, Math.round(bytes / 102.4) / 10) + ' kB';
    }
}

LibUploader.CSS = [
    ':host { display: block; max-width: 420px; margin: 3px 0 12px; font: inherit; color: var(--lib-uploader-text, var(--dark_purple, #220037)) }',
    ':host([hidden]) { display: none }',
    '.uploader { position: relative; --a: var(--lib-uploader-accent, var(--lighter_purple, #b25cff)); --t: var(--lib-uploader-text, var(--dark_purple, #220037)); --tint: var(--lib-uploader-tint, rgba(178, 92, 255, .10)); --hover: var(--lib-uploader-hover, #ead9ff); --danger: var(--lib-uploader-danger, var(--btn-delete, #c0392b)) }',
    '.uploader.iconen .icon::before { font-family: "LinearIcons"; font-style: normal; font-weight: 400; speak: none }',
    '.uploader.iconen .icon.up::before { content: "\\e8f4"; color: var(--a); margin-right: 8px; vertical-align: middle; font-size: 1.1em }',
    '.uploader.iconen .icon.file::before { content: "\\e6b3"; color: var(--a); font-size: 1.15em; vertical-align: middle }',
    '.uploader.iconen li.afbeelding .icon.file::before { content: "\\e90d" }',
    '.uploader.iconen .icon.cross::before { content: "\\e92a"; color: var(--t) }',
    '.button { position: relative; box-sizing: border-box; display: block; min-height: 44px; padding: 11px 16px; border: 1.5px dashed var(--a); border-radius: 12px; background: var(--tint); color: var(--t); font-weight: 600; line-height: 22px; text-align: center; cursor: pointer; user-select: none; transition: background-color .15s }',
    '.button:hover, .button:focus-visible { background: var(--hover); border-style: solid; outline: none }',
    '.button input { position: absolute; width: 0; height: 0; opacity: 0; pointer-events: none }',
    '.uploader.disabled .button { opacity: .5; cursor: not-allowed }',
    '.uploader.has-files .button { border-bottom-style: solid; border-bottom-left-radius: 0; border-bottom-right-radius: 0 }',
    '.drop { display: none; position: absolute; inset: 0; z-index: 5; box-sizing: border-box; padding-top: 11px; border: 2px dashed var(--a); border-radius: 12px; background: var(--hover); color: var(--t); font-weight: 600; line-height: 22px; text-align: center }',
    '.uploader.dragover .drop { display: block }',
    '.list { list-style: none; margin: 0; padding: 0 }',
    '.list li { position: relative; display: flex; align-items: center; gap: 8px; box-sizing: border-box; min-height: 36px; padding: 6px 12px; border: 1px solid var(--a); border-radius: 12px; background: #fff; color: var(--t) }',
    '.uploader.has-files .list li:first-child { border-top-width: 0; border-top-left-radius: 0; border-top-right-radius: 0 }',
    '.list li + li { margin-top: 6px }',
    '.list li.klaar { cursor: pointer; transition: background-color .15s }',
    '.list li.klaar:hover { background: var(--hover) }',
    '.naam { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap }',
    '.naam a { color: var(--t); text-decoration: none; font-weight: 400 }',
    '.naam a::after { content: ""; position: absolute; inset: 0; border-radius: 12px }',
    '.grootte { flex: 0 0 auto; font-size: .85em; opacity: .7 }',
    '.verwijder { position: relative; z-index: 1; flex: 0 0 auto; padding: 2px 4px; border: 0; background: transparent; color: var(--t); cursor: pointer }',
    '.list li.fout { border-color: var(--danger) }',
    '.list li.fout .naam { color: var(--danger); white-space: normal }',
    '.spinner { flex: 0 0 auto; width: 16px; height: 16px; border: 2px solid rgba(178, 92, 255, .35); border-top-color: var(--t); border-radius: 50%; animation: draai .7s linear infinite }',
    '@keyframes draai { to { transform: rotate(360deg) } }',
    '@media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2.5s } }'
].join('\n');

customElements.define('lib-uploader', LibUploader);
}
