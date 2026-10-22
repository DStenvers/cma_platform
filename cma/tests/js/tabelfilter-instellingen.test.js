/**
 * De grens van het kolomfilter van <lib-table> komt uit window.CMA.settings
 * (TABLE_FILTER_MAX_CHECKBOXES) als het CMA die injecteert, en is anders 30:
 * het aantal verschillende waarden per kolom, niet het aantal rijen. Draait tegen
 * de echte bron.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');

function maakTabel(rijen, waarden, settings) {
    let tr = '';
    for (let i = 0; i < rijen; i++) {
        tr += '<tr><td>Groep ' + (i % waarden) + '</td><td>Titel ' + i + '</td></tr>';
    }
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        (settings ? '<script>window.CMA={settings:' + JSON.stringify(settings) + '};</script>' : '') +
        '<lib-table resizable><table><thead><tr><th>Groep</th><th>Titel</th></tr></thead>' +
        '<tbody>' + tr + '</tbody></table></lib-table>' +
        '<script>' + fs.readFileSync(LIBTABLE, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/main.php', pretendToBeVisual: true }
    );
    return dom.window.document;
}

function naOpbouw(doc) {
    return new Promise((klaar, mis) => {
        let pogingen = 0;
        const kijk = () => {
            if (doc.querySelector('thead th .dropdown-filter-content')) { klaar(doc); return; }
            if (++pogingen > 100) { mis(new Error('<lib-table> heeft geen filtermenu opgebouwd')); return; }
            setTimeout(kijk, 20);
        };
        kijk();
    });
}

function menuVan(doc, n) {
    const th = doc.querySelectorAll('thead th')[n];
    return th ? th.querySelector('.dropdown-filter-content') : null;
}

suite('lib-table: filtergrenzen uit de instellingen');

test('zonder injectie: 7 waarden blijven aanvinkbaar (grens 30)', async () => {
    const doc = await naOpbouw(maakTabel(40, 7, null));
    assert.waar(menuVan(doc, 0).querySelectorAll('.checkbox-container input').length > 0, 'keuzelijst met vinkjes');
});

test('tableFilterMaxCheckboxes=3 maakt van 7 waarden een tekstfilter', async () => {
    const doc = await naOpbouw(maakTabel(40, 7, { tableFilterMaxCheckboxes: 3 }));
    const menu = menuVan(doc, 0);
    assert.gelijk(menu.querySelectorAll('.checkbox-container input').length, 0, 'geen vinkjes boven de ingestelde grens');
    assert.waar(!!menu.querySelector('input.dropdown-filter-menu-search'), 'wel een tekstfilter');
});

test('het aantal rijen telt niet: 1200 rijen met 3 waarden blijven aanvinkbaar', async () => {
    // Een oude rijgrens (TABLE_FILTER_MAX_VALUES) gaf boven 500 rijen elke kolom een zoekveld,
    // ook een ja/nee-kolom. Een injectie van die oude sleutel mag niets meer doen.
    const doc = await naOpbouw(maakTabel(1200, 3, { tableFilterMaxValues: 20 }));
    assert.gelijk(menuVan(doc, 0).querySelectorAll('.checkbox-container input.item').length, 3, 'drie aanvinkbare waarden');
    const titel = menuVan(doc, 1);
    assert.gelijk(titel.querySelectorAll('.checkbox-container input').length, 0, 'kolom met 1200 verschillende waarden: geen vinkjes');
    assert.waar(!!titel.querySelector('input.dropdown-filter-menu-search'), 'maar een zoekveld');
});
