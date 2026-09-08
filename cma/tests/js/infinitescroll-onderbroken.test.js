/**
 * Een onderbroken lijst is geen kapotte lijst.
 *
 * load() van CmaInfiniteScroll gooide een Error als de paginering stopte vóór
 * het bekende totaal. Gemeld: "[Infinite Scroll] Pagination stopped at
 * 2144/2304 — 160 record(s) not loaded (last id 2392, form logins)" — en dat
 * is geen fout: de teller toont eerlijk "1-2144 van 2304" en de lijst is
 * bruikbaar. Een stop onder het totaal is daarom een waarschuwing in de
 * console (cmaLog.warn), geen Error die het foutpaneel en het serverrapport
 * haalt. Twee onderbrekingen krijgen zelfs geen waarschuwing, want dan zegt de
 * tellerstand niets:
 *   (a) de pagina wordt verlaten (pagehide / beforeunload);
 *   (b) de tabel van deze scroller staat niet meer in de DOM (lijst opnieuw
 *       opgebouwd) — de scroller trekt zich stil terug.
 *
 * Run: node tests/js/run.js infinitescroll
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'table-preferences.js');

function maakPagina() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<div id="container" style="height:100px;overflow:auto">' +
        '<table id="listTable"><tbody><tr data-id="1"><td>a</td></tr></tbody></table>' +
        '</div>' +
        '<script>' +
        'window.cmaLog = { log(){}, warn(m){ window.gewaarschuwd.push(String(m)); }, error(){} };' +
        'window.gewaarschuwd = [];' +
        'window.CMA = { utils: { setRecordCount(){}, formatRecordCount(){ return ""; } } };' +
        fs.readFileSync(BRON, 'utf8') +
        '</script></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' }
    );
    return dom.window;
}

/** Een scroller die 1 van 10 records heeft en waarvan de volgende batch faalt. */
function maakScroller(win, loadMore) {
    const scroller = new win.CmaInfiniteScroll({
        container: win.document.getElementById('container'),
        table: win.document.getElementById('listTable'),
        formId: 'logins',
        pageSize: 5,
        loadMore: loadMore
    });
    scroller.updateFromResponse({ hasMore: true, lastId: 1, count: 1, totalCount: 10 });
    return scroller;
}

/** Laat load() lopen tot de lus opgeeft; geeft de gegooide fout terug, of null. */
async function laadTotStop(scroller) {
    for (let i = 0; i < 10 && scroller.hasMore && !scroller.destroyed; i++) {
        try { await scroller.load(); } catch (e) { return e; }
        scroller.pendingLastId = null; // zoals de prefetch-lus na een mislukte batch
    }
    return null;
}

suite('Infinite scroll: onderbroken laden meldt geen bug');

test('een stop onder het totaal is een waarschuwing, geen fout', async () => {
    const win = maakPagina();
    const scroller = maakScroller(win, async () => ({ success: false }));
    const fout = await laadTotStop(scroller);
    assert.gelijk(fout, null, 'load() gooit niet meer bij een stop onder het totaal');
    assert.onwaar(scroller.hasMore, 'de lus is wel gestopt');
    const melding = win.gewaarschuwd.find(m => /Pagination stopped/.test(m)) || '';
    assert.waar(/Pagination stopped at 1\/10 — 9 record\(s\) not loaded/.test(melding),
        'de console krijgt de tellerstand als waarschuwing: ' + JSON.stringify(win.gewaarschuwd));
});

test('een stop op het totaal waarschuwt niet', async () => {
    const win = maakPagina();
    const scroller = maakScroller(win, async () => ({ success: false }));
    scroller.updateFromResponse({ hasMore: true, lastId: 1, count: 1, totalCount: 1 });
    scroller.currentCount = 1;
    await laadTotStop(scroller);
    assert.onwaar(win.gewaarschuwd.some(m => /Pagination stopped/.test(m)),
        'compleet geladen is niets om over te waarschuwen');
});

test('de pagina wordt verlaten: de afgebroken fetch is geen paginerings-bug', async () => {
    const win = maakPagina();
    const scroller = maakScroller(win, async () => {
        // De klik navigeert weg terwijl deze batch onderweg is.
        win.dispatchEvent(new win.Event('pagehide'));
        return { success: false, retriable: true };
    });
    const fout = await laadTotStop(scroller);
    assert.gelijk(fout, null, 'geen melding bij het verlaten van de pagina');
    assert.onwaar(scroller.hasMore, 'de lus is wel gestopt');
    assert.onwaar(win.gewaarschuwd.some(m => /Pagination stopped/.test(m)), 'ook geen waarschuwing');
});

test('de lijst is opnieuw opgebouwd: de oude scroller trekt zich stil terug', async () => {
    const win = maakPagina();
    const scroller = maakScroller(win, async () => {
        // Een klik bouwt de lijst opnieuw op: onze tabel verdwijnt uit de DOM.
        win.document.getElementById('listTable').remove();
        return { success: false, retriable: true };
    });
    const fout = await laadTotStop(scroller);
    assert.gelijk(fout, null, 'geen melding voor een tabel die er niet meer is');
    assert.waar(scroller.destroyed, 'de scroller is netjes afgevoerd');
    assert.onwaar(win.gewaarschuwd.some(m => /Pagination stopped/.test(m)), 'ook geen waarschuwing');
});

test('de vlag wordt maar één keer aan het venster gehangen', () => {
    const win = maakPagina();
    let geteld = 0;
    const orig = win.addEventListener.bind(win);
    win.addEventListener = (naam, fn) => { if (naam === 'pagehide') geteld++; return orig(naam, fn); };
    maakScroller(win, async () => ({ success: false }));
    maakScroller(win, async () => ({ success: false }));
    assert.gelijk(geteld, 1, 'de eerste scroller registreert, de tweede niet nog eens');
});
