/**
 * Eén hand kleurt de bewaarknop.
 *
 * De knop kent twee gezichten: 'muted' (schoon — gedempt maar klikbaar) en
 * 'dirty' (het rode pulseren — er staat iets ongeboekt). Dat zijn twee helften
 * van dezelfde toestand, en ze werden vanuit twee plekken geschilderd: de
 * controller zette 'muted', het legacy-script in cma.js zette 'dirty' er los
 * naast — o.a. vanuit CKEditor-changes die al bij het openen afgaan. Het
 * resultaat stond letterlijk op het scherm: een halfdoorzichtige knop die rood
 * pulseerde. "Uitgeschakeld" en "niet opgeslagen" tegelijk.
 *
 * De afspraak, hier vastgepind op de uitgeleverde bron (niet op een kopie):
 *   1. setDirty() van de controller zet BEIDE klassen, altijd tegengesteld.
 *   2. setDirty() in cma.js delegeert naar de controller zodra die er is
 *      (herkenbaar aan ._cmaController op de .form-layout) en schildert dan
 *      zelf niets.
 *   3. Zonder controller (legacy tb_DoSave-pagina's) doet cma.js wat het
 *      altijd deed.
 *
 * Run: node tests/js/run.js knopstate
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const CMAJS = path.join(__dirname, '..', '..', 'assets', 'js', 'cma.js');

let fails = 0;
function ok(label, got, expected) {
    const g = JSON.stringify(got), e = JSON.stringify(expected);
    if (g !== e) { fails++; console.error('FAIL', label, 'got', g, 'expected', e); }
    else console.log('ok', label);
}

/* --- 1+2: de setDirty van de controller, geknipt uit de echte bron --------- */
function knipMethode(src, naam) {
    const kop = src.indexOf(`    ${naam}(`);
    if (kop === -1) throw new Error(`${naam} niet gevonden`);
    let i = src.indexOf('{', kop), diepte = 0, start = i;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}' && --diepte === 0) break;
    }
    return src.slice(start, i + 1);
}

const controllerSrc = fs.readFileSync(CONTROLLER, 'utf8');
const setDirtyBody = knipMethode(controllerSrc, 'setDirty');

const dom = new JSDOM(`<!doctype html><html><body>
    <div class="form-layout" id="fl">
        <span class="tb-btn" id="toolbar_save"><a href="#" data-action="save"><span class="lnr lnr-save"></span></a></span>
    </div>
</body></html>`);
const { window } = dom;
const { document } = window;

// De helpers waar setDirty op leunt, uit dezelfde bron geknipt.
const helpers = controllerSrc.slice(
    controllerSrc.indexOf('function cmaFormLayoutVan('),
    controllerSrc.indexOf('class CmaFormController'));
const maakController = new Function('document', 'window', `
    ${helpers}
    const ctrl = {
        formLayout: document.getElementById('fl'),
        setDirty: function (dirty) { ${setDirtyBody.slice(1, -1)} }
    };
    return ctrl;
`);
const ctrl = maakController(document, window);
const knop = document.getElementById('toolbar_save');

ctrl.setDirty(true);
ok('vuil: rood aan', knop.classList.contains('dirty'), true);
ok('vuil: niet gedempt', knop.classList.contains('muted'), false);
ok('vuil: het formulier weet het ook', document.getElementById('fl').dataset.isdirty, 'true');

ctrl.setDirty(false);
ok('schoon: rood uit', knop.classList.contains('dirty'), false);
ok('schoon: gedempt aan', knop.classList.contains('muted'), true);

// De kern van de klacht: in geen enkele volgorde bestaan beide tegelijk.
for (const reeks of [[true, false, true], [false, true, false]]) {
    for (const stand of reeks) ctrl.setDirty(stand);
    ok('nooit gedempt én rood tegelijk (na ' + JSON.stringify(reeks) + ')',
        knop.classList.contains('muted') && knop.classList.contains('dirty'), false);
}

/* --- 2+3: de setDirty van cma.js delegeert — of juist niet ----------------- */
const cmaSrc = fs.readFileSync(CMAJS, 'utf8');
const van = cmaSrc.indexOf('function modernFormController()');
const tot = cmaSrc.indexOf('function setDirty()');
const eindSetDirty = cmaSrc.indexOf('}', cmaSrc.indexOf('jQuery', tot) + 1);
if (van === -1 || tot === -1) throw new Error('setDirty/modernFormController niet in cma.js gevonden');
// modernFormController + setDirty, plus een minimale jQuery en dirtySet eromheen.
const legacyStuk = cmaSrc.slice(van, cmaSrc.indexOf('function checkIfDirty'))
    + cmaSrc.slice(tot, cmaSrc.indexOf('\n        }', eindSetDirty) + 10);

function maakLegacy(document) {
    const geschilderd = [];
    const jQuery = (sel) => ({ addClass: (c) => geschilderd.push(sel + '+' + c) });
    const maak = new Function('document', 'jQuery', `
        let dirtySet = false;
        ${legacyStuk}
        return { setDirty: setDirty, geschilderd: arguments[2] };
    `);
    return { run: (g) => maak(document, jQuery, g), geschilderd };
}

// Mét controller: delegatie, geen eigen verf.
let doorgegeven = 0;
document.getElementById('fl')._cmaController = { setDirty: () => doorgegeven++ };
const met = maakLegacy(document);
met.run(met.geschilderd).setDirty();
ok('met controller: doorgegeven', doorgegeven, 1);
ok('met controller: zelf niets geschilderd', met.geschilderd, []);

// Zonder controller: het oude gedrag, onveranderd.
delete document.getElementById('fl')._cmaController;
const zonder = maakLegacy(document);
zonder.run(zonder.geschilderd).setDirty();
ok('zonder controller: schildert zoals vroeger',
    zonder.geschilderd, ['#toolbar_save,#toolbar_saveclose+dirty']);

console.log(fails ? `\n${fails} FAILED` : '\nALL PASSED');
process.exit(fails ? 1 : 0);
