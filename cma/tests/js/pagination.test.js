/**
 * lib-pagination: bladerknoppen als links (server-side) of als event (client-side).
 *
 * Draait tegen de echte bron in library/webcomponents/lib-pagination.js, in jsdom.
 * Afspraken: venster rond de huidige pagina, eerste/vorige uit aan het begin en
 * volgende/laatste aan het eind, {page} in het href-sjabloon, pages uit total en
 * page-size, niets renderen bij één pagina, en page-change met en zonder href.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-pagination.js');

function maak(attributen) {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<lib-pagination id="p" ' + attributen + '></lib-pagination>' +
        '<script>' + fs.readFileSync(BRON, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/main.php' }
    );
    const win = dom.window;
    const el = win.document.getElementById('p');
    const knoppen = () => [...el.shadowRoot.querySelectorAll('[data-page]')];
    return {
        win, el, knoppen,
        labels: () => knoppen().map((k) => k.textContent),
        knop: (label) => knoppen().find((k) => k.textContent === label),
        klik: (label) => {
            const k = knoppen().find((x) => x.textContent === label);
            const ev = new win.MouseEvent('click', { bubbles: true, cancelable: true, composed: true });
            k.dispatchEvent(ev);
            return ev.defaultPrevented;
        },
    };
}

suite('lib-pagination');

test('venster van nummers rond de huidige, met eerste/vorige/volgende/laatste', () => {
    const p = maak('page="8" pages="16" href="?p={page}"');
    assert.diepgelijk(p.labels(), ['«', '‹', '5', '6', '7', '8', '9', '10', '11', '›', '»']);
    assert.gelijk(p.knop('8').getAttribute('aria-current'), 'page', 'huidige pagina');
    assert.gelijk(p.knop('9').getAttribute('href'), '?p=9', '{page} vervangen');
    assert.gelijk(p.knop('«').getAttribute('href'), '?p=1');
    assert.gelijk(p.knop('»').getAttribute('href'), '?p=16');
});

test('aan het begin staan eerste en vorige uit, aan het eind volgende en laatste', () => {
    const begin = maak('page="1" pages="16" href="?p={page}"');
    assert.gelijk(begin.knop('«').getAttribute('aria-disabled'), 'true');
    assert.gelijk(begin.knop('‹').getAttribute('aria-disabled'), 'true');
    assert.gelijk(begin.knop('‹').tagName, 'SPAN', 'een uitgeschakelde knop is geen link');
    assert.diepgelijk(begin.labels().slice(2, 6), ['1', '2', '3', '4']);
    const eind = maak('page="16" pages="16" href="?p={page}"');
    assert.gelijk(eind.knop('›').getAttribute('aria-disabled'), 'true');
    assert.gelijk(eind.knop('»').getAttribute('aria-disabled'), 'true');
});

test('pages uit total en page-size; één pagina rendert niets', () => {
    const p = maak('page="1" total="3085" page-size="200" href="?p={page}"');
    assert.gelijk(p.el.pages, 16);
    assert.gelijk(p.knop('»').getAttribute('href'), '?p=16');
    const een = maak('page="1" pages="1" href="?p={page}"');
    assert.gelijk(een.knoppen().length, 0, 'niets te bladeren');
    const nul = maak('total="0" page-size="200"');
    assert.gelijk(nul.knoppen().length, 0);
});

test('window en no-ends', () => {
    const p = maak('page="8" pages="16" window="1" no-ends href="?p={page}"');
    assert.diepgelijk(p.labels(), ['‹', '7', '8', '9', '›']);
});

test('page buiten bereik wordt binnen het bereik gehouden', () => {
    assert.gelijk(maak('page="99" pages="16"').el.page, 16);
    assert.gelijk(maak('page="0" pages="16"').el.page, 1);
});

test('met href: page-change vuurt, de link volgt tenzij de luisteraar preventDefault doet', () => {
    const p = maak('page="3" pages="16" href="?p={page}"');
    const gezien = [];
    p.el.addEventListener('page-change', (e) => gezien.push(e.detail));
    const tegengehouden = p.klik('4');
    assert.diepgelijk(gezien, [{ page: 4, href: '?p=4' }]);
    assert.onwaar(tegengehouden, 'de link mag gewoon navigeren');
    assert.gelijk(p.el.page, 3, 'het attribuut page verandert niet: de server tekent de nieuwe pagina');
    p.el.addEventListener('page-change', (e) => e.preventDefault());
    assert.waar(p.klik('5'), 'preventDefault op het event houdt de navigatie tegen');
});

test('zonder href: de klik zet page zelf en vuurt page-change', () => {
    const p = maak('page="1" pages="9"');
    const gezien = [];
    p.el.addEventListener('page-change', (e) => gezien.push(e.detail.page));
    assert.gelijk(p.knop('2').tagName, 'SPAN', 'geen link zonder href');
    p.klik('2');
    assert.gelijk(p.el.page, 2);
    assert.diepgelijk(gezien, [2]);
    p.klik('›');
    assert.gelijk(p.el.page, 3);
    p.klik('3');   // de huidige: niets
    assert.diepgelijk(gezien, [2, 3]);
});

test('properties renderen opnieuw', () => {
    const p = maak('page="1" pages="3"');
    p.el.pages = 20;
    p.el.page = 10;
    assert.diepgelijk(p.labels(), ['«', '‹', '7', '8', '9', '10', '11', '12', '13', '›', '»']);
    assert.gelijk(p.el.hrefFor(4), null, 'zonder href geen link');
    p.el.setAttribute('href', '/lijst?p={page}&q=a{page}');
    assert.gelijk(p.el.hrefFor(4), '/lijst?p=4&q=a4', 'elke {page} vervangen');
});
