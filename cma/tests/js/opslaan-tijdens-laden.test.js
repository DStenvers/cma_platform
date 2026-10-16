/**
 * Opslaan terwijl het record nog laadt doet niets - en het formulier neemt dan geen invoer aan.
 *
 * Gemeld: in Opleidingen > deelnemer een Hoofd-/jaargroepopleider kiezen en Opslaan gaf
 * "Vul alle verplichte velden in: Opleiding, Deelnemer", terwijl beide keuzelijsten zichtbaar
 * een waarde hadden, en er werd niets opgeslagen.
 *
 * Oorzaak: een bestaand record wordt na het tonen van het formulier via action=record
 * nageladen (body.data-loading). In dat venster stond het formulier al open voor invoer.
 * Wie dan een keuze maakte en op Opslaan klikte, liet validateForm() de nog lege verplichte
 * keuzelijsten zien; even later kwam het record binnen, vulde de keuzelijsten (vandaar dat
 * ze zichtbaar gevuld waren) en overschreef de gemaakte keuze met de opgeslagen waarde.
 *
 * Nu: form.css maakt #mainForm inert zolang body.data-loading staat (niet in toevoegmodus),
 * en saveRecord() weigert zolang isRecordLoading() waar is, vóór de validatie.
 *
 * Run: node tests/js/run.js opslaan-tijdens-laden
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const FORM_CSS = path.join(__dirname, '..', '..', 'assets', 'css', 'form.css');

function knipMethode(src, kop) {
    const start = src.indexOf('\n    ' + kop);
    if (start === -1) throw new Error(kop + ' niet gevonden in form-controller.js');
    let i = src.indexOf('{', start), diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}' && --diepte === 0) return src.slice(start + 1, i + 1);
    }
    throw new Error('einde van ' + kop + ' niet gevonden');
}

function bouw(bodyKlassen) {
    const src = fs.readFileSync(CONTROLLER, 'utf8');
    const methoden = ['isRecordLoading(', 'async saveRecord('].map(k => knipMethode(src, k)).join('\n');
    const dom = new JSDOM(`<!doctype html><html><body class="${bodyKlassen}">
        <div class="form-layout" data-record-id="3975"><form id="mainForm"></form></div></body></html>`,
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' });
    const { window } = dom;
    const s = window.document.createElement('script');
    s.textContent = `
        var cmaLog = { log() {}, warn() {}, error() {} };
        var cmaPerf = { start() {}, end() {}, mark() {}, measure() {}, count() {}, gauge() {} };
        function cmaGetRecordId(el) { return el.getAttribute('data-record-id') || ''; }
        window.__fetches = 0;
        window.fetch = async () => { window.__fetches++; return { ok: true, text: async () => '{"success":false}' }; };
        class Controller {
            constructor() {
                this.mainForm = document.getElementById('mainForm');
                this.formLayout = document.querySelector('.form-layout');
                this.gevalideerd = 0;
                this.meldingen = [];
            }
            verifyIdentity() {}
            validateForm() { this.gevalideerd++; return false; }
            showTopNotification(m) { this.meldingen.push(m); }
            showLoading() {} hideLoading() {}
            ${methoden}
        }
        window.__maak = () => new Controller();
    `;
    window.document.body.appendChild(s);
    return { window, ctrl: window.__maak() };
}

suite('Opslaan tijdens het laden van een record');

test('record laadt nog (body.data-loading): isRecordLoading is waar', () => {
    const { ctrl } = bouw('has-record data-loading');
    assert.waar(ctrl.isRecordLoading());
});

test('record geladen: isRecordLoading is onwaar', () => {
    const { ctrl } = bouw('has-record');
    assert.onwaar(ctrl.isRecordLoading());
});

test('toevoegmodus telt nooit als laden', () => {
    const { ctrl } = bouw('is-creating data-loading');
    assert.onwaar(ctrl.isRecordLoading());
});

test('een loadRecord() die nog loopt telt als laden', () => {
    const { ctrl } = bouw('has-record');
    ctrl._loadingRecordPromise = new Promise(() => {});
    assert.waar(ctrl.isRecordLoading());
});

test('Opslaan tijdens het laden: geen validatie (geen valse "verplichte velden"), geen request', async () => {
    const { window, ctrl } = bouw('has-record data-loading');
    await ctrl.saveRecord();
    assert.gelijk(ctrl.gevalideerd, 0, 'validateForm mag niet draaien op een half geladen formulier');
    assert.gelijk(window.__fetches, 0);
    assert.gelijk(ctrl.meldingen.length, 1);
});

test('Opslaan na het laden: valideert gewoon', async () => {
    const { ctrl } = bouw('has-record');
    await ctrl.saveRecord();
    assert.gelijk(ctrl.gevalideerd, 1);
});

test('form.css: #mainForm neemt geen muisinvoer aan zolang het record laadt (niet in toevoegmodus)', () => {
    const css = fs.readFileSync(FORM_CSS, 'utf8');
    assert.waar(/body\.data-loading:not\(\.is-creating\)\s+#mainForm\s*\{\s*pointer-events:\s*none;/.test(css));
});
