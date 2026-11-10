/**
 * De shim onder de bovenste popup (lib_popup_shim in library/library.js).
 *
 * Scrollen op de achtergrond van een venster verschoof de pagina erachter. Eén transparante shim
 * ligt nu direct onder de bovenste popup en neemt wiel en aanraking over. Vensters kunnen worden
 * gestapeld, dus de shim hoort altijd bij de hoogste: zijn z-index is die van de bovenste
 * container min 1 en wordt bij elk openen en sluiten opnieuw bepaald.
 *
 * Draait tegen de echte bron.
 * Run: node tests/js/run.js popupshim
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBRARY = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

function nieuweShim() {
    const dom = new JSDOM('<!doctype html><html><body></body></html>');
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const start = src.indexOf('var lib_popup_shim = (function() {');
    if (start === -1) { throw new Error('lib_popup_shim niet gevonden'); }
    const eind = src.indexOf('\n})();', start);
    const body = src.slice(start, eind + 6).replace('var lib_popup_shim =', 'return');
    const shim = new Function('document', 'lib_alertbox_getbody_doc_element', 'MutationObserver', body)(
        dom.window.document, () => dom.window.document, dom.window.MutationObserver);
    return { shim, doc: dom.window.document, win: dom.window };
}

function venster(doc, id, z) {
    const el = doc.createElement('div');
    el.id = id;
    el.className = 'lib_window_container';
    el.style.zIndex = String(z);
    doc.body.appendChild(el);
    return el;
}

const shimEl = (doc) => doc.getElementById('__lib_shim');

suite('popupshim');

test('zonder popup is er geen shim', () => {
    const { shim, doc } = nieuweShim();
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc), null, 'shim');
});

test('met één venster ligt de shim er direct onder', () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc).style.zIndex, '998999', 'z-index van de shim');
});

test('bij gestapelde vensters hoort de shim bij het hoogste', () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    venster(doc, '__lib_win2', 999010);
    venster(doc, '__lib_win3', 999020);
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc).style.zIndex, '999019', 'z-index van de shim');
    assert.gelijk(doc.querySelectorAll('#__lib_shim').length, 1, 'er is maar één shim');
});

test('na het sluiten van het bovenste venster zakt de shim mee', () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    venster(doc, '__lib_win2', 999010);
    shim.bijwerken(doc);
    doc.getElementById('__lib_win2').remove();
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc).style.zIndex, '998999', 'z-index na sluiten');
});

test('een venster dat niet het bovenste is, sluiten laat de shim staan', () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    venster(doc, '__lib_win2', 999010);
    shim.bijwerken(doc);
    doc.getElementById('__lib_win1').remove();
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc).style.zIndex, '999009', 'z-index');
});

test('na het sluiten van het laatste venster verdwijnt de shim', () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    shim.bijwerken(doc);
    doc.getElementById('__lib_win1').remove();
    shim.bijwerken(doc);
    assert.gelijk(shimEl(doc), null, 'shim');
});

test('het venster ligt altijd boven de shim en de shim boven alle lagere vensters', () => {
    const { shim, doc } = nieuweShim();
    const onder = venster(doc, '__lib_win1', 999000);
    const boven = venster(doc, '__lib_win2', 999010);
    shim.bijwerken(doc);
    const z = parseInt(shimEl(doc).style.zIndex, 10);
    assert.waar(z > parseInt(onder.style.zIndex, 10), 'shim boven het onderste venster');
    assert.waar(z < parseInt(boven.style.zIndex, 10), 'shim onder het bovenste venster');
});

test('wiel en aanraking op de shim worden niet doorgegeven', () => {
    const { shim, doc, win } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    shim.bijwerken(doc);
    for (const soort of ['wheel', 'touchmove']) {
        const e = new win.Event(soort, { bubbles: true, cancelable: true });
        shimEl(doc).dispatchEvent(e);
        assert.waar(e.defaultPrevented, soort + ' is tegengehouden');
    }
});

test('een klik op de shim sluit de bovenste popup via het top-venster', () => {
    const { shim, doc, win } = nieuweShim();
    let gesloten = 0;
    win.lib_OpenWindowCenteredClose = () => { gesloten++; };
    venster(doc, '__lib_win1', 999000);
    shim.bijwerken(doc);
    shimEl(doc).dispatchEvent(new win.MouseEvent('click', { bubbles: true }));
    assert.gelijk(gesloten, 1, 'aantal keer gesloten');
});

test('de bewaking ruimt de shim op als het laatste venster buiten de sluitroute om verdwijnt', async () => {
    const { shim, doc } = nieuweShim();
    venster(doc, '__lib_win1', 999000);
    shim.bijwerken(doc);
    shim.bewaak(doc);
    doc.getElementById('__lib_win1').remove();
    await new Promise((klaar) => setTimeout(klaar, 20));
    assert.gelijk(shimEl(doc), null, 'shim');
});
