<?php
/**
 * Tests for App\Library\NotFoundLists — the hack list and the ignored-404 list
 * that keep scanners and known misses out of the 404 overview — and for the
 * digest counting them apart.
 *
 * Run with: php cma/tests/TestRunner.php NotFoundListsTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\NotFoundDigest;
use App\Library\NotFoundLists;

class NotFoundListsTest extends TestCase
{
    private string $file;

    public function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/notfound_lists_' . uniqid('', true) . '/data/notfound_lists.json';
    }

    public function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
        if (is_dir(dirname($this->file))) {
            rmdir(dirname($this->file));
            rmdir(dirname($this->file, 2));
        }
    }

    public function testDefaultsAreAddedOnceToAnAlmostEmptyList(): void
    {
        $lists = new NotFoundLists($this->file);
        $this->assertTrue($lists->canAddDefaults());
        $this->assertTrue($lists->addDefaults('test'));
        $this->assertSame(count(NotFoundLists::DEFAULT_HACK), count($lists->all()[NotFoundLists::HACK]));
        $this->assertFalse($lists->canAddDefaults());
        $this->assertFalse($lists->addDefaults('test'));
    }

    public function testMergeDefaultsAddsOnlyMissingPatternsAndIsIdempotent(): void
    {
        $lists = new NotFoundLists($this->file);
        foreach (['/a', '/b', '/c', '/wp-*'] as $p) {
            $lists->add(NotFoundLists::HACK, $p, 'oud');
        }
        $added = $lists->mergeDefaults('migratie');
        $this->assertSame(count(NotFoundLists::DEFAULT_HACK) - 1, $added);
        $hack = $lists->all()[NotFoundLists::HACK];
        $this->assertSame(count(NotFoundLists::DEFAULT_HACK) + 3, count($hack));
        $this->assertSame('oud', $hack[0]['door']);
        $this->assertSame(0, $lists->mergeDefaults('migratie'));
    }

    public function testDefaultsHaveNoPatternThatAnotherDefaultAlreadyCovers(): void
    {
        foreach (NotFoundLists::DEFAULT_HACK as $pattern) {
            $this->assertFalse(NotFoundLists::isCovered($pattern, NotFoundLists::DEFAULT_HACK), $pattern . ' valt al onder een ander standaardpatroon');
        }
        $this->assertSame(count(NotFoundLists::DEFAULT_HACK), count(array_unique(array_map('strtolower', NotFoundLists::DEFAULT_HACK))), 'dubbele standaardpatronen');
    }

    public function testIsCoveredRecognisesWildcardCover(): void
    {
        $this->assertTrue(NotFoundLists::isCovered('/wp-login.php', ['/wp-*']));
        $this->assertTrue(NotFoundLists::isCovered('/wp-admin*', ['/a', '/wp-*']));
        $this->assertTrue(NotFoundLists::isCovered('/vendor/phpunit*', ['/vendor/*']));
        $this->assertTrue(NotFoundLists::isCovered('*.php.bak', ['*.bak']));
        $this->assertFalse(NotFoundLists::isCovered('/wp-*', ['/wp-login.php']), 'de smalle dekt de brede niet');
        $this->assertFalse(NotFoundLists::isCovered('/wp-*', ['/wp-*']), 'hetzelfde patroon is niet "gedekt"');
        $this->assertFalse(NotFoundLists::isCovered('/wp/*', ['/wp-*']), 'een ander pad');
        $this->assertFalse(NotFoundLists::isCovered('/a*c', ['/a*b']));
    }

    public function testMergeSkipsDefaultsCoveredByWhatTheSiteAlreadyHas(): void
    {
        $lists = new NotFoundLists($this->file);
        $lists->add(NotFoundLists::HACK, '/wp-*', 'beheerder');
        $lists->add(NotFoundLists::HACK, '/.env*', 'beheerder');
        $lists->mergeDefaults('migratie');
        $patterns = array_column($lists->all()[NotFoundLists::HACK], 'patroon');
        foreach (['/wp-login.php', '/wp-admin*', '/wp-content*', '/wp-includes*'] as $dubbel) {
            $this->assertFalse(in_array($dubbel, $patterns, true), $dubbel . ' hoort er niet bij: /wp-* dekt het');
        }
        $this->assertSame(1, count(array_keys($patterns, '/wp-*', true)), '/wp-* blijft een keer staan');
        $this->assertTrue(in_array('/xmlrpc.php', $patterns, true));
        $this->assertSame(0, $lists->mergeDefaults('migratie'), 'tweede keer: niets te doen');
    }

    public function testDefaultsToAddOnEmptyListIsTheWholeDefaultList(): void
    {
        $this->assertSame(NotFoundLists::DEFAULT_HACK, NotFoundLists::defaultsToAdd([]));
    }

    public function testDefaultHackListStillBlocksWhatTheRemovedPatternsCovered(): void
    {
        $lists = new NotFoundLists($this->file);
        $lists->addDefaults('test');
        foreach (['/wp-login.php', '/wp-admin/', '/wp-content/uploads/x.php', '/wp-includes/js/a.js', '/a/b/.env', '/x.php.bak', '/web.config.bak', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php'] as $path) {
            $this->assertSame(NotFoundLists::HACK, $lists->classify($path), $path);
        }
    }

    public function testDefaultHackListLeavesLegitimatePathsAlone(): void
    {
        $lists = new NotFoundLists($this->file);
        $lists->mergeDefaults('test');
        foreach (['/', '/index.php', '/cma/dashboard', '/cma/ckeditor/ckeditor.js', '/opleiding/12/owasp-training',
                  '/.well-known/passkey-endpoints', '/pagina.php', '/zoeken/s=console', '/robots.txt'] as $path) {
            $this->assertNull($lists->classify($path), $path);
        }
        foreach (['/wp-login.php', '/.env', '/phpinfo.php', '/owa/auth.php', '/vendor/composer/x', '/a/.git/config',
                  '/backup.sql', '/dana-na/auth/url_default/login.cgi'] as $path) {
            $this->assertSame(NotFoundLists::HACK, $lists->classify($path), $path);
        }
    }

    public function testDefaultsAreRefusedWhenTheListHasMoreThanTwo(): void
    {
        $lists = new NotFoundLists($this->file);
        foreach (['/a', '/b', '/c'] as $p) {
            $lists->add(NotFoundLists::HACK, $p, 'test');
        }
        $this->assertFalse($lists->addDefaults('test'));
        $this->assertSame(3, count($lists->all()[NotFoundLists::HACK]));
    }

    public function testExactPatternMatchesWholePathCaseInsensitive(): void
    {
        $this->assertTrue(NotFoundLists::matches('/wp-login.php', '/WP-Login.php'));
        $this->assertFalse(NotFoundLists::matches('/wp-login.php', '/blog/wp-login.php'));
        $this->assertFalse(NotFoundLists::matches('/wp-login.php', '/wp-login.php.bak'));
    }

    public function testWildcardMatchesAnyRun(): void
    {
        $this->assertTrue(NotFoundLists::matches('*/wp-*', '/blog/wp-admin/setup.php'));
        $this->assertTrue(NotFoundLists::matches('*.env', '/.env'));
        $this->assertTrue(NotFoundLists::matches('*.env', '/api/.env'));
        $this->assertFalse(NotFoundLists::matches('*.env', '/.env.bak'));
    }

    public function testRegexCharactersInPatternAreLiteral(): void
    {
        $this->assertTrue(NotFoundLists::matches('/a+b(1).php', '/a+b(1).php'));
        $this->assertFalse(NotFoundLists::matches('/a.php', '/aXphp'));
    }

    public function testFullUrlPatternIsReducedToItsPath(): void
    {
        $this->assertSame('/xmlrpc.php', NotFoundLists::normalize(' https://site.nl/xmlrpc.php?x=1 '));
        $this->assertTrue(NotFoundLists::matches('https://site.nl/xmlrpc.php', '/xmlrpc.php'));
    }

    public function testPathOfDropsQueryAndHandlesEmpty(): void
    {
        $this->assertSame('/oud.html', NotFoundLists::pathOf('/oud.html?x=1'));
        $this->assertSame('/oud.html', NotFoundLists::pathOf('https://site.nl/oud.html'));
        $this->assertSame('(onbekend)', NotFoundLists::pathOf(''));
    }

    public function testAddClassifyRemoveRoundTrip(): void
    {
        $lists = new NotFoundLists($this->file);
        $this->assertNull($lists->classify('/wp-login.php'));

        $this->assertTrue($lists->add(NotFoundLists::HACK, '/wp-*', 'tester'));
        $this->assertTrue($lists->add(NotFoundLists::IGNORE, '/nieuwsbrief-2019.html', 'tester'));
        $this->assertSame(NotFoundLists::HACK, $lists->classify('/wp-login.php'));
        $this->assertSame(NotFoundLists::IGNORE, $lists->classify('/nieuwsbrief-2019.html'));
        $this->assertNull($lists->classify('/echt-kapot.html'));

        // Persisted: a fresh instance reads the same lists.
        $fresh = new NotFoundLists($this->file);
        $this->assertSame('/wp-*', $fresh->all()[NotFoundLists::HACK][0]['patroon']);
        $this->assertSame('tester', $fresh->all()[NotFoundLists::HACK][0]['door']);

        $this->assertTrue($fresh->remove(NotFoundLists::HACK, '/WP-*'));
        $this->assertNull($fresh->classify('/wp-login.php'));
        $this->assertFalse($fresh->remove(NotFoundLists::HACK, '/wp-*'));
    }

    public function testDuplicateAndUnknownListAreRefused(): void
    {
        $lists = new NotFoundLists($this->file);
        $this->assertTrue($lists->add(NotFoundLists::HACK, '/.env', 'tester'));
        $this->assertFalse($lists->add(NotFoundLists::HACK, '/.ENV', 'tester'));
        $this->assertFalse($lists->add('onzin', '/x', 'tester'));
        $this->assertFalse($lists->add(NotFoundLists::HACK, '   ', 'tester'));
    }

    public function testHackWinsWhenOnBothLists(): void
    {
        $lists = new NotFoundLists($this->file);
        $lists->add(NotFoundLists::IGNORE, '/admin*', 'tester');
        $lists->add(NotFoundLists::HACK, '/admin.php', 'tester');
        $this->assertSame(NotFoundLists::HACK, $lists->classify('/admin.php'));
    }

    public function testDigestCountsListedPathsApart(): void
    {
        $lists = new NotFoundLists($this->file);
        $lists->add(NotFoundLists::HACK, '*.env', 'tester');
        $lists->add(NotFoundLists::IGNORE, '/oude-folder.pdf', 'tester');
        $line = static fn (string $url, string $ua = 'Mozilla/5.0'): string => json_encode(
            ['ts' => '2026-10-28T10:00:00', 'url' => $url, 'referer' => '', 'ip' => '1.2.3.4', 'ua' => $ua, 'type' => 'not_found']
        );
        $summary = NotFoundDigest::summarize([
            $line('/.env', 'python-requests/2.31'),
            $line('/api/.env'),
            $line('/oude-folder.pdf'),
            $line('/echt.html'),
        ], $lists);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['hack']);
        $this->assertSame(1, $summary['genegeerd']);
        $this->assertSame(0, $summary['bots']); // a listed hack is not also a bot
        $this->assertSame(['/echt.html'], array_keys($summary['paths']));

        $body = NotFoundDigest::renderBody('2026-10-28', $summary);
        $this->assertStringContainsString('waarvan 2 hackpogingen en 1 genegeerd', $body);
        $this->assertStringContainsString('/echt.html', $body);
    }
}
