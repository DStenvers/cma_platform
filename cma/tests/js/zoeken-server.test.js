/**
 * Zoeken in de lijst: na het typen ook op de server zoeken (zonder Enter), en een filter dat een herrender overleeft.
 *
 * Gemeld: "als er wat in de zoekbalk wordt ingevoerd moet er op Enter gedrukt worden voordat er resultaten zijn" en
 * "als je dan iemand kiest verschijnt weer de hele lijst, maar het filtercriterium blijft staan".
 *
 * De methoden worden uit de echte form-controller.js geknipt en tegen een stub-controller gedraaid; cma-tree.js
 * wordt in zijn geheel geladen. Wat hier slaagt, slaagt dus in de code die wordt uitgeleverd.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const CONTROLLER = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js'), 'utf8');
const TREE = fs.readFileSync(path.join(__dirname, '..', '..', 'webcomponents', 'cma-tree.js'), 'utf8');

/** De broncode van één methode, van "naam(" tot de sluitende accolade. */
function methode(naam) {
    const m = new RegExp('\\n    (async )?' + naam + '\\([^)]*\\) \\{').exec(CONTROLLER);
    if (!m) throw new Error('methode ' + naam + ' niet gevonden in form-controller.js');
    let i = m.index + m[0].length, diepte = 1;
    while (diepte > 0 && i < CONTROLLER.length) {
        const c = CONTROLLER[i++];
        if (c === '{') diepte++; else if (c === '}') diepte--;
    }
    return CONTROLLER.slice(m.index + 1, i);
}

function maakController(win, opties) {
    const doc = win.document;
    const ctrl = {
        searchTerm: '', currentPage: 3, _lastListTerm: '', _tableMode: !!opties.tabel,
        isTableMode() { return this._tableMode; },
        aanroepen: [],
        async loadList(a, b, c) { this.aanroepen.push({ a, b, c, term: this.searchTerm }); this._lastListTerm = this.searchTerm || ''; },
        _doSearchAsYouType() {},
    };
    const bron = ['searchAsYouType', '_scheduleServerSearch', '_doServerSearch', 'updateSearchInputState'].map(methode).join(',\n');
    const klasse = new win.Function('return ({' + bron + '})')();
    Object.keys(klasse).forEach((k) => { ctrl[k] = klasse[k]; });
    // cmaLog bestaat in de pagina; hier een stille stand-in.
    win.cmaLog = { log() {}, warn() {}, error() {} };
    return ctrl;
}

function pagina(waarde) {
    const dom = new JSDOM('<!doctype html><html><body><div id="searchfor"><input id="binnen" value="' + (waarde || '') + '"></div></body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true });
    const win = dom.window;
    const veld = win.document.getElementById('searchfor');
    // lib-search-input heeft een value-property; hier een stand-in die de inner input volgt.
    Object.defineProperty(veld, 'value', {
        get() { return win.document.getElementById('binnen').value; },
        set(v) { win.document.getElementById('binnen').value = v; },
    });
    return { win, veld, binnen: win.document.getElementById('binnen') };
}
const wacht = (ms) => new Promise((r) => setTimeout(r, ms));

suite('Zoeken: server-zoekactie na het typen');

test('twee tekens: na 300 ms zoekt de server, zonder record te openen; eerder niet', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: true });
    veld.value = 'ab';
    c.searchAsYouType('ab');
    await wacht(150);
    assert.gelijk(c.aanroepen.length, 0, 'binnen 300 ms nog geen serveraanroep');
    await wacht(400);
    assert.gelijk(c.aanroepen.length, 1, 'na het debounce één aanroep');
    assert.gelijk(c.aanroepen[0].term, 'ab');
    assert.gelijk(c.aanroepen[0].b, true, 'geopend record blijft staan (skipRecordLoad)');
    assert.gelijk(c.aanroepen[0].c, true, 'geen automatisch openen bij één treffer (skipAutoSelect)');
    assert.gelijk(c.currentPage, 1, 'terug naar pagina 1');
});

test('doortypen binnen 300 ms geeft één aanroep, met de laatste term', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: true });
    for (const t of ['ab', 'abc', 'abcd']) { veld.value = t; c.searchAsYouType(t); await wacht(100); }
    await wacht(500);
    assert.gelijk(c.aanroepen.length, 1);
    assert.gelijk(c.aanroepen[0].term, 'abcd');
});

