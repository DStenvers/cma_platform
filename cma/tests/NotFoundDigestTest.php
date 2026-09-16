<?php
/**
 * Tests for App\Library\NotFoundDigest::summarize() and renderBody() — the
 * pure half of the daily 404 mail. The send path (marker file, shutdown
 * function, Email) is file- and SMTP-bound and is not covered here.
 *
 * Run with: php cma/tests/TestRunner.php NotFoundDigestTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\NotFoundDigest;

class NotFoundDigestTest extends TestCase
{
    private function line(string $url, string $ua = 'Mozilla/5.0', string $referer = '', string $type = 'not_found'): string
    {
        return json_encode(['ts' => '2026-09-15T10:00:00', 'url' => $url, 'referer' => $referer, 'method' => 'GET', 'ip' => '1.2.3.4', 'ua' => $ua, 'type' => $type]);
    }

    public function testCountsPathsAndKeepsFirstReferer(): void
    {
        $summary = NotFoundDigest::summarize([
            $this->line('/oud.html?x=1', 'Mozilla/5.0', 'https://site.nl/start'),
            $this->line('/oud.html', 'Mozilla/5.0', 'https://site.nl/nieuws'),
            $this->line('/plaatje.png'),
        ]);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, $summary['bots']);
        $this->assertSame(['/oud.html', '/plaatje.png'], array_keys($summary['paths']));
        $this->assertSame(2, $summary['paths']['/oud.html']['count']);
        $this->assertSame('https://site.nl/start', $summary['paths']['/oud.html']['referer']);
        $this->assertSame('', $summary['paths']['/plaatje.png']['referer']);
    }

    public function testBotsAreCountedButNotListed(): void
    {
        $summary = NotFoundDigest::summarize([
            $this->line('/wp-login.php', 'Mozilla/5.0 (compatible; Googlebot/2.1)'),
            $this->line('/.env', 'python-requests/2.31'),
            $this->line('/echt.html'),
        ]);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['bots']);
        $this->assertSame(['/echt.html'], array_keys($summary['paths']));
    }

    public function testSkipsIconRedirectsAndGarbage(): void
    {
        $summary = NotFoundDigest::summarize([
            $this->line('/assets/icons/x.svg', 'Mozilla/5.0', '', 'icon_redirect'),
            'not json at all',
            '',
        ]);
        $this->assertSame(0, $summary['total']);
        $this->assertSame([], $summary['paths']);
    }

    public function testBodyListsPathsAndEscapes(): void
    {
        $summary = NotFoundDigest::summarize([
            $this->line('/<script>', 'Mozilla/5.0', 'https://elders.nl/?a=1&b=2'),
        ]);
        $body = NotFoundDigest::renderBody('2026-09-15', $summary);
        $this->assertStringContainsString('/&lt;script&gt;', $body);
        $this->assertStringContainsString('a=1&amp;b=2', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function testBodyWithOnlyBotsSaysSo(): void
    {
        $summary = NotFoundDigest::summarize([$this->line('/x', 'bingbot/2.0')]);
        $body = NotFoundDigest::renderBody('2026-09-15', $summary);
        $this->assertStringContainsString('waarvan 1 door zoekmachines', $body);
        $this->assertStringContainsString('Geen misses van bezoekers', $body);
    }
}
