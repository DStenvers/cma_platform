/**
 * Het rijmenu hoort niet in de filterwaarden te staan.
 *
 * De eerste cel van elke rij draagt de knop van het rijmenu: <span class="row-menu-trigger">⋮</span>.
 * Die staat op display:none tot je over de rij gaat. <lib-table> leest de waarde van een cel met
 * innerText, en innerText slaat verborgen tekst over — zolang de tabel gerenderd wordt.
 *
 * Wordt hij dat niet (een subformulier-tabblad dat nog niet gekozen is, een dichtgeklapt paneel),
 * dan geeft innerText een lege string en valt de code terug op textContent. Die telt de ⋮ wél mee,
 * en dan begint elke waarde in de keuzelijst van de eerste kolom met dat teken:
 *
 *     ⋮MG.EMV.2521- EMDR Volwassenen Modulair (GZ)
 *
 * Gemeld op het opleidingen-filter van een deelnemer. Hetzelfde gold voor sorteren: de sorteersleutel
 * van die kolom begon ook met ⋮.
 *
 * jsdom kent innerText niet, dus de terugval naar textContent is hier de normale weg — precies de
 * situatie die op het scherm misging.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');

function maakTabel() {
    const rijen = [
        ['MG.EMV.2521- EMDR Volwassenen Modulair (GZ)', 'Hester van Gessel-Steur'],
        ['MG.EMV.2522- EMDR Kind en Jeugd', 'Marlijn Rovers'],
    ].map(([opleiding, naam]) =>
        '<tr data-id="1"><td data-field="opleiding"><span class="row-menu-trigger" data-id="1">⋮</span>'
        + opleiding + '</td><td data-field="naam">' + naam + '</td></tr>').join('');
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<lib-table><table><thead><tr><th>Opleiding</th><th>Deelnemer</th></tr></thead>' +
        '<tbody>' + rijen + '</tbody></table></lib-table>' +
        '<script>' + fs.readFileSync(LIBTABLE, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/lijst.php', pretendToBeVisual: true }
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

suite('lib-table: rijmenu in filterwaarden');

test('de keuzelijst van de eerste kolom begint niet met het rijmenu-teken', async () => {
    const doc = await naOpbouw(maakTabel());
    const menu = doc.querySelectorAll('thead th')[0].querySelector('.dropdown-filter-content');
    const labels = Array.from(menu.querySelectorAll('.checkbox-container label'))
        .map(l => (l.textContent || '').trim())
        .filter(t => t !== '' && t.toLowerCase() !== 'alles selecteren');

    assert.waar(labels.length > 0, 'er staan waarden in de keuzelijst');
    for (const t of labels) {
        assert.onwaar(t.indexOf('⋮') > -1, 'waarde draagt het rijmenu-teken: ' + JSON.stringify(t));
    }
    assert.waar(labels.some(t => t.indexOf('EMDR Volwassenen Modulair') > -1),
        'de echte opleidingsnaam hoort er wel in te staan');
});

test('de tweede kolom, zonder rijmenu, blijft ongemoeid', async () => {
    const doc = await naOpbouw(maakTabel());
    const menu = doc.querySelectorAll('thead th')[1].querySelector('.dropdown-filter-content');
    const labels = Array.from(menu.querySelectorAll('.checkbox-container label')).map(l => (l.textContent || '').trim());
    assert.waar(labels.some(t => t.indexOf('Hester van Gessel-Steur') > -1),
        'de naam hoort onveranderd in de keuzelijst te staan');
});
