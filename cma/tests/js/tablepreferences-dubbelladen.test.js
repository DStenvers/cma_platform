/**
 * table-preferences.js twee keer laden in dezelfde pagina: geen "Identifier 'CmaTablePreferences' has already been declared".
 * De bundel kan in één pagina twee keer worden uitgevoerd; de klassen staan op het hoogste niveau van een gewoon script.
 *
 * Draait tegen de echte bron in cma/assets/js/table-preferences.js, in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'table-preferences.js'), 'utf8');

function laad(win, keer) {
    const fouten = [];
    win.addEventListener('error', (e) => fouten.push(e.message));
    for (let i = 0; i < keer; i++) {
        const s = win.document.createElement('script');
        s.textContent = BRON;
        win.document.body.appendChild(s);
    }
    return fouten;
}

suite('table-preferences.js: dubbel laden');

test('één keer laden zet de drie klassen op window', () => {
    const dom = new JSDOM('<!doctype html><body></body>', { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/' });
    const fouten = laad(dom.window, 1);
    assert.diepgelijk(fouten, []);
    assert.waar(typeof dom.window.CmaTablePreferences === 'function' && typeof dom.window.CmaTableColumnManager === 'function' && typeof dom.window.CmaInfiniteScroll === 'function', 'drie klassen');
});

test('twee keer laden geeft geen fout en houdt de eerste klassen', () => {
    const dom = new JSDOM('<!doctype html><body></body>', { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/' });
    laad(dom.window, 1);
    const eerste = dom.window.CmaTablePreferences;
    const fouten = laad(dom.window, 1);
    assert.diepgelijk(fouten, []);
    assert.waar(dom.window.CmaTablePreferences === eerste, 'dezelfde klasse blijft staan');
    const p = new dom.window.CmaTablePreferences(42);
    assert.gelijk(p.formId, 42, 'de klasse werkt nog');
});
