/**
 * Een onderbroken lijst is geen kapotte lijst.
 *
 * load() van CmaInfiniteScroll gooit met opzet een fout als de paginering stopt
 * vóór het bekende totaal: een lijst die stil bij 1500 van 1827 blijft staan is
 * een bug die je wilt zien. Maar gemeld werd precies die fout ná een klik:
 * "[Infinite Scroll] Pagination stopped at 601/2304 — 1703 record(s) not
 * loaded". De klik navigeerde de lijst weg; de fetch die daardoor afbrak werd
 * een retriable failure, de lus gaf na een paar keer op, hasMore ging op false
 * en de melding volgde. Twee onderbrekingen mogen dus niet als bug gelden:
 *   (a) de pagina wordt verlaten (pagehide / beforeunload);
 *   (b) de tabel van deze scroller staat niet meer in de DOM (lijst opnieuw
 *       opgebouwd) — het succes-pad trok zich daar al stil op terug, de
 *       faalpaden niet.
 * En de echte stop — tabel aanwezig, pagina in leven — blijft luid.
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
        'window.cmaLog = { log(){}, warn(){}, error(){} };' +
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

test('een echte stop blijft luid: tabel staat er, pagina leeft, batch mislukt', async () => {
    const win = maakPagina();
    const scroller = maakScroller(win, async () => ({ success: false }));
    const fout = await laadTotStop(scroller);
    assert.waar(fout !== null, 'zonder onderbreking hoort load() te gooien');
    assert.waar(/Pagination stopped at 1\/10/.test(fout.message), 'met de tellerstand erin: ' + fout.message);
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
