/**
 * Bewijs voor "een lange lijst houdt een filter".
 *
 * <lib-table> somt per kolom de waarden op in een keuzelijst. Of een kolom vinkjes of een
 * vrij tekstveld krijgt, hangt af van het aantal VERSCHILLENDE waarden in die kolom (meer dan
 * 30: tekstveld), niet van het aantal rijen. Een lange lijst houdt zo zijn vinkjes voor een
 * kolom als "Login actief?", en een kolom met veel waarden krijgt een zoekveld - nooit een
 * leeg filtermenu.
 *
 * Wat hier NIET verandert: een tabel in continue modus (data-continuous). Daar bepaalt de
 * server welke rijen er zijn en zegt wat er in de DOM staat niets over het geheel.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');

/** Een tabel met $rijen regels, eventueel in continue modus. */
function maakTabel(rijen, opties) {
    opties = opties || {};
    let tr = '';
    for (let i = 0; i < rijen; i++) {
        tr += '<tr><td>Groep ' + (i % 7) + '</td><td>Titel ' + i + '</td></tr>';
    }
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<lib-table resizable><table' + (opties.continuous ? ' data-continuous' : '') + '>' +
        '<thead><tr><th>Groep</th><th>Titel</th></tr></thead>' +
        '<tbody>' + tr + '</tbody></table></lib-table>' +
        '<script>' + fs.readFileSync(LIBTABLE, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/rapport.php', pretendToBeVisual: true }
    );
    return dom.window.document;
}

/** Wacht tot het component zijn koppen heeft omgebouwd (dat gebeurt na een tik). */
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

/** De filtermenu-inhoud van kolom $n. */
function menuVan(doc, n) {
    const th = doc.querySelectorAll('thead th')[n];
    return th ? th.querySelector('.dropdown-filter-content') : null;
}

suite('lib-table: filter bij veel rijen');

test('korte lijst houdt de keuzelijst met waarden', async () => {
    const doc = await naOpbouw(maakTabel(10));
    const menu = menuVan(doc, 0);
    assert.waar(!!menu, 'kolom 0 heeft een filtermenu');
    assert.waar(menu.querySelectorAll('.checkbox-container input').length > 0,
        'er staan aankruisbare waarden in');
});

test('lange lijst (600 rijen): weinig waarden houdt vinkjes, veel waarden krijgt een tekstveld', async () => {
    const doc = await naOpbouw(maakTabel(600));
    const groep = menuVan(doc, 0);
    assert.waar(!!groep, 'kolom 0 heeft een filtermenu');
    assert.gelijk(groep.querySelectorAll('.checkbox-container input.item').length, 7,
        'kolom 0 (7 groepen): zeven aanvinkbare waarden, ook bij 600 rijen');
    const titel = menuVan(doc, 1);
    assert.waar(titel.querySelectorAll('.checkbox-container input').length === 0,
        'kolom 1: geen keuzelijst met 600 waarden');
    assert.waar(!!titel.querySelector('input.dropdown-filter-menu-search'),
        'kolom 1: er staat wel een tekstfilter');
});

test('continue modus blijft leeg: daar gaat de server over de rijen', async () => {
    const doc = await naOpbouw(maakTabel(600, { continuous: true }));
    const menu = menuVan(doc, 0);
    assert.waar(!!menu, 'er is een filtermenu');
    assert.waar(!menu.querySelector('input.dropdown-filter-menu-search'),
        'geen tekstfilter in continue modus');
});
