/**
 * De z-index-stapel loopt niet meer op na een opruiming buiten de boekhouding om.
 *
 * lib_zindex_manager houdt per overlay een regel bij en deelt z-indexen uit als
 * baseZIndex + (aantal * 10). Vensters en zijpanelen staan óók als element in de DOM,
 * met precies het id dat in die stapel staat — twee waarheden dus.
 *
 * Ze liepen uiteen op één plek: de foutafhandeling van lib_screen_fade haalt met
 * $("#__lib_win"+i).remove() alle vensters weg zonder pop() aan te roepen. Daarna telde
 * de stapel vensters die niet meer bestonden, en klom de z-index van het volgende
 * venster door.
 *
 * De stapel schoont zichzelf nu op aan de hand van de DOM. Deze test legt dat vast, en
 * bewaakt dat een dialoog (met een verzonnen id dat niet in de DOM voorkomt) daar NIET
 * door wordt weggegooid.
 *
 * Run: node tests/js/run.js zindexstapel
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBRARY = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

/** De manager uit de echte bron, in een eigen document. */
function nieuweManager() {
    const dom = new JSDOM('<!doctype html><html><body></body></html>');
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const start = src.indexOf('var lib_zindex_manager = (function() {');
    if (start === -1) { throw new Error('lib_zindex_manager niet gevonden'); }
    // Tot en met de afsluitende regel van de IIFE.
    const eind = src.indexOf('\n})();', start);
    const body = src.slice(start, eind + 6).replace('var lib_zindex_manager =', 'return');
    const maak = new Function('document', 'self', 'top', body);
    return { manager: maak(dom.window.document, {}, {}), doc: dom.window.document };
}

function voegVensterToe(doc, id) {
    const el = doc.createElement('div');
    el.id = id;
    doc.body.appendChild(el);
    return el;
}

suite('zindexstapel');

test('een tweede venster komt tien hoger', () => {
    const { manager, doc } = nieuweManager();
    const een = manager.push('__lib_win1', 'window'); voegVensterToe(doc, '__lib_win1');
    const twee = manager.push('__lib_win2', 'window'); voegVensterToe(doc, '__lib_win2');
    assert.gelijk(twee - een, 10, 'afstand tussen twee vensters');
    assert.gelijk(manager.count(), 2, 'aantal in de stapel');
});

test('een venster dat buiten pop() om uit de DOM verdwijnt, telt niet meer mee', () => {
    const { manager, doc } = nieuweManager();
    const eerste = manager.push('__lib_win1', 'window'); voegVensterToe(doc, '__lib_win1');
    // Precies wat de foutafhandeling doet: element weg, boekhouding onaangeroerd.
    doc.getElementById('__lib_win1').remove();
    assert.gelijk(manager.count(), 0, 'de stapel is meegegaan met de DOM');
    const volgende = manager.push('__lib_win2', 'window'); voegVensterToe(doc, '__lib_win2');
    assert.gelijk(volgende, eerste, 'het volgende venster begint weer onderaan');
});

test('een zijpaneel wordt net zo nagekeken', () => {
    const { manager, doc } = nieuweManager();
    manager.push('__lib_sidepanel1', 'sidepanel'); voegVensterToe(doc, '__lib_sidepanel1');
    assert.gelijk(manager.count(), 1, 'paneel geteld');
    doc.getElementById('__lib_sidepanel1').remove();
    assert.gelijk(manager.count(), 0, 'paneel meegegaan met de DOM');
});

test('een dialoog blijft staan, want zijn id staat niet in de DOM', () => {
    const { manager } = nieuweManager();
    manager.push('lib-dialog-123', 'dialog');
    manager.push('datepicker_456', 'datepicker');
    assert.gelijk(manager.count(), 2, 'verzonnen ids worden niet opgeruimd');
});

test('vensters en dialogen door elkaar: alleen het verdwenen venster valt af', () => {
    const { manager, doc } = nieuweManager();
    manager.push('__lib_win1', 'window'); voegVensterToe(doc, '__lib_win1');
    manager.push('lib-dialog-9', 'dialog');
    doc.getElementById('__lib_win1').remove();
    assert.gelijk(manager.count(), 1, 'de dialoog blijft');
});

/* --------------------------------------------------------------------------
 * En dezelfde vraag voor de panelenstapel: die beschrijft ook iets wat in de
 * DOM staat, en werd op drie plekken gelezen om een besluit op te nemen.
 * ------------------------------------------------------------------------ */

function laadStapelHelper() {
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const start = src.indexOf('function lib_sidepanel_stapel(topWindow) {');
    if (start === -1) { throw new Error('lib_sidepanel_stapel niet gevonden'); }
    const eind = src.indexOf('\n}', start);
    return new Function(src.slice(start, eind + 2) + '\nreturn lib_sidepanel_stapel;')();
}

suite('panelenstapel');

test('een paneel dat nog op het scherm staat, blijft in de stapel', () => {
    const stapel = laadStapelHelper();
    const dom = new JSDOM('<!doctype html><html><body><div id="__lib_sidepanel1"></div></body></html>');
    const win = { document: dom.window.document, lib_sidepanel_stack: [{ id: '__lib_sidepanel1', url: '/a' }] };
    assert.gelijk(stapel(win).length, 1);
});

test('een paneel dat uit de DOM is verdwenen, telt niet meer mee', () => {
    const stapel = laadStapelHelper();
    const dom = new JSDOM('<!doctype html><html><body></body></html>');
    const win = { document: dom.window.document, lib_sidepanel_stack: [{ id: '__lib_sidepanel1', url: '/a' }] };
    assert.gelijk(stapel(win).length, 0, 'de stapel gaat mee met het scherm');
    assert.gelijk(win.lib_sidepanel_stack.length, 0, 'en de echte stapel is bijgewerkt');
});

test('van twee panelen valt alleen het verdwenen exemplaar af', () => {
    const stapel = laadStapelHelper();
    const dom = new JSDOM('<!doctype html><html><body><div id="__lib_sidepanel2"></div></body></html>');
    const win = { document: dom.window.document, lib_sidepanel_stack: [
        { id: '__lib_sidepanel1', url: '/a' }, { id: '__lib_sidepanel2', url: '/b' }] };
    const over = stapel(win);
    assert.gelijk(over.length, 1);
    assert.gelijk(over[0].url, '/b');
});

test('zonder stapel of zonder document gaat er niets stuk', () => {
    const stapel = laadStapelHelper();
    assert.gelijk(stapel({}).length, 0, 'geen stapel');
    assert.gelijk(stapel({ lib_sidepanel_stack: [{ id: 'x' }] }).length, 1, 'geen document: laten staan');
});
