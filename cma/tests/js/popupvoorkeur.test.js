/**
 * Zijpaneel of venster: de keuze van de gebruiker geldt.
 *
 * De voorkeur staat in tblUsers (Voorkeuren) en gaat bij het opslaan mee naar het
 * cookie cma_popup_style. De JavaScript-kant las echter alleen localStorage, en die
 * kopie werd uitsluitend bijgewerkt op het moment dat je de voorkeurenpagina opende.
 * Wie zijn keuze op een andere machine had gezet — of ooit "popup" koos en later
 * "zijpaneel" — hield in deze browser de oude waarde. Gemeld als: een regel in de
 * tabelweergave opent een venster terwijl de instelling zijpaneel zegt.
 *
 * Hier vastgelegd, op de uitgeleverde bron:
 *   1. het cookie wint van localStorage;
 *   2. zonder cookie blijft localStorage gelden;
 *   3. zonder allebei is het zijpaneel de standaard;
 *   4. rommel in het cookie telt niet mee;
 *   5. opslaan schrijft naar allebei, zodat de volgende paginalading klopt;
 *   6. de extra-knoppen op een formulier openen via lib_OpenPanel, niet rechtstreeks
 *      als venster — anders negeren juist die knoppen de voorkeur.
 *
 * Run: node tests/js/run.js popupvoorkeur
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBRARY = path.join(__dirname, '..', '..', '..', 'library', 'library.js');
const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const INLINE = path.join(__dirname, '..', '..', 'assets', 'js', 'inline-edit.js');

/** De twee voorkeursfuncties uit de echte library.js, zonder de rest te draaien. */
function laadVoorkeurFuncties(dom) {
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const stukken = ['lib_getPopupStylePreference', 'lib_leesCookie', 'lib_setPopupStylePreference']
        .map((naam) => {
            const start = src.indexOf('function ' + naam + '(');
            if (start === -1) { throw new Error(naam + ' niet gevonden in library.js'); }
            // Tot de sluitende accolade in kolom 0: zo staan de functies in dit bestand.
            const eind = src.indexOf('\n}', start);
            return src.slice(start, eind + 2);
        });
    // new Function met document/localStorage erin: zo draait de uitgeleverde code
    // zonder dat jsdom scripts hoeft uit te voeren.
    const maak = new Function('document', 'localStorage', stukken.join('\n\n')
        + '\nreturn { lees: lib_getPopupStylePreference, zet: lib_setPopupStylePreference };');
    const api = maak(dom.window.document, dom.window.localStorage);
    dom.window.lib_getPopupStylePreference = api.lees;
    dom.window.lib_setPopupStylePreference = api.zet;
    return dom.window;
}

function nieuwVenster(cookie, opslag) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://test.example/cma/' });
    if (cookie) { dom.window.document.cookie = cookie; }
    const store = {};
    Object.defineProperty(dom.window, 'localStorage', {
        value: {
            getItem: (k) => (k in store ? store[k] : null),
            setItem: (k, v) => { store[k] = String(v); },
        },
        configurable: true,
    });
    if (opslag) { dom.window.localStorage.setItem('cma_popup_style', opslag); }
    return { win: laadVoorkeurFuncties(dom), store };
}

suite('popupvoorkeur');

test('het cookie wint van een oude waarde in localStorage', () => {
    // Zijpaneel opgeslagen, popup nog in de browserkopie: de opgeslagen keuze geldt.
    assert.gelijk(nieuwVenster('cma_popup_style=sidepanel', 'popup').win.lib_getPopupStylePreference(), 'sidepanel');
    assert.gelijk(nieuwVenster('cma_popup_style=popup', 'sidepanel').win.lib_getPopupStylePreference(), 'popup');
});

test('zonder cookie blijft localStorage gelden', () => {
    assert.gelijk(nieuwVenster('', 'popup').win.lib_getPopupStylePreference(), 'popup');
});

test('zonder voorkeur is het zijpaneel de standaard', () => {
    assert.gelijk(nieuwVenster('', '').win.lib_getPopupStylePreference(), 'sidepanel');
});

test('een onbekende waarde in het cookie telt niet mee', () => {
    assert.gelijk(nieuwVenster('cma_popup_style=onzin', 'popup').win.lib_getPopupStylePreference(), 'popup');
});

test('opslaan schrijft naar het cookie én naar localStorage', () => {
    const proef = nieuwVenster('', '');
    proef.win.lib_setPopupStylePreference('popup');
    assert.gelijk(proef.store['cma_popup_style'], 'popup', 'localStorage');
    assert.waar(/cma_popup_style=popup/.test(proef.win.document.cookie), 'het cookie is gezet');
    assert.gelijk(proef.win.lib_getPopupStylePreference(), 'popup', 'meteen teruggelezen');
});

test('de extra-knoppen laten de keuze aan lib_OpenPanel', () => {
    const controller = fs.readFileSync(CONTROLLER, 'utf8');
    const inline = fs.readFileSync(INLINE, 'utf8');
    assert.waar(/lib_OpenPanel\(url, 'extra_action'/.test(controller), 'form-controller gebruikt lib_OpenPanel');
    assert.onwaar(/lib_OpenWindowCentered\(url, 'extra_action'/.test(controller), 'form-controller opent geen venster meer');
    assert.waar(/lib_OpenPanel\(url, 'extra_action_'/.test(inline), 'inline-edit gebruikt lib_OpenPanel');
    assert.onwaar(/lib_OpenWindowCentered\(url, 'extra_action_'/.test(inline), 'inline-edit opent geen venster meer');
});
