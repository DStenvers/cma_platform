<?php
/**
 * The JS error panel persists errors in localStorage, which the CMA (/cma/...) and the site's front-end SHARE (same origin).
 * An error from the CMA used to show up on the front-end and vice versa. Errors are now stored per scope: a CMA error is only
 * shown inside /cma, a site error only outside it.
 *
 *   php cma/tests/TestRunner.php ErrorHandlerScopeTest      (needs node; skipped without)
 */
require_once __DIR__ . '/TestRunner.php';

class ErrorHandlerScopeTest extends TestCase
{
    private function run(string $js): ?string
    {
        $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ehs');
        file_put_contents($tmp, $js);
        $out = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);
        return trim($out);
    }

    /** Load error-handler.js as a page at $path with the given shared localStorage; returns the handler's errors as JSON. */
    private function harness(string $path, string $storageJson, string $report = ''): string
    {
        $file = json_encode(dirname(__DIR__, 2) . '/library/assets/js/error-handler.js');
        return <<<JS
const fs = require('fs'), vm = require('vm');
const store = $storageJson;
const localStorage = { getItem: k => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); }, removeItem: k => { delete store[k]; } };
const noop = () => {};
const el = () => ({ style: {}, classList: { add: noop, remove: noop, toggle: noop }, appendChild: noop, addEventListener: noop, setAttribute: noop, querySelector: () => null, querySelectorAll: () => [] });
const window = { location: { pathname: '$path', hostname: 'example.com', href: 'http://example.com$path' }, localStorage, addEventListener: noop, CMA_DEBUG: false, console };
window.window = window; window.top = window;
const document = { readyState: 'complete', addEventListener: noop, createElement: el, body: el(), documentElement: el(), querySelector: () => null, querySelectorAll: () => [], getElementById: () => null };
const ctx = vm.createContext({ window, document, localStorage, navigator: { userAgent: 'x' }, location: window.location, console, setTimeout: noop, clearTimeout: noop, setInterval: noop, FormData: function () { this.append = noop; }, Date, JSON, Set, Math });
vm.runInContext(fs.readFileSync($file, 'utf8'), ctx);
const h = window.CmaErrorHandler;
$report
console.log(JSON.stringify({ scope: h.scope, errors: h.getErrors().map(e => e.message), store: Object.keys(store).sort() }));
JS;
    }

    public function testCmaErrorsAreOnlyShownInsideCma(): int
    {
        $now = time() * 1000;
        $stored = json_encode([
            'cma_v2_js_errors_cma' => json_encode(['errors' => [['message' => 'CMA-fout', 'timestamp' => $now]]]),
            'cma_v2_js_errors_site' => json_encode(['errors' => [['message' => 'Site-fout', 'timestamp' => $now]]]),
            'cma_v2_js_errors' => json_encode(['errors' => [['message' => 'Oude gedeelde lijst', 'timestamp' => $now]]]),
        ]);
        $cma = $this->run($this->harness('/cma/main.php', $stored));
        if ($cma === null) {
            $this->assertTrue(true, 'node is not available: skipped');
            return 0;
        }
        $a = json_decode($cma, true);
        $this->assertEquals('cma', $a['scope'], 'a page under /cma has scope cma');
        $this->assertEquals(['CMA-fout'], $a['errors'], 'inside /cma only the CMA error is shown');

        $site = json_decode((string) $this->run($this->harness('/index.php', $stored)), true);
        $this->assertEquals('site', $site['scope'], 'a front-end page has scope site');
        $this->assertEquals(['Site-fout'], $site['errors'], 'on the front-end only the site error is shown');
        $this->assertFalse(in_array('cma_v2_js_errors', $site['store'], true), 'the old shared list is dropped');
        return 0;
    }

    public function testNewErrorsAreStoredUnderTheirOwnScope(): int
    {
        $out = $this->run($this->harness('/cma/main.php', '{}', "h._addToPanel({ message: 'nieuw CMA', scope: 'cma' }); h._addToPanel({ message: 'iframe van de site', scope: 'site' });"));
        if ($out === null) {
            $this->assertTrue(true, 'node is not available: skipped');
            return 0;
        }
        $a = json_decode($out, true);
        $this->assertEquals(['nieuw CMA'], $a['errors'], 'an error of the other scope is not shown here');
        $this->assertEquals(['cma_v2_js_errors_cma', 'cma_v2_js_errors_site'], $a['store'], 'but it is stored for its own scope');
        return 0;
    }
}
