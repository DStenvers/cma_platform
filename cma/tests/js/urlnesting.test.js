/**
 * Bewijs dat een geopend zijpaneel zijn plaats in de URL kent.
 *
 * De URL is het adres van wat er op het scherm staat: het bovenliggende record
 * hoort er te blijven staan als je er een subformulier-record uit opent, zodat
 * verversen hetzelfde scherm teruggeeft en het sluiten van een paneel niets
 * anders is dan de laatste twee segmenten eraf halen.
 *
 * Waar het misging: het niveau werd afgeleid uit de hoogte van de panelenstapel.
 * Die twee lopen uiteen zodra het bovenliggende record niet zelf in een paneel
 * staat. Vanaf /form/deelnemers/837 is een deelname het EERSTE paneel, terwijl
 * hij één niveau lager hoort — en de URL werd /form/deelnemers_neemt_deel_aan/1108,
 * de ouder kwijt. Het niveau volgt daarom de ouderketen (parentID).
 *
 * Deze test draait de echte url-manager en de echte lib_sidepanel_syncUrl.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const URL_MANAGER = path.join(__dirname, '..', '..', 'assets', 'js', 'url-manager.js');
const LIBRARY = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

/** Knip lib_sidepanel_syncUrl uit de echte bron. */
function haalSyncUitBron() {
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const start = src.indexOf('function lib_sidepanel_syncUrl(');
    if (start === -1) throw new Error('lib_sidepanel_syncUrl niet gevonden in library.js');
    let i = src.indexOf('{', start);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}') {
            diepte--;
            if (diepte === 0) return src.slice(start, i + 1);
        }
    }
    throw new Error('Einde van lib_sidepanel_syncUrl niet gevonden');
}

/**
 * Venster dat op `pad` staat, met een panelenstapel waarvan elk paneel een
 * iframe heeft met de opgegeven zoekstring.
 *
 * @param {string} pad          begin-URL, bijv. '/cma/form/deelnemers/837'
 * @param {string[]} panelen    zoekstrings, diepste laatst
 */
function maakVenster(pad, panelen) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost' + pad });
    const win = dom.window;
    const doc = win.document;

    const script = doc.createElement('script');
    script.textContent = fs.readFileSync(URL_MANAGER, 'utf8') + '\n' + haalSyncUitBron() +
        '\nwindow.__sync = lib_sidepanel_syncUrl;';
    doc.body.appendChild(script);

    win.lib_sidepanel_stack = panelen.map(zoek => {
        const panel = doc.createElement('div');
        const iframe = doc.createElement('iframe');
        iframe.src = '/cma/form.php?' + zoek;
        panel.appendChild(iframe);
        doc.body.appendChild(panel);
        return { panel: panel };
    });

    return { win, sync: () => win.__sync(win), pad: () => win.location.pathname };
}

suite('URL-nesting bij het openen van een zijpaneel');

test('een subform-record hangt onder zijn ouder, ook als het het eerste paneel is', () => {
    const v = maakVenster('/cma/form/deelnemers/837',
        ['form=deelnemers_neemt_deel_aan&id=1108&parentID=837&parentField=fkDeelnemer']);
    v.sync();
    assert.gelijk(v.pad(), '/cma/form/deelnemers/837/deelnemers_neemt_deel_aan/1108');
});

test('een nieuw subform-record krijgt /new op zijn eigen plaats', () => {
    const v = maakVenster('/cma/form/deelnemers/837',
        ['form=deelnemers_neemt_deel_aan&parentID=837&parentField=fkDeelnemer']);
    v.sync();
    assert.gelijk(v.pad(), '/cma/form/deelnemers/837/deelnemers_neemt_deel_aan/new');
});

test('een derde niveau hangt onder het subform-record', () => {
    const v = maakVenster('/cma/form/deelnemers/837/deelnemers_neemt_deel_aan/1108', [
        'form=deelnemers_neemt_deel_aan&id=1108&parentID=837&parentField=fkDeelnemer',
        'form=aanwezigheid&id=132132&parentID=1108&parentField=fkDeelname'
    ]);
    v.sync();
    assert.gelijk(v.pad(),
        '/cma/form/deelnemers/837/deelnemers_neemt_deel_aan/1108/aanwezigheid/132132');
});

test('een record zonder ouder blijft een adres op zichzelf', () => {
    const v = maakVenster('/cma/form/deelnemers', ['form=deelnemers&id=837']);
    v.sync();
    assert.gelijk(v.pad(), '/cma/form/deelnemers/837');
});

test('een vierde niveau laat de URL met rust — liever geen adres dan een onleesbaar adres', () => {
    const begin = '/cma/form/deelnemers/837/deelnemers_neemt_deel_aan/1108/aanwezigheid/132132';
    const v = maakVenster(begin, [
        'form=deelnemers_neemt_deel_aan&id=1108&parentID=837',
        'form=aanwezigheid&id=132132&parentID=1108',
        'form=aanwezigheid_details&id=7&parentID=132132'
    ]);
    v.sync();
    assert.gelijk(v.pad(), begin);
});

test('een paneel zonder iframe schrijft niets', () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form/deelnemers/837' });
    const doc = dom.window.document;
    const script = doc.createElement('script');
    script.textContent = fs.readFileSync(URL_MANAGER, 'utf8') + '\n' + haalSyncUitBron() +
        '\nwindow.__sync = lib_sidepanel_syncUrl;';
    doc.body.appendChild(script);
    const leeg = doc.createElement('div');
    doc.body.appendChild(leeg);
    dom.window.lib_sidepanel_stack = [{ panel: leeg }];

    dom.window.__sync(dom.window);
    assert.gelijk(dom.window.location.pathname, '/cma/form/deelnemers/837');
});
