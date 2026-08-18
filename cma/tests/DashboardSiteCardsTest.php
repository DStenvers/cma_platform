<?php
/**
 * Tests for cma_dashboard_site_cards() in cma/dashboard.php
 *
 * WHY THIS EXISTS
 * `dashboard_cards_extra` is the contract between the platform and every site
 * that injects a status card into the dashboard (see documentation.php topic
 * "Dashboard-kaarten per site"). A site's app.php is written once against
 * these rules and then survives platform upgrades — so the rules are pinned:
 * malformed config never breaks the dashboard, endpoints stay same-origin,
 * and role filtering keeps admin-only cards away from regular users.
 *
 * The function is extracted from dashboard.php source (the page cannot be
 * required whole: it renders), same technique as DocumentationSiteTopicsTest.
 *
 * Run with: php cma/tests/TestRunner.php DashboardSiteCardsTest
 */

require_once __DIR__ . '/TestRunner.php';

class DashboardSiteCardsTest extends TestCase
{
    private static bool $loaded = false;

    private function loadFunction(): void
    {
        if (self::$loaded) {
            return;
        }
        $src = (string)file_get_contents(__DIR__ . '/../dashboard.php');
        $start = strpos($src, 'function cma_dashboard_site_cards(');
        $this->assertTrue($start !== false, 'cma_dashboard_site_cards() is gone from dashboard.php');
        // Kolom-0 afsluiting: het eerste "\n}" op regelbegin ná de functiekop.
        $end = strpos($src, "\n}", $start);
        $this->assertTrue($end !== false, 'cma_dashboard_site_cards() is not brace-terminated at column 0');
        eval(substr($src, $start, $end - $start + 2));
        self::$loaded = true;
    }

    private function cards($config, bool $isAdmin = false, bool $isDeveloper = false): array
    {
        $this->loadFunction();
        return cma_dashboard_site_cards($config, $isAdmin, $isDeveloper);
    }

    public function testNonArrayConfigYieldsNothing(): void
    {
        $this->assertEquals([], $this->cards('niet eens een array'));
        $this->assertEquals([], $this->cards(null));
    }

    public function testAnEntryWithoutTitleOrEndpointIsDropped(): void
    {
        $this->assertEquals([], $this->cards([
            ['title' => 'Zonder endpoint'],
            ['endpoint' => '/zonder/titel.php'],
            'geen array',
        ]));
    }

    public function testEndpointsStaySameOrigin(): void
    {
        $this->assertEquals([], $this->cards([
            ['title' => 'Protocol-relatief', 'endpoint' => '//evil.example/x.php'],
            ['title' => 'Absoluut extern', 'endpoint' => 'https://evil.example/x.php'],
            ['title' => 'Relatief pad', 'endpoint' => 'tools/x.php'],
        ]));
        $ok = $this->cards([['title' => 'Goed', 'endpoint' => '/tools/x.php']]);
        $this->assertEquals(1, count($ok));
        $this->assertEquals('/tools/x.php', $ok[0]['endpoint']);
    }

    public function testAdminCardsAreFilteredForRegularUsers(): void
    {
        $config = [['title' => 'Beheer', 'endpoint' => '/tools/x.php', 'roles' => 'admin']];
        $this->assertEquals([], $this->cards($config, false, false));
        $this->assertEquals(1, count($this->cards($config, true, false)));
        $this->assertEquals(1, count($this->cards($config, false, true)));
    }

    public function testDefaultsAreApplied(): void
    {
        $card = $this->cards([['title' => 'Kaal', 'endpoint' => '/tools/x.php']])[0];
        $this->assertEquals('lnr-chart-bars', $card['icon']);
        $this->assertEquals('', $card['link']);
        // roles default 'all': zichtbaar zonder admin, dus de kaart is er al.
    }

    public function testAnExternalHeaderLinkIsDroppedButTheCardStays(): void
    {
        $card = $this->cards([[
            'title' => 'Kaart', 'endpoint' => '/tools/x.php',
            'link' => 'https://evil.example/details',
        ]])[0];
        $this->assertEquals('', $card['link']);
        $binnen = $this->cards([[
            'title' => 'Kaart', 'endpoint' => '/tools/x.php', 'link' => '/tools/stats.php',
        ]])[0];
        $this->assertEquals('/tools/stats.php', $binnen['link']);
    }
}
