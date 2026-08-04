/**
 * Bewijs voor "staat dit al open?".
 *
 * Er stonden drie mechanismen op deze vraag, waarvan twee met een klok: een map
 * van tijdstippen per formulier+record (1500 ms) en een _lastUrl op het
 * functie-object (600 ms). Beide onthielden iets dat de DOM al weet, en beide
 * hadden dezelfde fout: binnen het tijdvenster kon je een net gesloten scherm
 * niet heropenen.
 *
 * lib_IsOpenUrl vraagt het aan de DOM. Deze tests leggen dat gedrag vast,
 * inclusief het heropenen dat vroeger werd geslikt.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

/** Knip lib_UrlAbsoluut + lib_IsOpenUrl uit de echte bron. */
function haalHelpersUitBron() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('function lib_UrlAbsoluut(');
    const eind = src.indexOf('function lib_OpenPopupCentered(');
    if (start === -1 || eind === -1 || eind < start) {
        throw new Error('Kon lib_IsOpenUrl niet in library.js vinden');
    }
    // lib_IsOpenUrl vraagt via lib_alertbox_getbody_doc_element() welk document de
    // vensters draagt. Die functie hoort bij de rest van library.js; hier staat een
    // vervanger die simpelweg dít document teruggeeft.
    return `
        function lib_alertbox_getbody_doc_element() { return document; }
    ` + src.slice(start, eind);
}

/** Document met de vensterlaag erin, plus optioneel openstaande vensters/panelen. */
function maakPagina({ vensterUrls = [], paneelUrls = [] } = {}) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/main.php' });
    const win = dom.window;
    const doc = win.document;

    vensterUrls.forEach((url, i) => {
        const houder = doc.createElement('div');
        houder.id = '__lib_win' + (i + 1);
        houder.innerHTML = '<iframe id="__lib_win_iframe_' + (i + 1) + '" src="' + url + '"></iframe>';
        doc.body.appendChild(houder);
    });
    if (paneelUrls.length) {
        win.lib_sidepanel_stack = paneelUrls.map(url => ({ url }));
    }

    const script = doc.createElement('script');
    script.textContent = haalHelpersUitBron();
    doc.body.appendChild(script);
    return { win, doc };
}

suite('Staat dit scherm al open');

test('niets open: het antwoord is nee', () => {
    const { win } = maakPagina();
    assert.onwaar(win.lib_IsOpenUrl('form.php?form=opleidingen&id=5'));
});

test('een leeg adres is nooit open', () => {
    const { win } = maakPagina({ vensterUrls: ['form.php?form=a&id=1'] });
    assert.onwaar(win.lib_IsOpenUrl(''));
    assert.onwaar(win.lib_IsOpenUrl(null));
});

test('een openstaand venster wordt herkend', () => {
    const { win } = maakPagina({ vensterUrls: ['form.php?form=opleidingen&id=5'] });
    assert.waar(win.lib_IsOpenUrl('form.php?form=opleidingen&id=5'));
});

test('een ánder record is niet hetzelfde scherm', () => {
    const { win } = maakPagina({ vensterUrls: ['form.php?form=opleidingen&id=5'] });
    assert.onwaar(win.lib_IsOpenUrl('form.php?form=opleidingen&id=6'));
});

test('relatief en absoluut adres zijn hetzelfde scherm', () => {
    const { win } = maakPagina({ vensterUrls: ['form.php?form=opleidingen&id=5'] });
    assert.waar(win.lib_IsOpenUrl('/cma/form.php?form=opleidingen&id=5'),
        'hetzelfde adres, andere schrijfwijze');
});

test('een zijpaneel telt net zo goed als een venster', () => {
    const { win } = maakPagina({ paneelUrls: ['form.php?form=deelnemers&id=9'] });
    assert.waar(win.lib_IsOpenUrl('form.php?form=deelnemers&id=9'));
});

test('niet alleen het bovenste paneel telt, de hele stapel telt', () => {
    const { win } = maakPagina({
        paneelUrls: ['form.php?form=a&id=1', 'form.php?form=b&id=2', 'form.php?form=c&id=3']
    });
    assert.waar(win.lib_IsOpenUrl('form.php?form=a&id=1'), 'onderop de stapel');
    assert.waar(win.lib_IsOpenUrl('form.php?form=c&id=3'), 'bovenop de stapel');
});

test('een gesloten venster telt niet meer mee', () => {
    const { win, doc } = maakPagina({ vensterUrls: ['form.php?form=opleidingen&id=5'] });
    assert.waar(win.lib_IsOpenUrl('form.php?form=opleidingen&id=5'));
    doc.getElementById('__lib_win1').remove();
    assert.onwaar(win.lib_IsOpenUrl('form.php?form=opleidingen&id=5'),
        'direct na sluiten moet heropenen kunnen — dat slikte het tijdklokje');
});
