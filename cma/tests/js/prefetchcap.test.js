/**
 * Achtergrond-laden van een lijst stopt bij LIST_PREFETCH_MAX_ROWS.
 *
 * Na de eerste pagina haalt _autoPrefetchRows de rest op de achtergrond op.
 * Elke bijgeladen stap kost meer dan de vorige (de kolomfilters en de
 * rijtelling lopen over de hele tabel): op een lijst van 53.000 rijen was de
 * browser na 45 seconden 40 seconden geblokkeerd geweest, met 6.400 rijen en
 * 200.000 DOM-knopen, en het zou zes minuten zijn doorgegaan. Dus: tot de
 * ingestelde grens (window.CMA.settings.listPrefetchMaxRows, standaard 1000),
 * daarna laadt scrollen verder; en de kolomfilters worden tijdens het
 * achtergrond-laden niet per stap maar één keer aan het eind herbouwd.
 *
 * CmaInfiniteScroll komt uit de echte bron; _autoPrefetchRows en
 * prefetchMaxRows worden uit form-controller.js geknipt.
 *
 * Run: node tests/js/run.js prefetchcap
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const SCROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'table-preferences.js');
const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');

function methodeUitBron(src, kop) {
    const start = src.indexOf(kop);
    if (start === -1) { throw new Error(kop + ' niet gevonden in form-controller.js'); }
    let diepte = 0, i = src.indexOf('{', start);
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}' && --diepte === 0) break;
    }
    return src.slice(start, i + 1);
}

function maakPagina(settings) {
    const ctl = fs.readFileSync(CONTROLLER, 'utf8');
    const klasse = 'class CmaFormController {\n' +
        'constructor(scroller) { this.infiniteScroll = scroller; this.tellers = []; }\n' +
        'updateRecordCount(a, b) { this.tellers.push([a, b]); }\n' +
        methodeUitBron(ctl, '    _autoPrefetchRows() {') + '\n' +
        methodeUitBron(ctl, '    static prefetchMaxRows() {') + '\n}\nwindow.CmaFormController = CmaFormController;';
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<div id="container" style="height:100px;overflow:auto">' +
        '<table id="listTable"><tbody><tr data-id="1"><td>a</td></tr></tbody></table>' +
        '</div>' +
        '<script>' +
        'window.cmaLog = { log(){}, warn(){}, error(){} };' +
        'window.filterHerbouwd = 0;' +
        'window.jQuery = function(){ return { excelTableFilterRefresh(){ window.filterHerbouwd++; } }; };' +
        'window.jQuery.fn = { excelTableFilterRefresh(){} };' +
        'window.CMA = { utils: { setRecordCount(){}, formatRecordCount(){ return ""; } }, settings: ' + JSON.stringify(settings || {}) + ' };' +
        fs.readFileSync(SCROLLER, 'utf8') + '\n' + klasse +
        '</script></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' }
    );
    return dom.window;
}

/** Een lijst met 1 van `totaal` rijen; elke batch levert `perBatch` nieuwe rijen. */
function maakLijst(win, totaal, perBatch) {
    let volgendId = 2;
    const batches = [];
    const scroller = new win.CmaInfiniteScroll({
        container: win.document.getElementById('container'),
        table: win.document.getElementById('listTable'),
        formId: 'aanwezigheid',
        pageSize: perBatch,
        loadMore: async () => {
            let html = '';
            for (let i = 0; i < perBatch && volgendId <= totaal; i++, volgendId++) { html += '<tr data-id="' + volgendId + '"><td>r</td></tr>'; }
            batches.push(html.length);
            return { success: true, html: html, hasMore: volgendId <= totaal, lastId: volgendId - 1 };
        }
    });
    scroller.updateFromResponse({ hasMore: true, lastId: 1, count: 1, totalCount: totaal });
    const controller = new win.CmaFormController(scroller);
    return { scroller, controller, batches, rijen: () => win.document.querySelectorAll('#listTable tbody tr').length };
}

function wacht(ms) { return new Promise(r => setTimeout(r, ms)); }

suite('Achtergrond-laden: grens en filters');

