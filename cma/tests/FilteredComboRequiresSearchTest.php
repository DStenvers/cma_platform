<?php
/**
 * A filtered combo on a large table (requires_search) must keep the label of its selected value when the filtered options are reloaded. The server returns no options for such a
 * combo, and replacing the options with that empty list used to drop the label, so the field showed the bare id (rooster > Blok: "48" instead of the block name). Runs the real
 * CmaFormController.reloadFilteredCombo() with node against a fake combo and fetch (skipped without node).
 *
 *   php cma/tests/TestRunner.php FilteredComboRequiresSearchTest
 */
require_once __DIR__ . '/TestRunner.php';

class FilteredComboRequiresSearchTest extends TestCase
{
    public function testSelectedLabelSurvivesTheReload(): void
    {
        $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->assertTrue(true, 'node is not available: skipped');
            return;
        }
        $src = (string) realpath(__DIR__ . '/../assets/js/form-controller.js');
        $script = <<<'JS'
const fs = require('fs');
const code = fs.readFileSync(process.argv[2], 'utf8');
const start = code.indexOf('    async reloadFilteredCombo(');
const end = code.indexOf('    /**\n     * After form population, reload dependent combos');
if (start < 0 || end < 0) { console.log('FAIL method not found'); process.exit(0); }
const X = new Function('cmaLog', 'cmaGetRecordId', 'return class X {\n getCacheFormId(){return "f";} getFormParam(){return "form=f";}\n' + code.slice(start, end) + '\n}')({ error() {} }, () => '1');
const out = [];
const t = (n, ok) => out.push((ok ? 'ok ' : 'FAIL ') + n);
function fakeCombo(sel) {
    const c = { _opts: sel ? [{ value: '48', label: 'Communicatie en samenwerking', group: null }] : [], _sel: sel ? ['48'] : [], attrs: { 'ajax-url': '/cma/form_api.php?action=combo&form=f&field=blok&recordId=1' },
        get selectedOptions() { return this._opts.filter(o => this._sel.includes(o.value)); },
        get value() { return this._sel[0] || ''; }, set value(v) { this._sel = v ? [String(v)] : []; },
        setOptions(o) { this._opts = o.map(x => ({ value: String(x.value), label: x.label, group: x.group || null })); },
        getAttribute(n) { return this.attrs[n] ?? null; }, setAttribute(n, v) { this.attrs[n] = v; } };
    return c;
}
global.window = { location: { origin: 'http://x' } };
global.sessionStorage = { removeItem() {} };
global.URL = URL;
(async () => {
    // 1. opening a record: the selected option (with its label) is kept, the search follows the parent
    global.fetch = async () => ({ json: async () => ({ success: true, options: [], requires_search: true, table_count: 1499 }) });
    let x = new X(); let c = fakeCombo(true); x.comboElements = { blok: c };
    await x.reloadFilteredCombo('blok', 'fkOpleiding', '5', '48');
    t('the selected label survives', c.selectedOptions.length === 1 && c.selectedOptions[0].label === 'Communicatie en samenwerking');
    t('the search follows the parent value', /filterField=fkOpleiding/.test(c.attrs['ajax-url']) && /filterValue=5/.test(c.attrs['ajax-url']));
    // 2. the parent changes: the old choice no longer belongs, so it is cleared
    x = new X(); c = fakeCombo(true); x.comboElements = { blok: c };
    await x.reloadFilteredCombo('blok', 'fkOpleiding', '7');
    t('a changed parent clears the value', c.value === '' && c._opts.length === 0 && /filterValue=7/.test(c.attrs['ajax-url']));
    // 3. a small table: options come back and replace the list, as before
    global.fetch = async () => ({ json: async () => ({ success: true, options: [{ id: 1, text: 'Een' }, { id: 48, text: 'Blok 48' }] }) });
    x = new X(); c = fakeCombo(false); x.comboElements = { blok: c };
    await x.reloadFilteredCombo('blok', 'fkOpleiding', '5', '48');
    t('small tables still list their options and keep a value that is in them', c._opts.length === 2 && c.value === '48');
    console.log(out.join('\n'));
})();
JS;
        $tmp = tempnam(sys_get_temp_dir(), 'fcr') . '.js';
        file_put_contents($tmp, $script);
        $res = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($src) . ' 2>&1');
        @unlink($tmp);
        foreach (array_filter(explode("\n", trim($res))) as $line) {
            $this->assertTrue(strpos($line, 'ok ') === 0, $line);
        }
        $this->assertTrue(substr_count($res, 'ok ') >= 4, 'all cases ran: ' . $res);
    }
}
