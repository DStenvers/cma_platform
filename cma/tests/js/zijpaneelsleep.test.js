/**
 * zijpaneelsleep — een klik op de paneelkop is geen sleep.
 *
 * WAAROM DEZE TEST ER IS. Dit was de oorzaak van "waarom opent het loginsformulier
 * als popup terwijl mijn voorkeur zijpaneel is?". Het losmaken gebeurde op mousedown,
 * nog vóór enige beweging, en de stand werd bij elke mouseup in localStorage bewaard
 * — per formulier. Eén per ongeluk aangeraakte paneelkop maakte dat ene formulier dus
 * voorgoed een zwevend venster, terwijl alle andere formulieren rechts vastgeplakt
 * bleven.
 *
 * Er was niets aan te zien: de voorkeur stond op zijpaneel en er wérd ook een
 * zijpaneel geopend. Alleen de onthouden stand was anders.
 *
 * Wat hier wordt vastgelegd: onder de sleepdrempel verandert er niets en wordt er
 * niets bewaard; erboven wel.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBRARY = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

/**
 * Bouwt een paneel met kop in een echte DOM en hangt de sleeplogica eraan door
 * lib_sidepanel_maakVerstelbaar uit de bron te draaien.
 */
function paneelMetSleep(bewaardeStand, alOpgeschoond, extraSleutels) {
    const src = fs.readFileSync(LIBRARY, 'utf8');
    const dom = new JSDOM(`<!doctype html><html><body>
        <div id="p" class="lib_sidepanel_container" style="z-index:1000">
            <div class="lib_sidepanel_header"><div class="lib_sidepanel_title">Logins</div></div>
        </div></body></html>`, { pretendToBeVisual: true, url: 'https://test-mijn.rino.nl/cma/' });

    const win = dom.window;
    const opslag = {};
    if (bewaardeStand) { opslag['cma_sidepanel_form:logins'] = JSON.stringify(bewaardeStand); }
    if (alOpgeschoond) { opslag['cma_sidepanel_opgeschoond'] = '2026-09-losgeklikte-panelen'; }
    Object.assign(opslag, extraSleutels || {});
    // Een eigen opslag, zodat de test kan zien wat er onthouden wordt.
    Object.defineProperty(win, 'localStorage', {
        configurable: true,
        value: {
            get length() { return Object.keys(opslag).length; },
            key: (i) => Object.keys(opslag)[i] ?? null,
            getItem: (k) => (k in opslag ? opslag[k] : null),
            setItem: (k, v) => { opslag[k] = v; },
            removeItem: (k) => { delete opslag[k]; }
        }
    });

    const panel = win.document.getElementById('p');
    panel.getBoundingClientRect = () => ({ width: 800, height: 600, left: 400, top: 50, right: 1200, bottom: 650 });

    // De drie functies die we nodig hebben, uit de bron geknipt.
    const stukken = ['lib_sidepanel_opschonenEenmalig', 'lib_sidepanel_leesStand', 'lib_sidepanel_bewaarStand', 'lib_sidepanel_maakVerstelbaar']
        .map((naam) => {
            const start = src.indexOf('function ' + naam + '(');
            if (start === -1) { throw new Error(naam + ' niet gevonden'); }
            const eind = src.indexOf('\n}', start);
            return src.slice(start, eind + 2);
        }).join('\n');

    const maak = new win.Function('window', 'document', stukken +
        '\nreturn lib_sidepanel_maakVerstelbaar;');
    maak(win, win.document)(panel, 'form:logins', win.document);

    return { win, panel, opslag };
}

function muis(win, panel, type, x, y) {
    const doel = type === 'mousedown' ? panel.querySelector('.lib_sidepanel_title') : win.document;
    const ev = new win.MouseEvent(type, { clientX: x, clientY: y, button: 0, bubbles: true, cancelable: true });
    doel.dispatchEvent(ev);
}

suite('zijpaneelsleep');