test('zonder instelling geldt 1000', () => {
    const win = maakPagina({});
    assert.gelijk(win.CmaFormController.prefetchMaxRows(), 1000);
    const win2 = maakPagina({ listPrefetchMaxRows: 250 });
    assert.gelijk(win2.CmaFormController.prefetchMaxRows(), 250);
});

test('een kleine lijst wordt helemaal geladen en de teller verdwijnt', async () => {
    const win = maakPagina({ listPrefetchMaxRows: 1000 });
    const l = maakLijst(win, 11, 5);
    l.controller._autoPrefetchRows();
    await wacht(900);
    assert.gelijk(l.rijen(), 11, 'alle rijen staan er');
    assert.onwaar(l.scroller.hasMore, 'niets meer te halen');
    assert.onwaar(l.scroller.paused, 'scrollen weer vrij');
    assert.gelijk(l.controller.tellers.length, 1, 'de teller is één keer bijgewerkt (compleet)');
});

test('boven de grens stopt het achtergrond-laden en laadt scrollen verder', async () => {
    const win = maakPagina({ listPrefetchMaxRows: 10 });
    const l = maakLijst(win, 1000, 5);
    l.controller._autoPrefetchRows();
    await wacht(900);
    assert.gelijk(l.rijen(), 11, 'gestopt bij de eerste batch die de grens haalt');
    assert.waar(l.scroller.hasMore, 'er is nog meer, voor het scrollen');
    assert.onwaar(l.scroller.paused, 'scrollen is weer vrij');
    const batchesEerst = l.batches.length;
    await wacht(500);
    assert.gelijk(l.batches.length, batchesEerst, 'en er wordt niets meer op de achtergrond gehaald');

    await l.scroller.load(); // een scroll-stap
    assert.gelijk(l.rijen(), 16, 'scrollen laadt gewoon door');
});

test('de teller zegt "(laden...)" alleen zolang er echt geladen wordt', async () => {
    const win = maakPagina({ listPrefetchMaxRows: 10 });
    win.CMA.utils.formatRecordCount = (a, b, bezig) => 'records 1-' + a + ' van ' + b + (bezig ? ' (laden...)' : '');
    win.CMA.utils.setRecordCount = (t) => { win.teller = t; };
    const l = maakLijst(win, 1000, 5);
    // De teller vergelijkt scrollHeight met clientHeight; jsdom kent geen layout, dus meet ze zelf.
    Object.defineProperty(l.scroller.container, 'scrollHeight', { value: 500 });
    Object.defineProperty(l.scroller.container, 'clientHeight', { value: 100 });
    l.controller._autoPrefetchRows();
    await wacht(900);
    l.scroller.updateRecordCountDisplay();
    assert.gelijk(win.teller, 'records 1-11 van 1000', 'gestopt op de grens: er is meer, maar er wordt niets geladen');
});

test('grens 0: nooit op de achtergrond', async () => {
    const win = maakPagina({ listPrefetchMaxRows: 0 });
    const l = maakLijst(win, 50, 5);
    l.controller._autoPrefetchRows();
    await wacht(400);
    assert.gelijk(l.rijen(), 1);
    assert.gelijk(l.batches.length, 0);
    assert.waar(!l.scroller.paused, 'scrollen is nooit gepauzeerd');
});

test('de kolomfilters worden tijdens het achtergrond-laden één keer herbouwd, aan het eind', async () => {
    const win = maakPagina({ listPrefetchMaxRows: 1000 });
    const l = maakLijst(win, 16, 5);
    l.controller._autoPrefetchRows();
    await wacht(1100);
    assert.gelijk(l.rijen(), 16);
    assert.gelijk(win.filterHerbouwd, 1, 'niet per batch (dat waren er 3), maar één keer');

    // Een scroll-stap buiten het achtergrond-laden herbouwt wél meteen.
    const win2 = maakPagina({ listPrefetchMaxRows: 0 });
    const l2 = maakLijst(win2, 16, 5);
    await l2.scroller.load();
    assert.gelijk(win2.filterHerbouwd, 1);
});