test('één teken blijft client-side', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: true });
    veld.value = 'a';
    c.searchAsYouType('a');
    await wacht(500);
    assert.gelijk(c.aanroepen.length, 0);
});

test('het veld leegmaken haalt de volledige lijst terug', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: true });
    c._lastListTerm = 'ab';
    veld.value = '';
    c.searchAsYouType('');
    await wacht(500);
    assert.gelijk(c.aanroepen.length, 1);
    assert.gelijk(c.aanroepen[0].term, '', 'zoekterm leeg');
});

test('dezelfde term als waarop de lijst al staat (Enter, eerdere zoekactie) laadt niet nog eens', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: true });
    c._lastListTerm = 'ab';
    veld.value = 'ab';
    c.searchAsYouType('ab');
    await wacht(500);
    assert.gelijk(c.aanroepen.length, 0);
});

test('boomweergave filtert alleen client-side (een zoekterm geeft de server een platte lijst)', async () => {
    const { win, veld } = pagina();
    const c = maakController(win, { tabel: false });
    veld.value = 'abc';
    c.searchAsYouType('abc');
    await wacht(500);
    assert.gelijk(c.aanroepen.length, 0);
});

test('een serverantwoord overschrijft niet wat de gebruiker op dat moment typt', () => {
    const { win, veld, binnen } = pagina('abc');
    const c = maakController(win, { tabel: true });
    c.searchTerm = 'ab';
    binnen.focus();
    c.updateSearchInputState(5);
    assert.gelijk(veld.value, 'abc', 'veld met focus blijft staan');
    binnen.blur();
    c.updateSearchInputState(5);
    assert.gelijk(veld.value, 'ab', 'zonder focus wordt de term teruggezet (bijvoorbeeld bij wisselen van weergave)');
});

suite('Zoeken: het filter van de boom overleeft een herrender');

function maakBoom() {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'dangerously', pretendToBeVisual: true });
    const win = dom.window;
    // cma-tree vraagt zijn iconenstijlen aan het globale CMA-object (shared-icons.js); hier een lege stand-in.
    win.CMA = { getIconStylesFor() { return ''; } };
    win.cmaLog = { log() {}, warn() {}, error() {} };
    win.Element.prototype.scrollIntoView = function () {};   // jsdom kent scrollIntoView niet
    const s = win.document.createElement('script');
    s.textContent = TREE;
    win.document.body.appendChild(s);
    const boom = win.document.createElement('cma-tree');
    win.document.body.appendChild(boom);
    const knoop = (id, label) => ({ type: 'item', id: String(id), label, href: 'javascript:void(0)' });
    boom.setData([{ type: 'folder', label: 'Alles', children: [knoop(1, 'Anna Jansen'), knoop(2, 'Bram de Vries'), knoop(3, 'Annet Smit')] }]);
    return { win, boom };
}
const zichtbaar = (boom) => [...boom.shadowRoot.querySelectorAll('a[data-label]')]
    .filter((a) => { const li = a.closest('li'); return li && li.style.display !== 'none'; })
    .map((a) => a.getAttribute('data-label'));

test('een record kiezen (selectById) toont niet weer de hele lijst', () => {
    const { boom } = maakBoom();
    boom.filter('ann');
    assert.gelijk(zichtbaar(boom).join('|'), 'Anna Jansen|Annet Smit', 'gefilterd');
    boom.selectById('1');
    assert.gelijk(zichtbaar(boom).join('|'), 'Anna Jansen|Annet Smit', 'na het kiezen nog steeds gefilterd');
});

test('nieuwe data (setData) behoudt het filter; een leeg filter toont alles', () => {
    const { boom } = maakBoom();
    boom.filter('bram');
    boom.setData([{ type: 'folder', label: 'Alles', children: [
        { type: 'item', id: '1', label: 'Anna Jansen', href: 'x' }, { type: 'item', id: '2', label: 'Bram de Vries', href: 'x' } ] }]);
    assert.gelijk(zichtbaar(boom).join('|'), 'Bram de Vries');
    boom.filter('');
    boom.setData([{ type: 'folder', label: 'Alles', children: [
        { type: 'item', id: '1', label: 'Anna Jansen', href: 'x' }, { type: 'item', id: '2', label: 'Bram de Vries', href: 'x' } ] }]);
    assert.gelijk(zichtbaar(boom).length, 2, 'zonder filter alles');
});
