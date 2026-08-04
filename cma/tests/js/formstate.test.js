/**
 * Bewijs voor de state-helpers van het formulier.
 *
 * De functies worden NIET nagebouwd: dit bestand knipt ze uit de echte
 * form-controller.js en voert ze uit in een jsdom-document. Wat hier slaagt,
 * slaagt dus in de code die wordt uitgeleverd.
 *
 * De afspraak die bewezen wordt: state hoort bij één formulier. Een tweede
 * formulier op dezelfde pagina mag er nooit door geraakt worden, en een
 * aanroep zonder formulier is een fout — geen stille gok op de eerste die
 * toevallig in het document staat.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');

/** Knip het blok met de state-helpers uit de echte bron. */
function haalHelpersUitBron() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('function cmaFormLayoutVan(');
    const eind = src.indexOf('class CmaFormController');
    if (start === -1 || eind === -1 || eind < start) {
        throw new Error('Kon de state-helpers niet in form-controller.js vinden');
    }
    return src.slice(start, eind);
}

/** Vers document met twee formulieren, plus de helpers erin geladen. */
function maakPagina() {
    const dom = new JSDOM(`<!doctype html><html><body>
        <div class="form-layout" id="formA" data-json-form="opleidingen">
            <form id="mainForm"><input name="titel" id="veldA"></form>
        </div>
        <div class="form-layout" id="formB" data-json-form="deelnemers">
            <form><input name="naam" id="veldB"></form>
        </div>
        <div id="buiten">geen formulier</div>
    </body></html>`, { runScripts: 'dangerously' });
    const win = dom.window;
    // Als <script> in het document, niet via eval: dan gedragen de
    // functiedeclaraties zich precies zoals in de browser (globals op window).
    const script = win.document.createElement('script');
    script.textContent = haalHelpersUitBron();
    win.document.body.appendChild(script);
    return {
        win,
        doc: win.document,
        formA: win.document.getElementById('formA'),
        formB: win.document.getElementById('formB')
    };
}

suite('Formulier-state: bij welk formulier hoort het');

test('een aanroep zonder formulier is een fout, geen gok', () => {
    const { win } = maakPagina();
    assert.gooit(() => win.cmaGetRecordId(), 'cmaGetRecordId() zonder element hoort te gooien');
    assert.gooit(() => win.cmaGetIsDirty(), 'cmaGetIsDirty() zonder element hoort te gooien');
    assert.gooit(() => win.cmaSetRecordId(5), 'cmaSetRecordId() zonder element hoort te gooien');
    assert.gooit(() => win.cmaSetIsDirty(true), 'cmaSetIsDirty() zonder element hoort te gooien');
});

test('een element buiten elk formulier is een fout', () => {
    const { win, doc } = maakPagina();
    assert.gooit(() => win.cmaGetRecordId(doc.getElementById('buiten')));
});

test('een element bínnen het formulier wijst het juiste formulier aan', () => {
    const { win, doc, formA } = maakPagina();
    win.cmaSetRecordId(42, doc.getElementById('veldA'));
    assert.gelijk(formA.dataset.recordId, '42');
    assert.gelijk(win.cmaGetRecordId(doc.getElementById('veldA')), '42');
});

suite('Recordnummer');

test('heen en weer', () => {
    const { win, formA } = maakPagina();
    assert.gelijk(win.cmaGetRecordId(formA), null, 'leeg formulier heeft geen record');
    win.cmaSetRecordId(7, formA);
    assert.gelijk(win.cmaGetRecordId(formA), '7');
});

test('een lege waarde wist het attribuut in plaats van "" te bewaren', () => {
    const { win, formA } = maakPagina();
    win.cmaSetRecordId(7, formA);
    win.cmaSetRecordId('', formA);
    assert.gelijk(win.cmaGetRecordId(formA), null);
    assert.onwaar(formA.hasAttribute('data-record-id'), 'attribuut hoort weg te zijn');
    win.cmaSetRecordId(7, formA);
    win.cmaSetRecordId(null, formA);
    assert.gelijk(win.cmaGetRecordId(formA), null);
});

test('het tweede formulier blijft ongemoeid', () => {
    const { win, formA, formB } = maakPagina();
    win.cmaSetRecordId(1, formA);
    win.cmaSetRecordId(2, formB);
    assert.gelijk(win.cmaGetRecordId(formA), '1', 'formulier A');
    assert.gelijk(win.cmaGetRecordId(formB), '2', 'formulier B');
    win.cmaSetRecordId(99, formB);
    assert.gelijk(win.cmaGetRecordId(formA), '1', 'A mag niet meebewegen met B');
});

suite('Gewijzigd-vlag');

test('heen en weer, per formulier', () => {
    const { win, formA, formB } = maakPagina();
    assert.onwaar(win.cmaGetIsDirty(formA), 'vers formulier is schoon');
    win.cmaSetIsDirty(true, formA);
    assert.waar(win.cmaGetIsDirty(formA));
    assert.onwaar(win.cmaGetIsDirty(formB), 'B is niet vies geworden van A');
    win.cmaSetIsDirty(false, formA);
    assert.onwaar(win.cmaGetIsDirty(formA));
});

test('de class op het formulier volgt de vlag', () => {
    const { win, formA } = maakPagina();
    win.cmaSetIsDirty(true, formA);
    assert.waar(formA.classList.contains('is-dirty'));
    win.cmaSetIsDirty(false, formA);
    assert.onwaar(formA.classList.contains('is-dirty'));
});

test('de body wordt niet meer aangeraakt', () => {
    const { win, doc, formA } = maakPagina();
    win.cmaSetIsDirty(true, formA);
    assert.onwaar(doc.body.classList.contains('is-dirty'),
        'er hoort geen tweede kopie op document.body te staan');
});

test('een class op de body bepaalt het antwoord niet', () => {
    const { win, doc, formA } = maakPagina();
    // Vroeger las de navigatiewacht deze class als terugval; hij ging vanzelf aan
    // doordat lib-combo en lib-switch bij het vullen een change uitsturen.
    doc.body.classList.add('is-dirty');
    assert.onwaar(win.cmaGetIsDirty(formA), 'de waarheid staat op het formulier');
});

test('twee formulieren, alleen het gevraagde is vies', () => {
    const { win, formA, formB } = maakPagina();
    win.cmaSetIsDirty(true, formB);
    assert.onwaar(win.cmaGetIsDirty(formA), 'A is niet vies');
    assert.waar(win.cmaGetIsDirty(formB), 'B is vies');
    assert.onwaar(formA.classList.contains('is-dirty'), 'ook de class blijft bij B');
    assert.waar(formB.classList.contains('is-dirty'));
});

test('een niet-booleaanse waarde wordt netjes waar of onwaar', () => {
    const { win, formA } = maakPagina();
    win.cmaSetIsDirty('ja', formA);
    assert.gelijk(formA.dataset.isdirty, 'true');
    win.cmaSetIsDirty(0, formA);
    assert.gelijk(formA.dataset.isdirty, 'false');
    assert.onwaar(win.cmaGetIsDirty(formA));
});
