/**
 * cma-launcher: het menu achter "Alle tools" en "Alle rapporten".
 *
 * Het menu vult zijn host-box en ligt over het iframe van `target`. Een keuze laadt de
 * href in dat iframe, sluit het menu en zet de deeplink in de adresbalk; een href buiten
 * /cma/ opent in een nieuw tabblad. Er is één modus: geen overlay over de hele pagina,
 * geen scroll-lock op <body>.
 *
 * Draait tegen de echte bron in cma/webcomponents/cma-launcher.js, in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'webcomponents', 'cma-launcher.js');

const CATALOGUS = {
    success: true,
    groups: [
        {
            type: 'folder', label: 'Beheer', icon: 'lnr-cog',
            children: [
                { type: 'item', label: 'Cache leegmaken', href: 'tools/tools_cache.php' },
                { type: 'item', label: 'Gebruikers', href: 'form.php?form=users' },
                { type: 'item', label: 'Site-tool', href: '/tools/export.php' },
                { type: 'folder', label: 'Testen', children: [
                    { type: 'item', label: 'Rapport 7', href: 'reportdetails.php?RepID=7', url: '/cma/reports?rep=7' }
                ] }
            ]
        }
    ]
};

function maakMenu() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<div id="tools" class="launcher-host">' +
        '<cma-launcher catalog-url="/cma/api/tools-catalog.php" target="#tools-content"></cma-launcher>' +
        '<iframe id="tools-content" src="about:blank"></iframe>' +
        '</div>' +
        '<script>' + fs.readFileSync(BRON, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/tools' }
    );
    const win = dom.window;
    const opgehaald = [];
    win.fetch = (url) => { opgehaald.push(url); return Promise.resolve({ json: () => Promise.resolve(CATALOGUS) }); };
    const el = win.document.querySelector('cma-launcher');
    const item = (label) => Array.from(el.querySelectorAll('.cma-launcher__item'))
        .find((a) => a.querySelector('.cma-launcher__item-label').textContent === label);
    return { win, el, opgehaald, item, iframe: win.document.getElementById('tools-content') };
}

suite('cma-launcher');

test('open() haalt de catalogus op en toont groepen, submappen als eigen sectie', async () => {
    const m = maakMenu();
    await m.el.open();
    assert.diepgelijk(m.opgehaald, ['/cma/api/tools-catalog.php']);
    assert.waar(m.el.isOpen(), 'isOpen()');
    assert.waar(m.el.classList.contains('is-open'), 'klasse is-open');
    const titels = Array.from(m.el.querySelectorAll('.cma-launcher__group-title')).map((h) => h.textContent.trim());
    assert.diepgelijk(titels, ['Beheer', 'Beheer · Testen']);
});

test('geen overlay: geen backdrop en geen scroll-lock op body', async () => {
    const m = maakMenu();
    await m.el.open();
    assert.gelijk(m.el.querySelector('.cma-launcher__backdrop'), null, 'backdrop');
    assert.onwaar(m.win.document.body.classList.contains('cma-launcher-open'), 'body-klasse');
    assert.gelijk(m.el.querySelector('.cma-launcher__panel').getAttribute('aria-modal'), 'false');
});

test('een keuze laadt de href in het iframe, sluit het menu en zet de deeplink', async () => {
    const m = maakMenu();
    await m.el.open();
    m.item('Cache leegmaken').click();
    assert.gelijk(m.iframe.getAttribute('src'), 'tools/tools_cache.php', 'iframe-src');
    assert.onwaar(m.el.isOpen(), 'menu dicht');
    assert.gelijk(m.win.location.pathname + m.win.location.search, '/cma/tools?tool=cache');
});

test('een formulier krijgt de tools-route als deeplink, een item met eigen url houdt die', async () => {
    const m = maakMenu();
    await m.el.open();
    assert.gelijk(m.item('Gebruikers').getAttribute('href'), '/cma/tools?form=users');
    assert.gelijk(m.item('Rapport 7').getAttribute('data-src'), 'reportdetails.php?RepID=7');
    assert.gelijk(m.item('Rapport 7').getAttribute('href'), '/cma/reports?rep=7');
});

test('een href buiten /cma/ opent in een nieuw tabblad en laat het iframe staan', async () => {
    const m = maakMenu();
    await m.el.open();
    const a = m.item('Site-tool');
    assert.gelijk(a.getAttribute('target'), '_blank');
    assert.gelijk(a.getAttribute('href'), '/tools/export.php');
    a.dispatchEvent(new m.win.MouseEvent('click', { bubbles: true, cancelable: true }));
    assert.gelijk(m.iframe.getAttribute('src'), 'about:blank', 'iframe ongewijzigd');
    assert.onwaar(m.el.isOpen(), 'menu dicht');
});

test('het item dat in het iframe staat is actief bij opnieuw openen', async () => {
    const m = maakMenu();
    await m.el.open();
    m.item('Gebruikers').click();
    await m.el.open();
    assert.waar(m.item('Gebruikers').classList.contains('is-active'), 'Gebruikers actief');
    assert.onwaar(m.item('Cache leegmaken').classList.contains('is-active'), 'Cache niet actief');
});

// Zoekveld in de toolbar (search-source): het menu rendert dan geen eigen zoekveld.
function maakMenuMetToolbarZoekveld() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<input id="toolsSearch" type="text">' +
        '<div id="tools" class="launcher-host">' +
        '<cma-launcher catalog-url="/cma/api/tools-catalog.php" target="#tools-content" search-source="#toolsSearch"></cma-launcher>' +
        '<iframe id="tools-content" src="about:blank"></iframe>' +
        '</div>' +
        '<script>' + fs.readFileSync(BRON, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/tools' }
    );
    const win = dom.window;
    win.fetch = () => Promise.resolve({ json: () => Promise.resolve(CATALOGUS) });
    const el = win.document.querySelector('cma-launcher');
    const veld = win.document.getElementById('toolsSearch');
    const zichtbaar = () => Array.from(el.querySelectorAll('.cma-launcher__item:not([hidden])'))
        .map((a) => a.querySelector('.cma-launcher__item-label').textContent);
    const typ = (tekst) => { veld.value = tekst; veld.dispatchEvent(new win.Event('input', { bubbles: true })); };
    const wacht = () => new Promise((klaar) => setTimeout(klaar, 20));
    return { win, el, veld, zichtbaar, typ, wacht };
}

test('met search-source heeft het menu geen kop: geen zoekveld en geen sluitkruisje', async () => {
    const m = maakMenuMetToolbarZoekveld();
    await m.el.open();
    assert.onwaar(!!m.el.querySelector('.cma-launcher__head'), 'geen kop in het menu');
    assert.onwaar(!!m.el.querySelector('.cma-launcher__search'), 'geen zoekveld in het menu');
    assert.onwaar(!!m.el.querySelector('.cma-launcher__close'), 'geen sluitkruisje');
    assert.waar(!!m.el.querySelector('.cma-launcher__body'), 'de lijst zelf blijft');
});

test('met search-source sluit het menu opnieuw door op de toolbarknop te drukken (toggle) en met Escape', async () => {
    const m = maakMenuMetToolbarZoekveld();
    await m.el.open();
    assert.waar(m.el.isOpen(), 'open');
    m.el.toggle();
    assert.onwaar(m.el.isOpen(), 'toggle sluit het menu');
    await m.el.open();
    m.win.document.dispatchEvent(new m.win.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    assert.onwaar(m.el.isOpen(), 'Escape sluit het menu (zonder term in het zoekveld)');
});

test('zonder search-source blijft de kop met zoekveld en sluitkruisje staan', async () => {
    const m = maakMenu();
    await m.el.open();
    assert.waar(!!m.el.querySelector('.cma-launcher__head') && !!m.el.querySelector('.cma-launcher__search') && !!m.el.querySelector('.cma-launcher__close'), 'kop, zoekveld en kruisje');
});

test('typen in het toolbar-zoekveld opent het menu en filtert op de term', async () => {
    const m = maakMenuMetToolbarZoekveld();
    assert.onwaar(m.el.isOpen(), 'menu dicht');
    m.typ('cache');
    await m.wacht();
    assert.waar(m.el.isOpen(), 'menu open na typen');
    assert.diepgelijk(m.zichtbaar(), ['Cache leegmaken']);
    assert.gelijk(m.veld.value, 'cache', 'de ingetypte term blijft staan');
});

test('verder typen in een open menu filtert opnieuw, een lege term toont alles', async () => {
    const m = maakMenuMetToolbarZoekveld();
    await m.el.open();
    m.typ('geb');
    assert.diepgelijk(m.zichtbaar(), ['Gebruikers']);
    m.typ('');
    assert.diepgelijk(m.zichtbaar(), ['Cache leegmaken', 'Gebruikers', 'Site-tool', 'Rapport 7']);
});

test('Escape wist eerst de term in het toolbar-zoekveld en sluit pas daarna het menu', async () => {
    const m = maakMenuMetToolbarZoekveld();
    m.typ('cache');
    await m.wacht();
    const esc = () => m.win.document.dispatchEvent(new m.win.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    esc();
    assert.gelijk(m.veld.value, '', 'term gewist');
    assert.waar(m.el.isOpen(), 'menu blijft open bij de eerste Escape');
    esc();
    assert.onwaar(m.el.isOpen(), 'tweede Escape sluit het menu');
});

test('een keuze maakt het toolbar-zoekveld leeg', async () => {
    const m = maakMenuMetToolbarZoekveld();
    m.typ('gebr');
    await m.wacht();
    m.el.querySelector('.cma-launcher__item:not([hidden])').click();
    assert.gelijk(m.veld.value, '', 'zoekveld leeg na een keuze');
    assert.onwaar(m.el.isOpen(), 'menu dicht na een keuze');
});

test('een site-tool met target R laadt in het tools-venster, zonder target opent hij in een nieuw tabblad', async () => {
    const m = maakMenu();
    m.win.fetch = () => Promise.resolve({ json: () => Promise.resolve({ success: true, groups: [{ type: 'folder', label: 'Site', children: [
        { type: 'item', label: 'In venster', href: '/beheer/sso.php', target: 'R' },
        { type: 'item', label: 'Nieuw tabblad', href: '/beheer/log.php' },
        { type: 'item', label: 'Extern met R', href: 'https://elders.example/x', target: 'R' }
    ] }] }) });
    await m.el.open();
    const inVenster = m.item('In venster');
    assert.onwaar(inVenster.hasAttribute('data-external'), 'target R: geen nieuw tabblad');
    assert.gelijk(inVenster.getAttribute('data-src'), '/beheer/sso.php', 'src voor het iframe');
    assert.waar(m.item('Nieuw tabblad').hasAttribute('data-external'), 'zonder target: nieuw tabblad zoals voorheen');
    assert.waar(m.item('Extern met R').hasAttribute('data-external'), 'een andere host blijft altijd een nieuw tabblad');
    inVenster.click();
    assert.gelijk(m.iframe.getAttribute('src'), '/beheer/sso.php', 'het iframe laadt de site-tool');
    assert.onwaar(m.el.isOpen(), 'menu dicht na een keuze');
});
