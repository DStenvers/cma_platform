<?php
/**
 * Tests voor de site-documentatie-haak in cma/tools/documentation.php.
 *
 * Een consumer-site kan eigen hoofdstukken meeleveren (doc/documentation.json +
 * doc/chapters/*.html, gelezen door src/includes/site_documentation.inc). Zijn die er,
 * dan splitst de tree in een CMA- en een Front-end-tak; zijn ze er niet, dan blijft de
 * tool exact zoals hij was. Dat "zoals hij was" is het belangrijkste wat hier bewaakt
 * wordt: verreweg de meeste sites hebben geen eigen documentatie.
 *
 * De functies onder test zijn puur (geen I/O), maar staan in een tool die een bootstrap
 * en een admin-sessie vereist. Daarom worden ze hier uit het bestand geëxtraheerd in
 * plaats van het hele bestand te includen.
 *
 * Run with: php tests/TestRunner.php DocumentationSiteTopicsTest
 */

require_once __DIR__ . '/TestRunner.php';

class DocumentationSiteTopicsTest extends TestCase
{
    /**
     * Haal de drie pure functies uit documentation.php en evalueer ze, zonder de
     * bootstrap/admin-gate van de tool zelf te raken.
     */
    private static function loadFunctions(): void
    {
        static $geladen = false;
        if ($geladen) {
            return;
        }
        $src = (string)file_get_contents(__DIR__ . '/../tools/documentation.php');
        $code = '';
        foreach (['flatten_topics', 'cma_doc_split_tree', 'cma_doc_strip_reserved'] as $fn) {
            if (function_exists($fn)) {
                continue;
            }
            // Van "function <naam>(" tot de regel die enkel "}" bevat (kolom 0).
            $start = strpos($src, "\nfunction $fn(");
            if ($start === false) {
                throw new \RuntimeException("functie $fn niet gevonden in documentation.php");
            }
            $end = strpos($src, "\n}\n", $start);
            $code .= substr($src, $start, $end - $start + 3);
        }
        if ($code !== '') {
            eval($code);
        }
        $geladen = true;
    }

    private function platformTopics(): array
    {
        return [
            'overview' => ['label' => 'Overzicht', 'icon' => 'lnr-book', 'render' => 'render_doc_overview'],
            'dev' => [
                'label' => 'Voor ontwikkelaars',
                'icon'  => 'lnr-code',
                'children' => [
                    'testing' => ['label' => 'Tests', 'icon' => 'lnr-shield-check', 'render' => 'render_doc_testing'],
                ],
            ],
        ];
    }

    private function siteTopics(): array
    {
        return [
            'site' => [
                'label' => 'Deze site',
                'icon'  => 'lnr-home',
                'children' => [
                    'site_twig' => ['label' => 'Twig', 'icon' => 'lnr-file-empty', 'render' => 'site_doc_render'],
                ],
            ],
        ];
    }

    // ========================================================================
    // Zonder site-documentatie verandert er NIETS
    // ========================================================================

    public function testGeenSiteDocsLaatDeTreeOngemoeid(): void
    {
        self::loadFunctions();
        $topics = $this->platformTopics();
        $this->assertEquals($topics, cma_doc_split_tree($topics, []),
            'zonder site-hoofdstukken moet de tree identiek blijven');
    }

    public function testGeenSiteDocsGeenCmaOfFrontendTak(): void
    {
        self::loadFunctions();
        $out = cma_doc_split_tree($this->platformTopics(), []);
        $this->assertFalse(isset($out['cma']), 'geen CMA-tak zonder site-documentatie');
        $this->assertFalse(isset($out['frontend']), 'geen Front-end-tak zonder site-documentatie');
    }

    // ========================================================================
    // Met site-documentatie splitst de tree
    // ========================================================================

    public function testSiteDocsSplitstInCmaEnFrontend(): void
    {
        self::loadFunctions();
        $out = cma_doc_split_tree($this->platformTopics(), $this->siteTopics());
        $this->assertCount(2, $out, 'precies twee takken op hoofdniveau');
        $this->assertEquals('CMA', $out['cma']['label']);
        $this->assertEquals('Front-end', $out['frontend']['label']);
    }

    public function testBestaandeTopicsBlijvenOngewijzigdOnderCma(): void
    {
        self::loadFunctions();
        $topics = $this->platformTopics();
        $out = cma_doc_split_tree($topics, $this->siteTopics());
        $this->assertEquals($topics, $out['cma']['children'],
            'de CMA-tak bevat de oorspronkelijke tree, ongewijzigd');
    }

    public function testRoutingBlijftWerkenNaDeSplitsing(): void
    {
        self::loadFunctions();
        $out  = cma_doc_split_tree($this->platformTopics(), $this->siteTopics());
        $flat = flatten_topics($out);
        // De router kent alleen de vlakke slug-namespace; die moet na de extra
        // nestinglaag nog steeds elk topic bevatten, inclusief het default-topic.
        $this->assertTrue(isset($flat['overview']), 'overview blijft routeerbaar');
        $this->assertTrue(isset($flat['testing']), 'genest platform-topic blijft routeerbaar');
        $this->assertTrue(isset($flat['site_twig']), 'site-topic is routeerbaar');
        $this->assertEquals('site_doc_render', $flat['site_twig']['render']);
    }

    // ========================================================================
    // Slug-botsingen: het platform wint
    // ========================================================================

    public function testBotsendeSlugWordtGeschrapt(): void
    {
        self::loadFunctions();
        $reserved = flatten_topics($this->platformTopics());
        $site = [
            'site' => [
                'label' => 'Deze site', 'icon' => 'lnr-home',
                'children' => [
                    'testing'   => ['label' => 'Mijn tests', 'icon' => 'lnr-x', 'render' => 'site_doc_render'],
                    'site_twig' => ['label' => 'Twig',       'icon' => 'lnr-x', 'render' => 'site_doc_render'],
                ],
            ],
        ];
        $out = cma_doc_strip_reserved($site, $reserved);
        $this->assertFalse(isset($out['site']['children']['testing']),
            'een slug die het platform al gebruikt wordt geschrapt');
        $this->assertTrue(isset($out['site']['children']['site_twig']),
            'niet-botsende slugs blijven staan');
    }

    public function testLeegGewordenMapVerdwijnt(): void
    {
        self::loadFunctions();
        $reserved = flatten_topics($this->platformTopics());
        $site = [
            'site' => [
                'label' => 'Deze site', 'icon' => 'lnr-home',
                'children' => [
                    'testing' => ['label' => 'Mijn tests', 'icon' => 'lnr-x', 'render' => 'site_doc_render'],
                ],
            ],
        ];
        $out = cma_doc_strip_reserved($site, $reserved);
        $this->assertEmpty($out, 'een map die door schrapping leeg raakt blijft niet als lege folder staan');
    }

    public function testAllesGeschraptGeeftGeenSplitsing(): void
    {
        self::loadFunctions();
        // Realistische keten: alles botst -> niets over -> tree blijft plat.
        $reserved = flatten_topics($this->platformTopics());
        $site = ['site' => ['label' => 'x', 'icon' => 'y', 'children' => [
            'testing' => ['label' => 'x', 'icon' => 'y', 'render' => 'site_doc_render'],
        ]]];
        $out = cma_doc_split_tree($this->platformTopics(), cma_doc_strip_reserved($site, $reserved));
        $this->assertFalse(isset($out['frontend']),
            'geen lege Front-end-tak als er na schrapping niets overblijft');
    }
}
