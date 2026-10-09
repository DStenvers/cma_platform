/**
 * Filter en sortering van een tabel worden onthouden, ook zonder data-json-form.
 *
 * Een lijst met data-json-form of data-name bewaart zijn filter onder die naam. Een tabel op
 * de front-end heeft geen van beide; die valt terug op pad + kolomkoppen, zoals library.js van
 * de ASP-site doet. Naast de uitgevinkte waarden wordt ook de gekozen sortering bewaard en bij
 * de volgende opbouw teruggezet. Zoeken in een filtermenu is tijdelijk en wordt niet bewaard.
 *
 * Zonder data-field op de kolomkop identificeert de kolomkop-tekst de kolom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');
const BRON = fs.readFileSync(LIBTABLE, 'utf8');

const TABEL = '<table><thead><tr><th>Naam</th><th>Plaats</th></tr></thead><tbody>' +
    '<tr><td>Bram</td><td>Utrecht</td></tr>' +
    '<tr><td>Anna</td><td>Zwolle</td></tr>' +
    '<tr><td>Carla</td><td>Utrecht</td></tr>' +
    '</tbody></table>';

// Eén "browser" (gedeelde localStorage via dezelfde origin is in jsdom per venster; daarom
// geven we de opgeslagen inhoud handmatig door aan het tweede venster).
function laad(opgeslagen) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/index.php?pageaction=lijst', pretendToBeVisual: true });
    const w = dom.window;
    Object.keys(opgeslagen || {}).forEach(k => w.localStorage.setItem(k, opgeslagen[k]));
    const s = w.document.createElement('script');
    s.textContent = BRON;
    w.document.body.appendChild(s);
    w.document.body.insertAdjacentHTML('beforeend', '<lib-table>' + TABEL + '</lib-table>');
    return new Promise((klaar, mis) => {
        let n = 0;
        const kijk = () => {
            if (w.document.querySelector('thead th .dropdown-filter-content')) { klaar(w); return; }
            if (++n > 100) { mis(new Error('<lib-table> heeft geen filtermenu opgebouwd')); return; }
            setTimeout(kijk, 20);
        };
        kijk();
    });
}

function opslag(w) {
    const r = {};
    for (let i = 0; i < w.localStorage.length; i++) {
        const k = w.localStorage.key(i);
        r[k] = w.localStorage.getItem(k);
    }
    return r;
}

const zichtbareNamen = w => Array.from(w.document.querySelectorAll('tbody tr'))
    .filter(tr => tr.style.display !== 'none').map(tr => tr.children[0].textContent);

suite('lib-table: filter en sortering onthouden zonder data-json-form');

test('uitvinken en sorteren worden bewaard onder pad + kolomkoppen', async () => {
    const w = await laad();
    const plaats = w.document.querySelectorAll('thead th')[1];
    const zwolle = Array.from(plaats.querySelectorAll('.dropdown-filter-menu-item.item')).find(i => i.value === 'Zwolle');
    zwolle.checked = false;
    zwolle.dispatchEvent(new w.Event('change'));
    w.document.querySelectorAll('thead th')[0].querySelector('.dropdown-filter-sort span.z---a').click();

    const o = opslag(w);
    const sleutel = 'cma_tableFilter_/index.php::Naam|Plaats';
    assert.waar(sleutel in o, 'sleutel ' + sleutel + ' ontbreekt, wel: ' + JSON.stringify(Object.keys(o)));
    const staat = JSON.parse(o[sleutel]);
    assert.gelijk(JSON.stringify(staat.Plaats), JSON.stringify({ t: 'c', ex: ['Zwolle'] }));
    assert.gelijk(JSON.stringify(staat.__sort), JSON.stringify({ c: 'Naam', o: 'z---a' }));
});

test('bij een nieuwe opbouw komen filter en sortering terug', async () => {
    const eerste = await laad();
    const plaats = eerste.document.querySelectorAll('thead th')[1];
    const zwolle = Array.from(plaats.querySelectorAll('.dropdown-filter-menu-item.item')).find(i => i.value === 'Zwolle');
    zwolle.checked = false;
    zwolle.dispatchEvent(new eerste.Event('change'));
    eerste.document.querySelectorAll('thead th')[0].querySelector('.dropdown-filter-sort span.z---a').click();

    const w = await laad(opslag(eerste));
    assert.gelijk(zichtbareNamen(w).join(','), 'Carla,Bram', 'Zwolle verborgen en aflopend op naam');
});

test('zoeken in het filtermenu wordt niet bewaard', async () => {
    const w = await laad();
    const zoek = w.document.querySelectorAll('thead th')[1].querySelector('.dropdown-filter-menu-search');
    zoek.value = 'zwol';
    zoek.dispatchEvent(new w.Event('keyup'));
    assert.gelijk(zichtbareNamen(w).join(','), 'Anna', 'zoeken filtert wel');
    assert.gelijk(Object.keys(opslag(w)).filter(k => k.indexOf('cma_tableFilter_') === 0).length, 0,
        'maar er is niets opgeslagen');
});
