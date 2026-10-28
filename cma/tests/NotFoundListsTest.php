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
