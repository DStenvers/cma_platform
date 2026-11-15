/**
 * Een pagina laden (main.js, loadPage): de huidige pagina blijft zichtbaar tot het antwoord binnen is.
 *
 * Gemeld: "een formulier openen is flaky: titelbalk, lange witte pagina, titelbalk weg, dan het formulier". De huidige pagina werd bij de klik direct gecachet
 * (en dus uit beeld gehaald), waardoor het content-gebied wit bleef gedurende de hele aanvraag. Nu wordt hij pas gecachet als de nieuwe inhoud er is; tot die tijd staat hij
 * er (na 2 seconden gedimd, .loading-delayed).
 *
 * loadPage is een grote asynchrone functie met veel pagina-globals; hier wordt de volgorde van de stappen in de echte broncode vastgelegd.
 */

const fs = require('fs');
const path = require('path');

const MAIN = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'main.js'), 'utf8');
const start = MAIN.indexOf('window.loadPage = async function(page, updateHistory)');
const einde = MAIN.indexOf('window.loadPagePost = function', start);
const LOAD = MAIN.slice(start, einde);

suite('loadPage: de huidige pagina blijft staan tot het antwoord er is');

test('de huidige pagina wordt pas gecachet nadat het antwoord binnen is', () => {
    assert.waar(start > -1 && einde > start, 'loadPage staat in main.js');
    const fetchPos = LOAD.indexOf('fetch(url');
    // De eerste cacheCurrentPage in het cache-miss-pad is die van de externe URL (iframe); de gewone ligt in .then, na de fetch.
    const inThen = LOAD.indexOf('cacheCurrentPage(currentPage)', LOAD.indexOf('.then(html =>'));
    assert.waar(fetchPos > -1 && inThen > fetchPos, 'cachen gebeurt in .then, ná fetch(url)');
    const voorFetch = LOAD.slice(LOAD.indexOf('Cache MISS'), fetchPos);
    const aantalVoorFetch = (voorFetch.match(/cacheCurrentPage\(currentPage\)/g) || []).length;
    assert.gelijk(aantalVoorFetch, 1, 'vóór de fetch alleen in het pad van een externe URL (iframe)');
    assert.waar(voorFetch.indexOf('isExternalUrl(page)') < voorFetch.indexOf('cacheCurrentPage(currentPage)'), 'en dat staat binnen het isExternalUrl-blok');
});

test('het menu wijst meteen aan waar je heen gaat', () => {
    const voorFetch = LOAD.slice(LOAD.indexOf('Cache MISS'), LOAD.indexOf('fetch(url'));
    assert.waar(voorFetch.indexOf('setActiveMenuItem(page)') > -1, 'setActiveMenuItem bij de klik, niet pas na het laden');
});

test('een fout zet de oude pagina netjes weg voordat de foutmelding de inhoud vervangt', () => {
    const naCatch = LOAD.slice(LOAD.indexOf('.catch(error =>'));
    assert.waar(naCatch.indexOf('cacheCurrentPage(currentPage)') > -1 && naCatch.indexOf('cacheCurrentPage(currentPage)') < naCatch.indexOf('contentArea.innerHTML'), 'cachen vóór de foutmelding');
});