test('een klik op de kop maakt het paneel niet los', () => {
    const { win, panel, opslag } = paneelMetSleep();
    muis(win, panel, 'mousedown', 500, 60);
    muis(win, panel, 'mouseup', 500, 60);

    assert.onwaar(panel.classList.contains('lib_sidepanel_zwevend'), 'blijft rechts vastgeplakt');
    assert.gelijk(opslag['cma_sidepanel_form:logins'], undefined, 'en er wordt geen stand onthouden');
});

test('een minieme trilling van de muis telt ook niet als slepen', () => {
    const { win, panel, opslag } = paneelMetSleep();
    muis(win, panel, 'mousedown', 500, 60);
    muis(win, panel, 'mousemove', 502, 61);
    muis(win, panel, 'mouseup', 502, 61);

    assert.onwaar(panel.classList.contains('lib_sidepanel_zwevend'));
    assert.gelijk(opslag['cma_sidepanel_form:logins'], undefined);
});

test('echt slepen maakt het paneel wel los en onthoudt dat', () => {
    const { win, panel, opslag } = paneelMetSleep();
    muis(win, panel, 'mousedown', 500, 60);
    muis(win, panel, 'mousemove', 560, 120);
    muis(win, panel, 'mouseup', 560, 120);

    assert.waar(panel.classList.contains('lib_sidepanel_zwevend'), 'nu zweeft hij');
    const stand = JSON.parse(opslag['cma_sidepanel_form:logins']);
    assert.waar(stand.zwevend, 'en dat wordt onthouden');
});

test('het losmaken vertrekt vanaf de plek van vóór de sleep', () => {
    const { win, panel } = paneelMetSleep();
    muis(win, panel, 'mousedown', 500, 60);
    muis(win, panel, 'mousemove', 510, 70);

    // start.l = 400, dx = 10 → 410. Niet 400 + iets van een tussenmeting.
    assert.gelijk(panel.style.left, '410px');
    assert.gelijk(panel.style.top, '60px', 'start.t = 50, dy = 10');
});

test('een onthouden zwevende stand komt terug', () => {
    // Met de opschoning al achter de rug: een stand die de gebruiker daarna zelf
    // heeft gezet, blijft gewoon werken.
    const { panel } = paneelMetSleep({ zwevend: true, l: 300, t: 100, b: 700, h: 500 }, true);
    assert.waar(panel.classList.contains('lib_sidepanel_zwevend'));
    assert.gelijk(panel.style.left, '300px');
});

test('de standen van vóór de sleepdrempel worden eenmalig opgeruimd', () => {
    // Dit is de reparatie voor browsers die het al te pakken hadden: die stand is
    // ontstaan door een klik, niet door een sleep, en is dus niets waard.
    const { panel, opslag } = paneelMetSleep({ zwevend: true, l: 300, t: 100, b: 700, h: 500 });

    assert.onwaar(panel.classList.contains('lib_sidepanel_zwevend'), 'komt vastgeplakt terug');
    assert.gelijk(opslag['cma_sidepanel_form:logins'], undefined, 'de oude stand is weg');
    assert.waar('cma_sidepanel_opgeschoond' in opslag, 'en dat wordt afgevinkt');
});

test('het opruimen gebeurt maar één keer', () => {
    const { win, panel, opslag } = paneelMetSleep({ zwevend: true, l: 300, t: 100, b: 700, h: 500 });
    assert.gelijk(opslag['cma_sidepanel_form:logins'], undefined);

    // De gebruiker sleept hem daarna zelf los; dat moet blijven staan.
    muis(win, panel, 'mousedown', 500, 60);
    muis(win, panel, 'mousemove', 560, 120);
    muis(win, panel, 'mouseup', 560, 120);
    assert.waar(JSON.parse(opslag['cma_sidepanel_form:logins']).zwevend);
});

test('andere voorkeuren blijven ongemoeid', () => {
    const { opslag } = paneelMetSleep({ zwevend: true, l: 300, t: 100 }, false, { cma_popup_style: 'sidepanel' });
    assert.gelijk(opslag['cma_popup_style'], 'sidepanel', 'de paneelvoorkeur is geen paneelstand');
});
