/**
 * Het hoofdscherm boven de sleepbalk blijft zichtbaar.
 *
 * Melding: "als een splitter een top positie heeft van minder dan 50px, zorg dat
 * het hoofdscherm zichtbaar blijft, nu lijkt het hoofdscherm weg en zie je alleen
 * de tabs".
 *
 * De horizontale balk tussen formulier en subform-sectie (reverse: het doel ligt
 * ONDER de balk) begrensde de sectie alleen op max-size="800". Op een paneel dat
 * lager is dan 800px plus de balk kreeg de sectie dan alle ruimte: de balk schoof
 * tegen de bovenkant en het formulier erboven verdween. Dat gebeurde bij slepen,
 * bij de pijl "maximaliseren", bij een bewaarde stand van een groter scherm, en
 * bij het verkleinen van het venster.
 *
 * Nu houdt de balk vóór zich altijd min-voor (standaard 50px) vrij.
 *
 * jsdom rekent geen layout uit; het paneel en de balk krijgen daarom vaste maten,
 * en de sectie is zo hoog als zijn inline hoogte — precies wat flex:0 0 Xpx doet.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const FOLD = path.join(__dirname, '..', '..', 'webcomponents', 'cma-fold.js');

function maakPaneel({ hoogte = 500, bewaard = null } = {}) {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<div class="detail-panel" id="paneel" style="display:flex;flex-direction:column">' +
        '<div class="detail-content" id="detailContent"></div>' +
        '<cma-fold class="fold-horizontal" orientation="horizontal" target=".subform-section"' +
        ' reverse min-size="120" collapsed-size="120" max-size="800" default-size="250" storage-key="form_foldH"></cma-fold>' +
        '<div class="subform-section" id="subformSection"></div>' +
        '</div></body></html>',
        { runScripts: 'outside-only', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' }
    );
    const win = dom.window;
    const doc = win.document;
    if (bewaard) win.localStorage.setItem('cma_fold_form_foldH', bewaard);

    const paneel = doc.getElementById('paneel');
    const sectie = doc.getElementById('subformSection');
    const balk = doc.querySelector('cma-fold');
    const maat = { paneel: hoogte };
    Object.defineProperty(paneel, 'clientHeight', { get: () => maat.paneel });
    Object.defineProperty(balk, 'offsetHeight', { get: () => 8 });
    Object.defineProperty(sectie, 'offsetHeight', { get: () => parseInt(sectie.style.height, 10) || 0 });

    win.eval(fs.readFileSync(FOLD, 'utf8'));
    return { win, paneel, sectie, balk, maat };
}

suite('Sleepbalk: het hoofdscherm erboven blijft zichtbaar');

test('een bewaarde stand van een groter scherm laat 50px voor het formulier over', () => {
    const p = maakPaneel({ hoogte: 500, bewaard: '{"size":800,"collapsed":false}' });
    // 500 paneel - 8 balk - 50 voor het formulier
    assert.gelijk(p.sectie.style.height, '442px');
});

test('slepen tot tegen de bovenkant stopt 50px eronder', () => {
    const p = maakPaneel({ hoogte: 500, bewaard: '{"size":250,"collapsed":false}' });
    p.balk._startDrag({ clientY: 300 });
    p.balk._onMouseMove({ clientY: -2000 });
    assert.gelijk(p.sectie.style.height, '442px');
    p.balk._onMouseUp({});
});

test('de pijl "maximaliseren" schuift de balk niet tegen de bovenkant', () => {
    const p = maakPaneel({ hoogte: 500, bewaard: '{"size":250,"collapsed":false}' });
    // reverse: de pijl omhoog (collapse) maakt het doel zo groot mogelijk
    p.balk.shadowRoot.querySelector('[data-action="collapse"]').click();
    assert.gelijk(p.sectie.style.height, '442px');
});

test('een kleiner paneel duwt de sectie terug, zonder de eigen maat te vergeten', () => {
    const p = maakPaneel({ hoogte: 700, bewaard: '{"size":500,"collapsed":false}' });
    assert.gelijk(p.sectie.style.height, '500px', 'past nog: ongewijzigd');
    p.maat.paneel = 400;
    p.balk._houdRuimteVoor();
    assert.gelijk(p.sectie.style.height, '342px');
    assert.gelijk(JSON.parse(p.win.localStorage.getItem('cma_fold_form_foldH')).size, 500,
        'de bewaarde maat blijft voor een groter scherm');
});

test('zonder layout (paneel nog 0 hoog) geldt gewoon max-size', () => {
    const p = maakPaneel({ hoogte: 0, bewaard: '{"size":300,"collapsed":false}' });
    assert.gelijk(p.sectie.style.height, '300px');
});

test('een gewone balk (niet reverse) houdt zijn oude begrenzing', () => {
    const dom = new JSDOM(
        '<!doctype html><html><body><div id="p" style="display:flex">' +
        '<div id="lijst"></div><cma-fold orientation="vertical" target="#lijst" min-size="150" max-size="600" storage-key="v"></cma-fold>' +
        '</div></body></html>',
        { runScripts: 'outside-only', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' }
    );
    dom.window.localStorage.setItem('cma_fold_v', '{"size":900}');
    dom.window.eval(fs.readFileSync(FOLD, 'utf8'));
    assert.gelijk(dom.window.document.getElementById('lijst').style.width, '600px');
});
