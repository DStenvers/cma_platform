/**
 * Een actief menu-item behoudt zijn witte titel bij hover.
 *
 * De hoverregel van .cma-menu-group-header kleurt icoon en titel blauw (#077ab2). Een los menu-item (single-item) is tegelijk
 * header en menu-item en kan actief zijn, met een paarse achtergrond: blauw op paars is slecht leesbaar. De regel
 * ".cma-menu-item.active:hover .cma-menu-group-title" houdt de titel dan wit.
 *
 * :hover is in jsdom niet na te bootsen; deze test bewaakt dus de regel zelf en dat hij zwaarder weegt dan de hoverregel.
 */
const fs = require('fs');
const path = require('path');

const CSS = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'css', 'main.css'), 'utf8');

/** Specificiteit (ids, klassen/pseudoklassen, elementen) van een selector zonder commas. */
function specificiteit(sel) {
    const klassen = (sel.match(/\.[\w-]+|:(?!:)[\w-]+|\[[^\]]+\]/g) || []).length;
    const ids = (sel.match(/#[\w-]+/g) || []).length;
    const elementen = (sel.replace(/\.[\w-]+|:+[\w-]+|\[[^\]]+\]|#[\w-]+/g, ' ').match(/(^|[\s>+~])[a-z][\w-]*/gi) || []).length;
    return ids * 10000 + klassen * 100 + elementen;
}

suite('Menu: actief item bij hover');

test('de regel bestaat en maakt de titel wit', () => {
    const m = /\.cma-menu-item\.active:hover \.cma-menu-group-title\s*\{([^}]*)\}/.exec(CSS);
    assert.waar(!!m, 'regel .cma-menu-item.active:hover .cma-menu-group-title ontbreekt');
    assert.waar(/color:\s*#ffffff\s*;/i.test(m[1]), 'de titel is niet #ffffff');
});

test('hij weegt zwaarder dan de blauwe hoverregel van de header', () => {
    assert.waar(specificiteit('.cma-menu-item.active:hover .cma-menu-group-title') > specificiteit('.cma-menu-group-header:hover .cma-menu-group-title'),
        'de witte regel wint niet van de blauwe');
});
