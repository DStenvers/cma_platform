/**
 * Een alleen-lezen of uitgeschakelde keuzelijst is niet te wissen.
 *
 * lib-combo zette bij een gekozen waarde altijd een wis-kruisje (.combo-clear) neer, en
 * bij een meervoudige keuze een verwijderknopje per tag (.combo-tag-remove), ook als het
 * veld readonly of disabled was. Een klik op het kruisje maakte de waarde leeg en stuurde
 * een change-event, waarna het formulier "gewijzigd" was en Opslaan de lege waarde
 * meestuurde. Gevonden op Rooster > Evaluatie verzoeken: het alleen-lezen veld
 * Roosteritem liet zich met één klik leegmaken.
 *
 * Run: node tests/js/run.js keuzelijst-alleenlezen
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const COMBO = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-combo.js');

function bouw(attrs) {
    const dom = new JSDOM(`<!doctype html><html><body>
        <lib-combo name="fkRooster" ${attrs}>
            <option value=""></option>
            <option value="5215">GZ2026-C - 02 sep 2026</option>
        </lib-combo>
        <lib-combo name="Doelgroep" multiple ${attrs}></lib-combo>
        </body></html>`,
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' });
    const doc = dom.window.document;
    const s = doc.createElement('script');
    s.textContent = 'var cmaLog = { log() {}, warn() {}, error() {} };\n' + fs.readFileSync(COMBO, 'utf8');
    doc.body.appendChild(s);
    const enkel = doc.querySelector('[name="fkRooster"]');
    enkel.value = '5215';
    const meer = doc.querySelector('[name="Doelgroep"]');
    meer.setOptions([{ value: '1', label: 'A' }, { value: '2', label: 'B' }]);
    meer.value = ['1', '2'];
    return { doc, enkel, meer };
}

suite('Alleen-lezen keuzelijst is niet te wissen');

test('gewone keuzelijst: het wis-kruisje is er en wist (zo hoort het)', () => {
    const { enkel } = bouw('');
    const kruis = enkel.shadowRoot.querySelector('.combo-clear');
    assert.waar(!!kruis, 'geen wis-kruisje op een gewone keuzelijst');
    kruis.click();
    assert.gelijk(enkel.value, '');
});

for (const attr of ['readonly', 'disabled']) {
    test(`${attr}: geen wis-kruisje, de waarde blijft staan`, () => {
        const { enkel } = bouw(attr);
        assert.gelijk(enkel.shadowRoot.querySelector('.combo-clear'), null, 'er staat een wis-kruisje');
        assert.gelijk(enkel.value, '5215');
    });

    test(`${attr}: meervoudige keuze zonder verwijderknopjes per tag`, () => {
        const { meer } = bouw(attr);
        assert.gelijk(meer.shadowRoot.querySelectorAll('.combo-tag').length, 2, 'de tags zelf horen er wel te staan');
        assert.gelijk(meer.shadowRoot.querySelector('.combo-tag-remove'), null, 'er staat een verwijderknopje');
    });
}

test('readonly achteraf gezet (bv. alleen wijzigbaar bij een nieuw record): kruisje verdwijnt', () => {
    const { enkel } = bouw('');
    enkel.setAttribute('readonly', '');
    assert.gelijk(enkel.shadowRoot.querySelector('.combo-clear'), null, 'kruisje bleef staan na readonly');
    enkel.removeAttribute('readonly');
    assert.waar(!!enkel.shadowRoot.querySelector('.combo-clear'), 'kruisje kwam niet terug na het weghalen van readonly');
});
