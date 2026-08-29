/**
 * Een readonly datum- of tijdveld hoort ook zijn kader kwijt te zijn.
 *
 * Gemeld op een datum/tijd-veld dat alleen-lezen stond: "de datum is keurig ontdaan van
 * lijntjes en duidelijk readonly, de tijd alleen niet". De datum was daar een gewone
 * <input class="datefield is-readonly"> — die krijgt zijn opmaak uit de stylesheet — en de
 * tijd een <lib-timepicker readonly>.
 *
 * De component haalde in readonly wél het kader van zijn BINNENKANT weg
 * (.timepicker-wrapper) maar niet dat van de host zelf, en juist die tekent het kader en
 * de witte achtergrond. Nagemeten in een browser: host border 1px #ddd op wit, terwijl de
 * binnenkant al kaal was. lib-datepicker had exact dezelfde omissie.
 *
 * border-color: transparent en niet border: none, zodat het veld even groot blijft als in
 * de bewerkbare stand — anders verspringt de regel bij het wisselen tussen lezen en
 * wijzigen. Dat is ook wat een readonly <input> in de CMA doet.
 *
 * De componenten worden niet nagebouwd: hun echte bron wordt in een jsdom-document
 * geladen en daarna wordt de gerenderde shadow-DOM bekeken.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const COMPONENTEN = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents');

/** Document met beide componenten geladen, elk één readonly en één bewerkbaar. */
function maakPagina() {
    const dom = new JSDOM(`<!doctype html><html><body>
        <lib-timepicker id="tijdLezen" readonly value="14:47"></lib-timepicker>
        <lib-timepicker id="tijdWijzigen" value="14:47"></lib-timepicker>
        <lib-datepicker id="datumLezen" readonly value="21-07-2026"></lib-datepicker>
        <lib-datepicker id="datumWijzigen" value="21-07-2026"></lib-datepicker>
    </body></html>`, { runScripts: 'dangerously', pretendToBeVisual: true });

    for (const bestand of ['lib-timepicker.js', 'lib-datepicker.js']) {
        const script = dom.window.document.createElement('script');
        script.textContent = fs.readFileSync(path.join(COMPONENTEN, bestand), 'utf8');
        dom.window.document.body.appendChild(script);
    }
    return dom.window;
}

/** De shadow-opmaak van een element, als tekst. */
function opmaakVan(win, id) {
    const el = win.document.getElementById(id);
    if (!el || !el.shadowRoot) {
        throw new Error('geen shadow-DOM op #' + id + ' — component niet opgewaardeerd?');
    }
    return el.shadowRoot.innerHTML;
}

/** Het blok achter een selector uit een stylesheet-tekst. */
function blokVan(css, selector) {
    const i = css.indexOf(selector);
    if (i === -1) return '';
    return css.slice(i, css.indexOf('}', i) + 1);
}

for (const [wat, lezen] of [['tijd', 'tijdLezen'], ['datum', 'datumLezen']]) {
    test('een readonly ' + wat + 'veld haalt ook het kader van de host weg', () => {
        const win = maakPagina();
        const blok = blokVan(opmaakVan(win, lezen), ':host([readonly]) {');

        assert.waar(blok !== '', 'er moet een :host([readonly])-regel zijn');
        assert.waar(blok.indexOf('border-color: transparent') > -1,
            'de rand van de host hoort doorzichtig te worden');
        assert.waar(blok.indexOf('background: transparent') > -1,
            'en de witte achtergrond eraf');
    });

    test('de rand blijft even dik, dus er verspringt niets', () => {
        // border: none zou het veld smaller maken; bij het wisselen tussen lezen en
        // wijzigen zou de hele regel dan verspringen.
        const win = maakPagina();
        const blok = blokVan(opmaakVan(win, lezen), ':host([readonly]) {');
        assert.gelijk(blok.indexOf('border: none'), -1,
            'de rand wordt doorzichtig gemaakt, niet weggehaald');
    });
}

test('een bewerkbaar veld houdt zijn kader gewoon', () => {
    const win = maakPagina();
    for (const id of ['tijdWijzigen', 'datumWijzigen']) {
        const basis = blokVan(opmaakVan(win, id), ':host {');
        assert.waar(basis.indexOf('border: 1px solid') > -1,
            id + ' hoort in de bewerkbare stand gewoon een rand te hebben');
    }
});
