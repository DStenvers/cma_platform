/**
 * De afterPostUrl krijgt dezelfde veldwaarden als het opslaan.
 *
 * executeAfterPost() postte new FormData(form) naar de afterPostUrl. lib-combo is niet
 * form-associated: van een keuzelijst zat alleen "<veld>__label" in die FormData, de
 * waarde zelf niet. De datum-/tijdkiezer en de radiogroep zitten er alleen in waar de
 * browser ElementInternals heeft. Een afterpost-handler kreeg zo bijvoorbeeld
 * fkOpleiding leeg binnen, terwijl het record mét die waarde was opgeslagen. Hetzelfde
 * gold voor de momentopname van het record vlak voor het verwijderen.
 *
 * afterPostFormData() vult de FormData aan met de waarde van elk value-component
 * (CMA_VALUE_COMPONENTS), zoals collectFormData() ze voor het opslaan post.
 *
 * De test draait de echte methoden uit form-controller.js op een echte lib-combo en
 * lib-datepicker in jsdom (zonder ElementInternals, dus zonder form-association).
 *
 * Run: node tests/js/run.js afterpost-waarden
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const WC = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents');

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

function bouw() {
    const src = fs.readFileSync(CONTROLLER, 'utf8');
    const methoden = ['async executeAfterPost(', 'afterPostFormData(', 'componentFieldValue(']
        .map(kop => knipMethode(src, kop)).join('\n');
    const constante = /const CMA_VALUE_COMPONENTS = '[^']*';/.exec(src);
    if (!constante) throw new Error('CMA_VALUE_COMPONENTS niet gevonden');

    const dom = new JSDOM(`<!doctype html><html><body>
        <div class="form-layout" data-record-id="7">
        <form id="mainForm">
            <input type="hidden" id="_changelog_type" name="_changelog_type" value="edit">
            <input type="text" name="Naam" value="Jan">
            <lib-combo name="fkOpleiding" data-field="fkOpleiding">
                <option value=""></option><option value="12">Huisarts</option><option value="13">Specialist</option>
            </lib-combo>
            <input type="hidden" name="fkOpleiding__label" value="Huisarts">
            <lib-combo name="chklst_Doelgroep" data-field="Doelgroep" multiple></lib-combo>
            <lib-datepicker name="Startdatum" data-field="Startdatum"></lib-datepicker>
            <lib-timepicker name="Starttijd" data-field="Starttijd"></lib-timepicker>
            <lib-combo name="_intern" data-field="_intern"><option value="9">x</option></lib-combo>
        </form></div></body></html>`,
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' });
    const { window } = dom;
    const doc = window.document;
    for (const f of ['lib-combo.js', 'lib-datepicker.js', 'lib-timepicker.js']) {
        const s = doc.createElement('script');
        s.textContent = fs.readFileSync(path.join(WC, f), 'utf8');
        doc.body.appendChild(s);
    }
    const s = doc.createElement('script');
    s.textContent = `
        var cmaLog = { log() {}, warn() {}, error() {} };
        window.posts = [];
        window.fetch = async (url, opts) => { window.posts.push({ url, body: opts.body });
            return { ok: true, text: async () => '' }; };
        ${constante[0]}
        class Controller {
            constructor() {
                this.mainForm = document.getElementById('mainForm');
                this.afterPostUrl = '/cma_afterpost.php?id=[ID]';
                this.config = { sourceFormId: 5, tableName: 'tblDeelname' };
                this.jsonForm = 'opleidingen_deelnemers';
            }
            showTopNotification(m) { window.fout = m; }
            showHtmlResponsePopup() {}
            ${methoden}
        }
        window.__maak = () => new Controller();
    `;
    doc.body.appendChild(s);

    doc.querySelector('[name="fkOpleiding"]').value = '12';
    const lijst = doc.querySelector('[name="chklst_Doelgroep"]');
    lijst.setOptions([{ value: '1', label: 'A' }, { value: '2', label: 'B' }]);
    lijst.value = ['1', '2'];
    doc.querySelector('lib-datepicker').value = '2026-01-05';
    doc.querySelector('lib-timepicker').value = '09:30';
    return { window, doc, ctrl: window.__maak() };
}

suite('Afterpost krijgt de veldwaarden van het opslaan');

test('na opslaan: keuzelijst, checklist, datum en tijd staan in de POST', async () => {
    const { window, ctrl } = bouw();
    await ctrl.executeAfterPost(7, 'edit');
    assert.gelijk(window.posts.length, 1, 'een POST: ' + (window.fout || ''));
    const body = window.posts[0].body;
    assert.gelijk(window.posts[0].url, '/cma_afterpost.php?id=7');
    assert.gelijk(body.get('fkOpleiding'), '12', 'waarde van de keuzelijst');
    assert.gelijk(body.get('fkOpleiding__label'), 'Huisarts', 'het label blijft meekomen');
    assert.gelijk(body.get('chklst_Doelgroep'), '1,2', 'checklist als kommalijst, zoals bij opslaan');
    assert.gelijk(body.get('Startdatum'), '2026-01-05');
    assert.gelijk(body.get('Starttijd'), '09:30');
    assert.gelijk(body.get('Naam'), 'Jan');
    assert.gelijk(body.get('_changelog_type'), 'edit');
    assert.gelijk(body.get('recid'), '7');
    assert.gelijk(body.getAll('fkOpleiding').length, 1, 'geen dubbele sleutel');
});

test('interne velden (_…) worden niet uit het component overschreven', () => {
    const { ctrl } = bouw();
    assert.gelijk(ctrl.afterPostFormData().has('_intern'), false);
});

test('bij verwijderen: de momentopname bewaart de waarden, ook als het formulier daarna leeg is', async () => {
    const { window, doc, ctrl } = bouw();
    const snapshot = ctrl.afterPostFormData();
    // Het formulier wordt na het verwijderen leeggemaakt.
    doc.querySelector('[name="fkOpleiding"]').value = '';
    doc.querySelector('lib-datepicker').value = '';
    await ctrl.executeAfterPost(7, 'delete', snapshot);
    const body = window.posts[0].body;
    assert.gelijk(body.get('fkOpleiding'), '12');
    assert.gelijk(body.get('Startdatum'), '2026-01-05');
    assert.gelijk(body.get('_changelog_type'), 'delete');
});

test('deleteRecord neemt de momentopname met afterPostFormData()', () => {
    const src = fs.readFileSync(CONTROLLER, 'utf8');
    assert.waar(/const afterPostSnapshot = \(this\.afterPostUrl && this\.mainForm\) \? this\.afterPostFormData\(\) : null;/.test(src),
        'de verwijder-momentopname gebruikt afterPostFormData()');
    assert.onwaar(/afterPostSnapshot = [^\n]*new FormData\(this\.mainForm\)/.test(src),
        'geen kale new FormData(form) meer als momentopname');
});
