/**
 * Bewijs voor "een lange lijst houdt een filter".
 *
 * <lib-table> somt per kolom de waarden op in een keuzelijst. Boven MAX_FILTER_LENGTH (500)
 * rijen is dat te duur en wordt zo'n lijst ook onbruikbaar lang, dus dat gebeurde niet meer.
 * Alleen: er kwam ook niets voor in de plaats. Het filtermenu ging open en was leeg — geen
 * keuzelijst, geen zoekveld. Gemeld op het rapport "ontbrekende presentie" van mijnRINO
 * (1182 regels): daar had ELKE kolom een leeg filter, en was er dus niets te filteren.
 *
 * Er bestond al een antwoord voor precies dit probleem: een kolom met meer dan 30
 * verschillende waarden krijgt geen vinkjes maar een vrij tekstveld. Dat is nu ook het
 * antwoord op "te veel rijen".
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

test('lange lijst (> 500 rijen) krijgt een vrij tekstveld in plaats van niets', async () => {
    const doc = await naOpbouw(maakTabel(600));
    for (const kolom of [0, 1]) {
        const menu = menuVan(doc, kolom);
        assert.waar(!!menu, 'kolom ' + kolom + ' heeft een filtermenu');
        assert.waar(menu.querySelectorAll('.checkbox-container input').length === 0,
            'kolom ' + kolom + ': geen keuzelijst met 600 waarden');
        assert.waar(!!menu.querySelector('input.dropdown-filter-menu-search'),
            'kolom ' + kolom + ': er staat wel een tekstfilter');
    }
});

test('continue modus blijft leeg: daar gaat de server over de rijen', async () => {
    const doc = await naOpbouw(maakTabel(600, { continuous: true }));
    const menu = menuVan(doc, 0);
    assert.waar(!!menu, 'er is een filtermenu');
    assert.waar(!menu.querySelector('input.dropdown-filter-menu-search'),
        'geen tekstfilter in continue modus');
});
