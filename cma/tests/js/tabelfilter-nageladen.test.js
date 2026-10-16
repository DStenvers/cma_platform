/**
 * Een kolomfilter geldt ook voor rijen die later binnenkomen.
 *
 * <lib-table> verzamelt zijn rijen en filterkeuzes één keer, uit het eerste blok.
 * CmaInfiniteScroll laadt de rest in de achtergrond bij en roept daarna
 * refreshFilters() aan. Die riep alleen jQuery.fn.excelTableFilterRefresh aan, een
 * plugin die niet meer bestaat: de lib-table bleef op de rijen van het eerste blok
 * staan, en een uitgevinkte waarde verborg nageladen rijen met die waarde niet.
 * refreshFilters() vraagt nu de omringende lib-table om refresh(); zonder lib-table
 * blijft de jQuery-route bestaan.
 *
 * Run: node tests/js/run.js tabelfilter-nageladen
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');
const SCROLL = path.join(__dirname, '..', '..', 'assets', 'js', 'table-preferences.js');

const rij = (id, naam, code) => `<tr data-id="${id}"><td>${naam}</td><td>${code}</td></tr>`;

function laad() {
    const dom = new JSDOM('<!doctype html><html><body><div id="container"></div></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php', pretendToBeVisual: true });
    const w = dom.window;
    const s = w.document.createElement('script');
    s.textContent = 'window.cmaLog = { log(){}, warn(){}, error(){} };' +
        'window.CMA = { utils: { setRecordCount(){}, formatRecordCount(){ return ""; } } };' +
        fs.readFileSync(LIBTABLE, 'utf8') + '\n' + fs.readFileSync(SCROLL, 'utf8');
    w.document.body.appendChild(s);
    w.document.getElementById('container').innerHTML =
        '<lib-table><table id="listTable" data-json-form="opleidingen"><thead><tr>' +
        '<th data-field="naam">Naam</th><th data-field="code">Code</th></tr></thead><tbody>' +
        rij(1, 'Anna', 'A') + rij(2, 'Bram', 'B') + '</tbody></table></lib-table>';
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

const zichtbaar = w => Array.from(w.document.querySelectorAll('#listTable tbody tr'))
    .filter(tr => tr.style.display !== 'none').map(tr => tr.children[0].textContent);

function vinkUit(w, kolom, waarde) {
    const th = w.document.querySelectorAll('#listTable thead th')[kolom];
    const item = Array.from(th.querySelectorAll('.dropdown-filter-menu-item.item')).find(i => i.value === waarde);
    if (!item) throw new Error('filterkeuze ' + waarde + ' niet gevonden');
    item.checked = false;
    item.dispatchEvent(new w.Event('change'));
}

suite('Kolomfilter na het bijladen van rijen');

test('een uitgevinkte code verbergt ook nageladen rijen met die code', async () => {
    const w = await laad();
    vinkUit(w, 1, 'B');
    assert.gelijk(zichtbaar(w).join(','), 'Anna', 'eerste blok gefilterd');

    // Het volgende blok komt binnen, zoals CmaInfiniteScroll.load() rijen toevoegt.
    w.document.querySelector('#listTable tbody').insertAdjacentHTML('beforeend',
        rij(3, 'Carla', 'A') + rij(4, 'Dirk', 'B'));
    const scroller = new w.CmaInfiniteScroll({
        container: w.document.getElementById('container'),
        table: w.document.getElementById('listTable'),
        formId: 'opleidingen', pageSize: 2, loadMore: async () => ({ success: false })
    });
    scroller.refreshFilters();

    assert.gelijk(zichtbaar(w).join(','), 'Anna,Carla', 'Dirk (code B) moet verborgen zijn');
});

test('het filtermenu kent na refreshFilters ook de waarden van nageladen rijen', async () => {
    const w = await laad();
    w.document.querySelector('#listTable tbody').insertAdjacentHTML('beforeend', rij(5, 'Eva', 'C'));
    const scroller = new w.CmaInfiniteScroll({
        container: w.document.getElementById('container'),
        table: w.document.getElementById('listTable'),
        formId: 'opleidingen', pageSize: 2, loadMore: async () => ({ success: false })
    });
    scroller.refreshFilters();
    const th = w.document.querySelectorAll('#listTable thead th')[1];
    const waarden = Array.from(th.querySelectorAll('.dropdown-filter-menu-item.item')).map(i => i.value);
    assert.waar(waarden.includes('C'), 'C staat in het filtermenu: ' + JSON.stringify(waarden));
});

test('een tabel buiten een lib-table gebruikt de jQuery-plugin als die er is', () => {
    const dom = new JSDOM('<!doctype html><html><body><div id="c"><table id="t"><tbody></tbody></table></div></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' });
    const w = dom.window;
    const s = w.document.createElement('script');
    s.textContent = 'window.cmaLog = { log(){}, warn(){}, error(){} };' +
        'window.CMA = { utils: { setRecordCount(){}, formatRecordCount(){ return ""; } } };' +
        'window.geroepen = 0; window.jQuery = function () { return { excelTableFilterRefresh() { window.geroepen++; } }; };' +
        'window.jQuery.fn = { excelTableFilterRefresh() {} };' +
        fs.readFileSync(SCROLL, 'utf8');
    w.document.body.appendChild(s);
    const scroller = new w.CmaInfiniteScroll({
        container: w.document.getElementById('c'), table: w.document.getElementById('t'),
        formId: 'x', pageSize: 2, loadMore: async () => ({ success: false })
    });
    scroller.refreshFilters();
    assert.gelijk(w.geroepen, 1);
});
