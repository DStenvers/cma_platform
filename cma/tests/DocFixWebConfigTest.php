<?php
/**
 * DocFixWebConfigTest.php — the doc-hub "Los op"-buttons that WRITE the
 * site-root web.config (cma/tools/documentation_fixes.inc).
 *
 * A broken web.config takes the whole IIS site down with a 500, so the
 * contract under test is narrow and strict: every fixer is idempotent, adds
 * exactly what its check looks for, leaves the rest of the file alone, and
 * cma_doc_apply_fix() rolls back rather than leave an unparseable file.
 *
 *   php tests/TestRunner.php DocFixWebConfigTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/tools/documentation_fixes.inc';

class DocFixWebConfigTest extends TestCase
{
    private string $tmpDir = '';

    public function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/docfix-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);
    }

    /**
     * The fixers edit XML with DOMDocument and verify the result with SimpleXML.
     * Skip (never silently pass) where those extensions are absent — the CLI PHP
     * on a dev workstation often lacks them; the IIS PHP the site runs on has them.
     */
    private function needsXml(): void
    {
        $this->requireExtensions('dom', 'simplexml');
    }

    public function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->tmpDir);
    }

    /** A minimal but realistic parent web.config with its own rewrite rule. */
    private function writeConfig(string $xml = ''): string
    {
        $xml = $xml !== '' ? $xml : <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Site friendly URL" stopProcessing="true">
                    <match url="^artikel/(.*)$" />
                    <action type="Rewrite" url="artikel.php?slug={R:1}" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
XML;
        $path = $this->tmpDir . '/web.config';
        file_put_contents($path, $xml);
        return $path;
    }

    private function apply(string $check, string $path): array
    {
        return cma_doc_apply_fix($check, $path);
    }

    private function xml(string $path): SimpleXMLElement
    {
        $x = simplexml_load_file($path);
        $this->assertTrue($x !== false, 'result must be parseable XML');
        return $x;
    }

    // ── every registered fixer resolves to a callable ────────────────────

    public function testEveryFixerIsCallable(): void
    {
        $fixers = cma_doc_fixers();
        $this->assertTrue(count($fixers) > 0);
        // documentation.php is admin-gated and renders on include, so the check
        // functions cannot be loaded here — assert their declaration statically.
        $docs = (string) file_get_contents(dirname(__DIR__) . '/tools/documentation.php');
        foreach ($fixers as $check => $spec) {
            $this->assertTrue(
                str_contains($docs, 'function ' . $check . '('),
                "fixer is keyed on {$check}, but documentation.php declares no such check"
            );
            $this->assertTrue(is_callable($spec['apply']), "fixer for {$check} must be callable");
            $this->assertTrue(($spec['label'] ?? '') !== '', "fixer for {$check} needs a button label");
        }
    }

    /**
     * Regression: documentation.php requires documentation_fixes.inc, but the
     * fragment branch (?fragment=1 — how the sidebar loads every topic) and the
     * search-index build both render topics and exit long before the bottom of
     * the file. With the require further down, opening any topic with a check
     * table fataled on "Call to undefined function cma_doc_fixers()", while a
     * full-page load of the same topic worked. The require must therefore come
     * before every early exit.
     */
    public function testFixesAreRequiredBeforeAnyEarlyExit(): void
    {
        $docs = (string) file_get_contents(dirname(__DIR__) . '/tools/documentation.php');

        $requirePos = strpos($docs, "require_once __DIR__ . '/documentation_fixes.inc'");
        $this->assertTrue($requirePos !== false, 'documentation.php must require documentation_fixes.inc');

        foreach ([
            "Request::query('fragment', '') === '1'" => 'the ?fragment=1 early exit',
            "\$GLOBALS['_docs_indexing'] = true"     => 'the search-index build',
            "Request::post('docfix', '') !== ''"     => 'the docfix endpoint',
        ] as $needle => $what) {
            $pos = strpos($docs, $needle);
            $this->assertTrue($pos !== false, "expected to find {$what} in documentation.php");
            $this->assertTrue($requirePos < $pos, "documentation_fixes.inc must be required BEFORE {$what}");
        }
    }

    /** The renderer must tolerate a missing fixes module instead of fatalling. */
    public function testCheckTableDegradesWithoutTheFixesModule(): void
    {
        $docs = (string) file_get_contents(dirname(__DIR__) . '/tools/documentation.php');
        $this->assertTrue(
            str_contains($docs, 'cma_doc_fixers_available() ? cma_doc_fixers() : []'),
            'the check table must guard the cma_doc_fixers() call'
        );
        $this->assertTrue(
            str_contains($docs, "is_file(__DIR__ . '/documentation_fixes.inc')"),
            'the require must be guarded so a half-synced deploy degrades instead of fatalling'
        );
    }

    public function testUnknownFixIsRejected(): void
    {
        $this->needsXml();
        $r = $this->apply('cma_doc_check_does_not_exist', $this->writeConfig());
        $this->assertFalse($r['ok']);
    }

    // ── skip /cma rule ───────────────────────────────────────────────────

    public function testSkipCmaRuleIsAddedFirst(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $r = $this->apply('cma_doc_check_parent_skip_cma', $path);
        $this->assertTrue($r['ok'], $r['message']);

        $rules = $this->xml($path)->xpath('//rewrite/rules/rule');
        $this->assertEquals('Skip /cma to child config', (string)$rules[0]['name'], 'must outrank the site rules');
        $this->assertEquals('^cma($|/)', (string)$rules[0]->match['url']);
        $this->assertEquals('None', (string)$rules[0]->action['type']);
        // the site's own rule survives
        $this->assertEquals('Site friendly URL', (string)$rules[1]['name']);
    }

    public function testSkipCmaRuleIsIdempotent(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $this->apply('cma_doc_check_parent_skip_cma', $path);
        $r = $this->apply('cma_doc_check_parent_skip_cma', $path);
        $this->assertTrue($r['ok']);
        $this->assertEquals(1, count($this->xml($path)->xpath("//rule[@name='Skip /cma to child config']")));
    }

    // ── outbound Content-Type rule ───────────────────────────────────────

    public function testDefaultContentTypeAddsRuleAndPrecondition(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $r = $this->apply('cma_doc_check_parent_default_content_type', $path);
        $this->assertTrue($r['ok'], $r['message']);

        $x = $this->xml($path);
        $this->assertEquals(1, count($x->xpath("//outboundRules/rule[@name='Default Content-Type to text/html']")));
        // The rule is inert without the preCondition it references.
        $this->assertEquals(1, count($x->xpath("//outboundRules/preConditions/preCondition[@name='ContentTypeMissing']")));
    }

    public function testDefaultContentTypeIsIdempotent(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $this->apply('cma_doc_check_parent_default_content_type', $path);
        $this->apply('cma_doc_check_parent_default_content_type', $path);
        $x = $this->xml($path);
        $this->assertEquals(1, count($x->xpath("//outboundRules/rule[@name='Default Content-Type to text/html']")));
        $this->assertEquals(1, count($x->xpath("//preCondition[@name='ContentTypeMissing']")));
    }

    // ── security headers ─────────────────────────────────────────────────

    public function testSecurityHeadersAreAdded(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $this->assertTrue($this->apply('cma_doc_check_parent_nosniff', $path)['ok']);
        $this->assertTrue($this->apply('cma_doc_check_parent_frame_options', $path)['ok']);

        $x = $this->xml($path);
        $nosniff = $x->xpath("//httpProtocol/customHeaders/add[@name='X-Content-Type-Options']");
        $frame   = $x->xpath("//httpProtocol/customHeaders/add[@name='X-Frame-Options']");
        $this->assertEquals('nosniff', (string)$nosniff[0]['value']);
        $this->assertEquals('SAMEORIGIN', (string)$frame[0]['value']);
    }

    public function testExistingHeaderValueIsNotOverwritten(): void
    {
        $this->needsXml();
        $path = $this->writeConfig(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <httpProtocol>
            <customHeaders>
                <add name="X-Frame-Options" value="DENY" />
            </customHeaders>
        </httpProtocol>
    </system.webServer>
</configuration>
XML);
        $r = $this->apply('cma_doc_check_parent_frame_options', $path);
        $this->assertTrue($r['ok']);
        $hits = $this->xml($path)->xpath("//customHeaders/add[@name='X-Frame-Options']");
        $this->assertEquals(1, count($hits));
        $this->assertEquals('DENY', (string)$hits[0]['value'], 'a stricter site choice must survive');
    }

    // ── hiddenSegments ───────────────────────────────────────────────────

    public function testHiddenSegmentsAddsOnlyMissingOnes(): void
    {
        $this->needsXml();
        $path = $this->writeConfig(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <security>
            <requestFiltering removeServerHeader="true">
                <hiddenSegments>
                    <add segment=".env" />
                </hiddenSegments>
            </requestFiltering>
        </security>
    </system.webServer>
</configuration>
XML);
        $r = $this->apply('cma_doc_check_parent_hidden_segments', $path);
        $this->assertTrue($r['ok'], $r['message']);

        $segments = [];
        foreach ($this->xml($path)->xpath('//hiddenSegments/add') as $n) { $segments[] = (string)$n['segment']; }
        sort($segments);
        $this->assertEquals(['.env', '.sessions', 'composer.json', 'composer.lock'], $segments);
        // pre-existing attribute on the parent element is untouched
        $rf = $this->xml($path)->xpath('//requestFiltering');
        $this->assertEquals('true', (string)$rf[0]['removeServerHeader']);
    }

    public function testHiddenSegmentsIsIdempotent(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $this->apply('cma_doc_check_parent_hidden_segments', $path);
        $r = $this->apply('cma_doc_check_parent_hidden_segments', $path);
        $this->assertTrue($r['ok']);
        $this->assertEquals(4, count($this->xml($path)->xpath('//hiddenSegments/add')));
    }

    // ── safety net ───────────────────────────────────────────────────────

    public function testMissingConfigIsReportedNotCreated(): void
    {
        $r = $this->apply('cma_doc_check_parent_nosniff', $this->tmpDir . '/web.config');
        $this->assertFalse($r['ok']);
        $this->assertFalse(file_exists($this->tmpDir . '/web.config'), 'must not conjure a web.config');
    }

    public function testMalformedConfigIsLeftAlone(): void
    {
        $this->needsXml();
        $path = $this->tmpDir . '/web.config';
        file_put_contents($path, '<configuration><system.webServer></configuration>');
        $before = file_get_contents($path);
        $r = $this->apply('cma_doc_check_parent_nosniff', $path);
        $this->assertFalse($r['ok']);
        $this->assertEquals($before, file_get_contents($path), 'a broken config must not be rewritten');
    }

    public function testSuccessfulFixLeavesABackup(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $before = file_get_contents($path);
        $this->assertTrue($this->apply('cma_doc_check_parent_nosniff', $path)['ok']);

        $backups = glob($this->tmpDir . '/web.config.bak-*');
        $this->assertEquals(1, count($backups));
        $this->assertEquals($before, file_get_contents($backups[0]), 'backup holds the pre-fix content');
    }

    public function testUnrelatedConfigContentSurvives(): void
    {
        $this->needsXml();
        $path = $this->writeConfig();
        $this->apply('cma_doc_check_parent_skip_cma', $path);
        $this->apply('cma_doc_check_parent_nosniff', $path);
        $this->apply('cma_doc_check_parent_hidden_segments', $path);

        $x = $this->xml($path);
        $site = $x->xpath("//rule[@name='Site friendly URL']");
        $this->assertEquals(1, count($site), "the site's own rewrite rule must survive every fix");
        $this->assertEquals('artikel.php?slug={R:1}', (string)$site[0]->action['url']);
    }
}
