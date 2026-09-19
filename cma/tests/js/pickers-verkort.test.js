/**
 * Verkorte invoer in de datum- en tijdkiezer, zoals formval_nl.js die in de oude
 * CMA accepteerde: "5" is de vijfde van deze maand, "0101" is 1 januari van dit
 * jaar, "010126" en "01012026" zijn 1-1-2026; "9" is 09:00, "9.3" is 09:30,
 * "930" is 09:30 en "9:1" is 09:15. Ongeldige invoer (dag 32, uur 25) wordt
 * niet stilzwijgend weggegooid maar benoemd via setCustomValidity.
 *
 * De echte methodes worden uit de componenten geknipt, zodat dit de code test die
 * ook wordt uitgeleverd.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const DATEPICKER = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-datepicker.js');
const TIMEPICKER = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-timepicker.js');

function haalMethodeUitBron(bestand, naam) {
    const src = fs.readFileSync(bestand, 'utf8');
    const match = new RegExp('\\n[ \\t]*' + naam + '\\s*\\(').exec(src);
    if (!match) throw new Error('Methode ' + naam + ' niet gevonden in ' + path.basename(bestand));
    let i = src.indexOf('{', match.index);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}') { diepte--; if (diepte === 0) return src.slice(match.index, i + 1); }
    }
    throw new Error('Einde van ' + naam + ' niet gevonden');
}

function bouw() {
    const dom = new JSDOM('<!doctype html><html><body><input class="datepicker-input"></body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' });
    const doc = dom.window.document;
    const script = doc.createElement('script');
    script.textContent =
        'window.__dp = { _format: "dd-mm-yyyy", gekozen: null, meldingen: [],' +
        '  shadowRoot: { querySelector: () => document.querySelector(".datepicker-input") },' +
        '  selectDate(v) { this.gekozen = v; }, ' +
        haalMethodeUitBron(DATEPICKER, 'parseInputValue') + ' };' +
        'window.__tp = { ' + haalMethodeUitBron(TIMEPICKER, '_normalizeTime') + ' };' +
        'document.querySelector(".datepicker-input").setCustomValidity = function(m) { if (m) window.__dp.meldingen.push(m); };';
    doc.body.appendChild(script);
    return dom.window;
}

const now = new Date();
const jaar = now.getFullYear();
const mm = String(now.getMonth() + 1).padStart(2, '0');

suite('Datumkiezer: verkorte invoer');

test('"5" wordt de vijfde van deze maand', () => {
    const w = bouw();
    w.__dp.parseInputValue('5');
    assert.gelijk(w.__dp.gekozen, `${jaar}-${mm}-05`);
});

test('"0101", "010126" en "01012026" worden 1 januari', () => {
    for (const [invoer, verwacht] of [['0101', `${jaar}-01-01`], ['010126', '2026-01-01'], ['01012026', '2026-01-01']]) {
        const w = bouw();
        w.__dp.parseInputValue(invoer);
        assert.gelijk(w.__dp.gekozen, verwacht, invoer);
    }
});

test('"1-1" en "1 1 26" krijgen jaar en nullen', () => {
    let w = bouw(); w.__dp.parseInputValue('1-1');
    assert.gelijk(w.__dp.gekozen, `${jaar}-01-01`);
    w = bouw(); w.__dp.parseInputValue('1 1 26');
    assert.gelijk(w.__dp.gekozen, '2026-01-01');
});

test('dag 32 en maand 13 worden benoemd, niet stil weggegooid', () => {
    let w = bouw(); w.__dp.parseInputValue('32-01-2026');
    assert.gelijk(w.__dp.gekozen, null);
    assert.waar(w.__dp.meldingen[0].indexOf('dag 32') >= 0, w.__dp.meldingen[0]);
    w = bouw(); w.__dp.parseInputValue('01-13-2026');
    assert.waar(w.__dp.meldingen[0].indexOf('maand 13') >= 0, w.__dp.meldingen[0]);
    w = bouw(); w.__dp.parseInputValue('31-04-2026');
    assert.gelijk(w.__dp.gekozen, null, '31 april bestaat niet');
});

suite('Tijdkiezer: verkorte invoer');

test('uren zonder minuten, kwartieren en cijferreeksen', () => {
    const w = bouw();
    const t = (v) => w.__tp._normalizeTime(v);
    assert.gelijk(t('9'), '09:00');
    assert.gelijk(t('9:'), '09:00');
    assert.gelijk(t('9.3'), '09:30');
    assert.gelijk(t('9 3'), '09:30');
    assert.gelijk(t('9:1'), '09:15');
    assert.gelijk(t('9:4'), '09:45');
    assert.gelijk(t('930'), '09:30');
    assert.gelijk(t('1730'), '17:30');
    assert.gelijk(t('09:30'), '09:30');
});

test('uur 25 of minuut 61 is ongeldig', () => {
    const w = bouw();
    assert.gelijk(w.__tp._normalizeTime('25:00'), '');
    assert.gelijk(w.__tp._normalizeTime('9:61'), '');
});
