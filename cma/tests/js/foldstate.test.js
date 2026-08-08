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
 * hoort hij te staan waar de gebruiker hem liet.
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

    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<script>' + opslag + '</script>' +
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
