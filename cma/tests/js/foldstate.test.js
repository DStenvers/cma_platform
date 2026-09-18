/**
 * Bewijs voor "een verborgen sleepbalk deelt niets in".
 *
 * cma-fold onthoudt in localStorage waar hij stond en zet die maat bij het laden
 * als inline height plus flex:0 0 op zijn doel. Dat is precies goed zolang de
 * balk er is. Bij het toevoegen van een record is hij er niet: er is nog geen id,
 * dus het subformulier eronder is verborgen en de balk ook. De bewaarde maat toch
 * toepassen zet het detailformulier op een fractie van het paneel vast, met een
 * lege band eronder — zichtbaar als "het scherm neemt niet de hele hoogte".
 *
 * De bewaarde stand mag daarbij niet verloren gaan: zodra de balk er weer is,
 * hoort hij te staan waar de gebruiker hem liet — en dat "er weer is" ziet de
 * balk zelf, via een ResizeObserver op zichzelf. Verborgen is hij zolang er
 * geen record is: op de lijstpagina tot je in de boom een record kiest, op de
 * toevoegpagina tot het opslaan. Zodra hij verschijnt krijgt het doel alsnog
 * zijn maat. Zonder die maat is de subform-sectie zo hoog als de lijst in het
 * actieve tabblad, en die wisselt per tabblad.
 * jsdom heeft geen ResizeObserver; een stukje stub vangt de waarnemer op zodat
 * de test hem zelf kan laten afgaan.
 *
 * Cypress kan dit niet dekken zonder site; hier is één document genoeg.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const FOLD = path.join(__dirname, '..', '..', 'webcomponents', 'cma-fold.js');

/**
 * Pagina met een doel-element en een cma-fold erboven.
 *
 * @param {Object} opties
 * @param {boolean} opties.verborgen  balk op display:none zetten
 * @param {Object}  opties.bewaard    stand in localStorage (of niets)
 * @param {string}  opties.doelStijl  inline stijl waarmee het doel begint
 */
function maakPagina({ verborgen = false, bewaard = null, doelStijl = '' } = {}) {
    const opslag = bewaard
        ? `localStorage.setItem('cma_fold_form_foldH', ${JSON.stringify(JSON.stringify(bewaard))});`
        : '';

    const waarnemer = "window.ResizeObserver = class { constructor(cb) { this.cb = cb; this.doelen = []; window.__waarnemers = (window.__waarnemers || []).concat(this); } observe(el) { this.doelen.push(el); } disconnect() { this.doelen = []; } };";
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<script>' + opslag + waarnemer + '</script>' +
        '<div class="detail-content" style="' + doelStijl + '"></div>' +
        '<cma-fold orientation="horizontal" target=".detail-content" storage-key="form_foldH"' +
        (verborgen ? ' style="display:none"' : '') + '></cma-fold>' +
        '<script>' + fs.readFileSync(FOLD, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' }
    );

    const doc = dom.window.document;
    return {
        win: dom.window,
        balk: doc.querySelector('cma-fold'),
        waarnemers: () => dom.window.__waarnemers || [],
        doel: doc.querySelector('.detail-content'),
        opslag: () => dom.window.localStorage.getItem('cma_fold_form_foldH')
    };
}

suite('Sleepbalk: verborgen deelt niets in');

test('een zichtbare balk zet de bewaarde maat op zijn doel', () => {
    const p = maakPagina({ bewaard: { size: 300, collapsed: false } });
    assert.gelijk(p.doel.style.height, '300px');
    assert.gelijk(p.doel.style.flex, '0 0 300px');
});

test('een verborgen balk laat het doel met rust', () => {
    const p = maakPagina({ verborgen: true, bewaard: { size: 300, collapsed: false } });
    assert.gelijk(p.doel.style.height, '', 'geen hoogte opgelegd');
    assert.gelijk(p.doel.style.flex, '', 'geen flex opgelegd');
});

test('een verborgen balk ruimt een eerder opgelegde maat op', () => {
    const p = maakPagina({
        verborgen: true,
        bewaard: { size: 300, collapsed: false },
        doelStijl: 'height:300px;flex:0 0 300px'
    });
    assert.gelijk(p.doel.style.height, '', 'hoogte weggehaald');
    assert.gelijk(p.doel.style.flex, '', 'flex weggehaald');
});

test('de bewaarde stand blijft staan — verbergen is geen vergeten', () => {
    const p = maakPagina({ verborgen: true, bewaard: { size: 300, collapsed: false } });
    assert.waar(p.opslag() !== null, 'stand nog in localStorage');
    assert.gelijk(JSON.parse(p.opslag()).size, 300);
});

test('een ingeklapte, zichtbare balk klapt zijn doel wel in', () => {
    const p = maakPagina({ bewaard: { collapsed: true, savedSize: 300 } });
    assert.waar(p.doel.style.height !== '', 'doel kreeg de ingeklapte maat');
});

test('een verborgen balk wacht tot hij zichtbaar is en zet de maat dan alsnog', () => {
    const p = maakPagina({ verborgen: true, bewaard: { size: 300, collapsed: false } });
    const w = p.waarnemers();
    assert.gelijk(w.length, 1, 'één waarnemer op de balk');
    assert.gelijk(w[0].doelen[0], p.balk, 'die kijkt naar de balk zelf');

    w[0].cb([]); // afgegaan terwijl de balk nog verborgen is: niets doen
    assert.gelijk(p.doel.style.height, '', 'nog steeds geen hoogte');
    assert.gelijk(w[0].doelen.length, 1, 'blijft kijken');

    p.balk.style.display = ''; // het record is opgeslagen; de balk verschijnt
    w[0].cb([]);
    assert.gelijk(p.doel.style.height, '300px', 'bewaarde maat alsnog toegepast');
    assert.gelijk(p.doel.style.flex, '0 0 300px');
    assert.gelijk(w[0].doelen.length, 0, 'waarnemer losgelaten');
    assert.gelijk(p.waarnemers().length, 1, 'geen tweede waarnemer aangemaakt');
});

test('zonder bewaarde stand krijgt het doel bij verschijnen de startmaat', () => {
    const p = maakPagina({ verborgen: true });
    p.balk.setAttribute('default-size', '250');
    p.balk.style.display = '';
    p.waarnemers()[0].cb([]);
    assert.gelijk(p.doel.style.height, '250px');
});
