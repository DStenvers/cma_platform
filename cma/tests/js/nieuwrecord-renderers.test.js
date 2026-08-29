/**
 * Een nieuw record mag geen molentjes achterlaten.
 *
 * Velden met een eigen renderer krijgen in het sjabloon alleen een plaatshouder
 * ("Laden..."), want bij het renderen is er nog geen record-ID. Wie die plaatshouder
 * invult, is JavaScript: loadCustomRenderers().
 *
 * Gemeld op /cma/form/groups/new: drie draaiende molentjes en verder niets, terwijl een
 * bestaand record wél goed ging. Dat formulier heeft drie van die velden —
 * group_menu_rights, group_report_rights en group_members. De oorzaak zat in de
 * init-tak voor een server-gerenderd nieuw record: die deed wél het uitklappen van
 * verplichte velden, maar riep loadCustomRenderers() niet aan. Via newRecord() (de knop
 * "Toevoegen" in een al geladen formulier) gebeurde dat wel, en via loadRecord() ook —
 * vandaar dat alleen de directe URL stuk was.
 *
 * De functie wordt NIET nagebouwd: dit bestand knipt hem uit de echte
 * form-controller.js en voert hem uit in een jsdom-document. Wat hier slaagt, slaagt dus
 * in de code die wordt uitgeleverd.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const bron = fs.readFileSync(BRON, 'utf8');

/** Knip loadCustomRenderers() uit de echte bron en maak er een losse functie van. */
function haalLaderUitBron() {
    const start = bron.indexOf('    async loadCustomRenderers(recordId) {');
    const eind = bron.indexOf('    initRightsMatrix(container) {', start);
    if (start === -1 || eind === -1) {
        throw new Error('Kon loadCustomRenderers() niet in form-controller.js vinden');
    }
    // Van methode naar losse functie; de twee dingen die hij van de controller gebruikt
    // (initRightsMatrix en getFormParam) worden in de test gestubd.
    return bron.slice(start, eind)
        .replace('async loadCustomRenderers(recordId) {', 'async function loadCustomRenderers(recordId) {')
        .replace(/this\.initRightsMatrix\(/g, 'stubInitRightsMatrix(')
        .replace(/this\.getFormParam\(\)/g, 'stubFormParam()');
}

/** Een document met de drie plaatshouders van het groups-formulier. */
function maakPagina(antwoord) {
    const dom = new JSDOM(`<!doctype html><html><body class="is-creating mode-detail">
        <div class="form-layout">
            <div class="custom-renderer" data-renderer="rights_matrix" data-field="group_menu_rights">
                <div class="loading-placeholder"><lib-loader size="small" delay="0" active></lib-loader> Laden...</div>
            </div>
            <div class="custom-renderer" data-renderer="rights_matrix" data-field="group_report_rights">
                <div class="loading-placeholder"><lib-loader size="small" delay="0" active></lib-loader> Laden...</div>
            </div>
            <div class="custom-renderer" data-renderer="userlist" data-field="group_members">
                <div class="loading-placeholder"><lib-loader size="small" delay="0" active></lib-loader> Laden...</div>
            </div>
        </div></body></html>`, { runScripts: 'dangerously' });
    const win = dom.window;
    // Als <script> in het document, niet via eval: dan gedragen de functiedeclaraties
    // zich precies zoals in de browser (globals op window).
    const script = win.document.createElement('script');
    script.textContent = `
        var gevraagdeUrls = [];
        var stubInitRightsMatrix = function () {};
        var stubFormParam = function () { return 'form=groups'; };
        var cmaLog = { warn: function () {}, error: function () {} };
        var fetch = function (url) {
            gevraagdeUrls.push(url);
            return Promise.resolve({
                ok: ${antwoord.ok},
                status: ${antwoord.status || 200},
                statusText: 'x',
                json: function () { return Promise.resolve(${JSON.stringify(antwoord.body)}); }
            });
        };
        ${haalLaderUitBron()}
    `;
    win.document.body.appendChild(script);
    return { win, doc: win.document };
}

test('de plaatshouders worden ingevuld, ook zonder record-ID', async () => {
    const { win, doc } = maakPagina({ ok: true, body: { success: true, html: '<table class="rights-matrix"></table>' } });

    await win.loadCustomRenderers('');

    assert.gelijk(doc.querySelectorAll('.loading-placeholder').length, 0,
        'er mag geen enkel molentje blijven staan');
    assert.gelijk(doc.querySelectorAll('.rights-matrix').length, 3,
        'alle drie de velden krijgen hun besturing');
});

test('een leeg record-ID gaat gewoon mee in de aanvraag', async () => {
    // De renderer heeft niets te tonen bij een nieuw record, maar levert wel de lege
    // besturing. Zonder die besturing kan het opslaan de rechten niet meesturen.
    const { win } = maakPagina({ ok: true, body: { success: true, html: '<i>x</i>' } });

    await win.loadCustomRenderers('');

    const urls = win.gevraagdeUrls;
    assert.gelijk(urls.length, 3, 'één aanvraag per veld met een eigen renderer');
    assert.waar(urls[0].indexOf('id=') > -1, 'het record-ID hoort in de URL te staan');
    assert.waar(urls[0].indexOf('action=renderer') > -1, 'en het is de renderer-aanroep');
});

test('een mislukte renderer laat een melding achter, geen eeuwig molentje', async () => {
    const { win, doc } = maakPagina({ ok: true, body: { success: false, error: 'stuk' } });

    await win.loadCustomRenderers('');

    assert.gelijk(doc.querySelectorAll('.loading-placeholder').length, 0,
        'ook bij een fout verdwijnt het molentje');
    assert.waar(doc.body.innerHTML.indexOf('Laden mislukt') > -1,
        'en er staat waarom');
});

test('de directe URL naar een nieuw record roept de lader ook aan', () => {
    // De behaviour-tests hierboven bewijzen de lader; dit houdt de AANROEP vast op de
    // enige weg waar hij ontbrak. newRecord() en loadRecord() hadden hem al.
    const start = bron.indexOf("} else if (document.body.classList.contains('is-creating')) {");
    assert.waar(start > -1, 'de init-tak voor een nieuw record moet bestaan');
    const eind = bron.indexOf('        } else {', start);
    const tak = bron.slice(start, eind > start ? eind : start + 3000);
    assert.waar(tak.indexOf('loadCustomRenderers') > -1,
        'de server-gerenderde nieuw-record-tak moet de renderers laden');
});
