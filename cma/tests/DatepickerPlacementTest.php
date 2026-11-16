<?php
/**
 * lib-datepicker places its calendar inside the visible area: regular size below the field, else above, else a compact calendar below or above, else the compact calendar clamped
 * into the viewport. The top is never negative. The placement is a pure function (LibDatepicker.computePlacement), run here with node (skipped without).
 *
 *   php cma/tests/TestRunner.php DatepickerPlacementTest
 */
require_once __DIR__ . '/TestRunner.php';

class DatepickerPlacementTest extends TestCase
{
    public function testPlacementRules(): void
    {
        $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->assertTrue(true, 'node is not available: skipped');
            return;
        }
        $src = realpath(__DIR__ . '/../../library/webcomponents/lib-datepicker.js');
        $script = <<<'JS'
const fs = require('fs'), vm = require('vm');
const code = fs.readFileSync(process.argv[2], 'utf8');
let LibDatepicker = null;
const ctx = { HTMLElement: class {}, customElements: { get() { return undefined; }, define(name, cls) { LibDatepicker = cls; } }, window: {}, document: {}, console };
vm.createContext(ctx);
vm.runInContext(code, ctx);
const place = LibDatepicker.computePlacement;
const out = [];
const t = (name, got, want) => { const ok = JSON.stringify(got) === JSON.stringify(want); out.push((ok ? 'ok ' : 'FAIL ') + name + (ok ? '' : ' got ' + JSON.stringify(got))); };
// field at y 100-130, viewport 800: plenty of room below
t('below', place({ top: 100, bottom: 130 }, 800, 340, 250), { placement: 'below', compact: false, top: 129, maxHeight: null });
// field near the bottom: regular calendar fits above
t('above', place({ top: 600, bottom: 630 }, 700, 340, 250), { placement: 'above', compact: false, top: 261, maxHeight: null });
// the situation of the toets popup: field low in a short viewport, 300 above, 100 below: regular fits neither, compact fits above
t('compact above', place({ top: 260, bottom: 290 }, 400, 340, 250), { placement: 'above', compact: true, top: 11, maxHeight: null });
// compact fits below
t('compact below', place({ top: 40, bottom: 70 }, 340, 340, 250), { placement: 'below', compact: true, top: 69, maxHeight: null });
// nothing fits: viewport 200 high, compact 250: clamped to 0 and scrollable
const r = place({ top: 90, bottom: 120 }, 200, 340, 250);
t('nothing fits: top >= 0, scrollable', [r.top, r.maxHeight, r.compact], [0, 200, true]);
// nothing fits but compact is shorter than the viewport: clamped inside it
const r2 = place({ top: 200, bottom: 230 }, 260, 340, 250);
t('clamped inside the viewport', [r2.top >= 0, r2.top + 250 <= 260, r2.compact], [true, true, true]);
// the top is never negative, whatever the input
let neg = false;
for (let vh = 100; vh <= 900; vh += 50) for (let y = 0; y <= vh; y += 40) { const p = place({ top: y, bottom: y + 30 }, vh, 340, 250); if (p.top < 0) neg = true; }
t('top never negative', neg, false);
console.log(out.join('\n'));
JS;
        $tmp = tempnam(sys_get_temp_dir(), 'dpt') . '.js';
        file_put_contents($tmp, $script);
        $res = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg((string) $src) . ' 2>&1');
        @unlink($tmp);
        foreach (array_filter(explode("\n", trim($res))) as $line) {
            $this->assertTrue(strpos($line, 'ok ') === 0, $line);
        }
        $this->assertTrue(substr_count($res, 'ok ') >= 7, 'all placement cases ran: ' . $res);
    }

    public function testCompactStylesExist(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-datepicker.js');
        $this->assertTrue(strpos($js, '.datepicker-calendar.compact .datepicker-day') !== false, 'compact day cells are styled');
        $this->assertTrue(strpos($js, "calendar.classList.add('compact')") !== false, 'the compact class is applied by _positionCalendar');
    }
}
