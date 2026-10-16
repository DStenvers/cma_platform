/**
 * Een wijziging die alleen in een keuzelijst zit, is een wijziging.
 *
 * saveRecord() slaat een bestaand record niet op als getChangedFields() niets vindt.
 * Die vergelijking keek alleen naar new FormData(form) plus vinkjes. lib-combo is niet
 * form-associated en zit dus nooit in FormData; de datum-/tijdkiezer en de radiogroep
 * alleen waar de browser ElementInternals heeft. Gevolg: alleen een keuzelijst wijzigen
 * en dan Opslaan deed niets (geen request, "Geen wijzigingen om op te slaan").
 *
 * Dezelfde blinde vlek zat in buildChangelog(): die loopt over form.elements, waar
 * lib-combo niet in staat. Afterpost-logica die in _changelog_flds kijkt of veld X
 * gewijzigd is, zag een gewijzigde keuzelijst dus nooit.
 *
 * De test draait de echte methoden uit form-controller.js op een echte lib-combo
 * (library/webcomponents/lib-combo.js) in jsdom. jsdom heeft geen ElementInternals,
 * dus de kiezers gedragen zich hier zoals in een engine zonder form-association.
 *
 * Run: node tests/js/run.js keuzelijst-wijziging
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const COMBO = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-combo.js');
const DATEPICKER = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-datepicker.js');

/** Knip een methode van de controller uit de echte bron (haakjes tellend). */
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

function knipConstante(src, naam) {
    const m = new RegExp('const ' + naam + " = '[^']*';").exec(src);
    if (!m) throw new Error(naam + ' niet gevonden in form-controller.js');
    return m[0];
}

function bouw() {
    const src = fs.readFileSync(CONTROLLER, 'utf8');
    const methoden = [
        'get originalValues()', 'componentFieldValue(', 'componentFieldLabel(',
        'getChangedFields(', 'captureOriginalValues(', 'buildChangelog(',
        'formChangeAdd(', 'specTrim(', 'specCharReplace('
    ].map(kop => knipMethode(src, kop)).join('\n');

    const dom = new JSDOM(`<!doctype html><html><body>
        <div class="form-layout" data-record-id="7">
        <form id="mainForm">
            <input type="hidden" id="_changelog" name="_changelog">
            <input type="hidden" id="_changelog_flds" name="_changelog_flds">
            <input type="hidden" id="_changelog_type" name="_changelog_type">
            <label for="Naam">Naam</label><input type="text" name="Naam" value="Jan">
            <lib-combo id="fkPraktijkOpleider_id" name="fkPraktijkOpleider" data-field="fkPraktijkOpleider" data-label="Praktijkopleider">
                <option value=""></option>
                <option value="12">Piet</option>
                <option value="13">Klaas</option>
            </lib-combo>
            <lib-combo name="chklst_Doelgroep" data-field="Doelgroep" multiple></lib-combo>
            <lib-datepicker name="Startdatum" data-field="Startdatum"></lib-datepicker>
        </form></div></body></html>`,
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' });
    const { window } = dom;
    const doc = window.document;

    for (const bestand of [COMBO, DATEPICKER]) {
        const s = doc.createElement('script');
        s.textContent = fs.readFileSync(bestand, 'utf8');
        doc.body.appendChild(s);
    }
    const s = doc.createElement('script');
    s.textContent = `
        var cmaLog = { log() {}, warn() {}, error() {} };
        function cmaGetRecordId(el) { return el.getAttribute('data-record-id') || ''; }
        ${knipConstante(src, 'CMA_VALUE_COMPONENTS')}
        class Controller {
            constructor() {
                this.mainForm = document.getElementById('mainForm');
                this.formLayout = document.querySelector('.form-layout');
            }
            ${methoden}
        }
        window.__maak = () => new Controller();
    `;
    doc.body.appendChild(s);

    // Het record zoals populate het neerzet, daarna de nulmeting.
    doc.querySelector('[name="fkPraktijkOpleider"]').value = '12';
    const lijst = doc.querySelector('[name="chklst_Doelgroep"]');
    lijst.setOptions([{ value: '1', label: 'A' }, { value: '2', label: 'B' }]);
    lijst.value = '1';
    doc.querySelector('lib-datepicker').value = '2026-01-05';
    const ctrl = window.__maak();
    ctrl.captureOriginalValues();
    return { window, doc, ctrl };
}

suite('Keuzelijst-wijziging telt als wijziging');

test('ongewijzigd formulier: geen wijzigingen (er wordt niet voor niets opgeslagen)', () => {
    const { ctrl } = bouw();
    assert.gelijk(JSON.stringify(ctrl.getChangedFields()), '[]');
});

test('alleen de keuzelijst gewijzigd: getChangedFields ziet het', () => {
    const { doc, ctrl } = bouw();
    doc.querySelector('[name="fkPraktijkOpleider"]').value = '13';
    const wijzigingen = ctrl.getChangedFields();
    assert.gelijk(wijzigingen.length, 1, JSON.stringify(wijzigingen));
    assert.gelijk(wijzigingen[0].field, 'fkPraktijkOpleider');
    assert.gelijk(wijzigingen[0].label, 'Praktijkopleider');
    assert.gelijk(wijzigingen[0].oldValue, '12');
    assert.gelijk(wijzigingen[0].newValue, '13');
});

test('meervoudige keuzelijst (checklist) gewijzigd: gezien, als kommalijst', () => {
    const { doc, ctrl } = bouw();
    doc.querySelector('[name="chklst_Doelgroep"]').value = ['1', '2'];
    const wijzigingen = ctrl.getChangedFields();
    assert.gelijk(wijzigingen.length, 1, JSON.stringify(wijzigingen));
    assert.gelijk(wijzigingen[0].newValue, '1,2');
});

test('alleen de datum gewijzigd: gezien, ook zonder form-association', () => {
    const { doc, ctrl } = bouw();
    doc.querySelector('lib-datepicker').value = '2026-02-01';
    const wijzigingen = ctrl.getChangedFields();
    assert.gelijk(wijzigingen.length, 1, JSON.stringify(wijzigingen));
    assert.gelijk(wijzigingen[0].field, 'Startdatum');
});

test('buildChangelog zet een gewijzigde keuzelijst in _changelog_flds, met de labels', () => {
    const { doc, ctrl } = bouw();
    doc.querySelector('[name="fkPraktijkOpleider"]').value = '13';
    assert.waar(ctrl.buildChangelog(), 'buildChangelog meldt een wijziging');
    assert.gelijk(doc.getElementById('_changelog_flds').value, 'fkPraktijkOpleider');
    const log = doc.getElementById('_changelog').value;
    assert.waar(log.includes('Piet') && log.includes('Klaas'), 'was/gewijzigd in als tekst: ' + log);
});

test('buildChangelog zonder wijziging blijft leeg', () => {
    const { doc, ctrl } = bouw();
    assert.onwaar(ctrl.buildChangelog(), 'geen wijziging gemeld');
    assert.gelijk(doc.getElementById('_changelog_flds').value, '');
});

test('een gewijzigd tekstveld werkt nog zoals voorheen', () => {
    const { doc, ctrl } = bouw();
    doc.querySelector('[name="Naam"]').value = 'Joop';
    const wijzigingen = ctrl.getChangedFields();
    assert.gelijk(wijzigingen.length, 1, JSON.stringify(wijzigingen));
    assert.gelijk(wijzigingen[0].field, 'Naam');
    ctrl.buildChangelog();
    assert.gelijk(doc.getElementById('_changelog_flds').value, 'Naam');
});
