/**
 * Recordteller en kolomfilter.
 *
 * Gemeld: met een kolomfilter aan bleef "records 1-x van y" staan. Een kolomfilter
 * werkt alleen op de rijen die al geladen zijn, dus een telling van de hele lijst
 * zegt dan niets; de trechter in de kolomkop laat al zien dat er gefilterd wordt.
 * Afspraak:
 * - lib-table meldt 'column-filter-change' ({active}) als de tabel van ongefilterd
 *   naar gefilterd gaat of terug;
 * - CMA.utils.setRecordCount houdt de teller leeg zolang er in #listContent een
 *   kolomfilter aan staat, ook als een batch op de achtergrond binnenkomt;
 * - gaat het laatste filter uit, dan schrijft de eigenaar de teller opnieuw.
 *
 * Draait tegen de echte bronnen: cma-utils.js, lib-table.js en het listener-blok
 * uit form-controller.js.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const ROOT = path.join(__dirname, '..', '..', '..');
const UTILS = fs.readFileSync(path.join(ROOT, 'cma', 'assets', 'js', 'cma-utils.js'), 'utf8');
const LIBTABLE = fs.readFileSync(path.join(ROOT, 'library', 'webcomponents', 'lib-table.js'), 'utf8');
const CONTROLLER = fs.readFileSync(path.join(ROOT, 'cma', 'assets', 'js', 'form-controller.js'), 'utf8');

const begin = CONTROLLER.indexOf('if (!window._cmaKolomFilterTellerGebonden)');
const eind = CONTROLLER.indexOf('// Initialize when DOM is ready', begin);
if (begin === -1 || eind === -1) throw new Error('listener-blok niet gevonden in form-controller.js');
const LISTENER = CONTROLLER.slice(begin, eind);

const TABEL = '<table id="listTable"><thead><tr><th>Naam</th><th>Plaats</th></tr></thead><tbody>' +
    '<tr data-id="1"><td>Bram</td><td>Utrecht</td></tr>' +
    '<tr data-id="2"><td>Anna</td><td>Zwolle</td></tr>' +
    '<tr data-id="3"><td>Carla</td><td>Utrecht</td></tr>' +
    '</tbody></table>';

function laad() {
    const dom = new JSDOM('<!doctype html><html><body>' +
        '<span id="recordCount"></span><div class="form-layout"></div><div id="listContent"></div></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php?form=x', pretendToBeVisual: true });
    const w = dom.window;
    [UTILS, LIBTABLE, LISTENER].forEach((src) => {
        const s = w.document.createElement('script');
        s.textContent = src;
        w.document.body.appendChild(s);
    });
    // De controller van het formulier op het scherm: alleen wat de listener vraagt.
    const geschreven = [];
    w.document.querySelector('.form-layout')._cmaController = {
        _tableData: { count: 50, totalCount: 200 },
        updateRecordCount: (n, totaal) => {
            geschreven.push([n, totaal]);
            w.CMA.utils.setRecordCount(w.CMA.utils.formatRecordCount(n, totaal, false));
        }
    };
    const events = [];
    w.document.addEventListener('column-filter-change', (e) => events.push(e.detail.active));
    w.document.getElementById('listContent').innerHTML = '<lib-table>' + TABEL + '</lib-table>';
    return new Promise((klaar, mis) => {
        let n = 0;
        const kijk = () => {
            if (w.document.querySelector('thead th .dropdown-filter-content')) { klaar({ w, events, geschreven }); return; }
            if (++n > 100) { mis(new Error('<lib-table> heeft geen filtermenu opgebouwd')); return; }
            setTimeout(kijk, 20);
        };
        kijk();
    });
}

function zetZwolle(w, aan) {
    const plaats = w.document.querySelectorAll('thead th')[1];
    const zwolle = Array.from(plaats.querySelectorAll('.dropdown-filter-menu-item.item')).find((i) => i.value === 'Zwolle');
    zwolle.checked = aan;
    zwolle.dispatchEvent(new w.Event('change'));
}

const teller = (w) => w.document.getElementById('recordCount').textContent;

suite('Recordteller verborgen bij een kolomfilter');

test('een kolomfilter maakt de teller leeg en meldt column-filter-change', async () => {
    const { w, events } = await laad();
    w.CMA.utils.setRecordCount('records 1-50 van 200');
    zetZwolle(w, false);
    assert.diepgelijk(events, [true]);
    assert.gelijk(teller(w), '');
});

test('zolang het filter aan staat schrijft een nieuwe batch de teller niet terug', async () => {
    const { w } = await laad();
    zetZwolle(w, false);
    w.CMA.utils.setRecordCount(w.CMA.utils.formatRecordCount(100, 200, true));
    assert.gelijk(teller(w), '');
});

test('gaat het filter uit, dan schrijft de controller de teller opnieuw', async () => {
    const { w, events, geschreven } = await laad();
    zetZwolle(w, false);
    zetZwolle(w, true);
    assert.diepgelijk(events, [true, false]);
    assert.diepgelijk(geschreven, [[50, 200]]);
    assert.gelijk(teller(w), 'records 1-50 van 200');
});

test('heeft de tabel een infinite scroller, dan schrijft die de teller', async () => {
    const { w, geschreven } = await laad();
    let gevraagd = 0;
    w.document.getElementById('listTable')._cmaScroller = { destroyed: false, updateRecordCountDisplay: () => { gevraagd++; } };
    zetZwolle(w, false);
    zetZwolle(w, true);
    assert.gelijk(gevraagd, 1);
    assert.diepgelijk(geschreven, [], 'de controller schrijft dan niet ook nog');
});

test('een tweede vinkje in hetzelfde filter meldt niets nieuws', async () => {
    const { w, events } = await laad();
    zetZwolle(w, false);
    const plaats = w.document.querySelectorAll('thead th')[1];
    const utrecht = Array.from(plaats.querySelectorAll('.dropdown-filter-menu-item.item')).find((i) => i.value === 'Utrecht');
    utrecht.checked = false;
    utrecht.dispatchEvent(new w.Event('change'));
    assert.diepgelijk(events, [true]);
});
