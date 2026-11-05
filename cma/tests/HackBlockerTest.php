<?php
/**
 * Tests for App\Library\HackBlocker — counting 404s on hack paths per IP and
 * blocking an IP that goes over the threshold within the window.
 *
 * Run with: php cma/tests/TestRunner.php HackBlockerTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\HackBlocker;

class HackBlockerTest extends TestCase
{
    private string $root;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/hackblocker_' . uniqid('', true);
    }

    public function tearDown(): void
    {
        foreach (['/data/ip_blocks.json', '/data/ip_block_history.json', '/.logs/404/hackhits.json'] as $file) {
            @unlink($this->root . $file);
        }
        foreach (['/data', '/.logs/404', '/.logs', ''] as $dir) {
            @rmdir($this->root . $dir);
        }
    }

    public function testBlocksOnTheHitAfterTheThreshold(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        for ($i = 0; $i < 10; $i++) {
            $this->assertNull($blocker->recordHit('1.2.3.4', '/wp-login.php', $now + $i, 10, 600, 3600));
        }
        $this->assertNull($blocker->blockedUntil('1.2.3.4', $now + 10));
        $this->assertSame($now + 10 + 3600, $blocker->recordHit('1.2.3.4', '/.env', $now + 10, 10, 600, 3600));
        $this->assertSame($now + 3610, $blocker->blockedUntil('1.2.3.4', $now + 11));
        $this->assertNull($blocker->blockedUntil('5.6.7.8', $now + 11));

        $blok = $blocker->blocks($now + 11)['1.2.3.4'];
        $this->assertSame(11, $blok['pogingen']);
        $this->assertSame('/.env', $blok['laatste']);
    }

    public function testRepeatedBlocksDoubleUntilTheCap(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        $duren = [];
        for ($ronde = 0; $ronde < 6; $ronde++) {
            $t = $now + $ronde * 16000;
            for ($i = 0; $i < 10; $i++) {
                $blocker->recordHit('1.2.3.4', '/x', $t + $i, 10, 600, 3600);
            }
            $duren[] = $blocker->recordHit('1.2.3.4', '/x', $t + 10, 10, 600, 3600) - ($t + 10);
        }
        $this->assertSame([3600, 7200, 14400, 28800, 57600, 115200], $duren);
    }

    public function testEscalationStopsAtAWeek(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        $duren = [];
        foreach ([0, 1000, 2000] as $offset) {
            for ($i = 0; $i <= 10; $i++) {
                $until = $blocker->recordHit('1.2.3.4', '/x', $now + $offset + $i, 10, 600, 400000);
            }
            $duren[] = $until - ($now + $offset + 10);
        }
        $this->assertSame([400000, 604800, 604800], $duren);
    }

    public function testBlockHistoryExpiresAfterAWeek(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        for ($i = 0; $i <= 10; $i++) {
            $blocker->recordHit('1.2.3.4', '/x', $now + $i, 10, 600, 3600);
        }
        $later = $now + 8 * 86400;
        for ($i = 0; $i < 10; $i++) {
            $blocker->recordHit('1.2.3.4', '/x', $later + $i, 10, 600, 3600);
        }
        $this->assertSame(3600, $blocker->recordHit('1.2.3.4', '/x', $later + 10, 10, 600, 3600) - ($later + 10));
    }

    public function testTheServerItselfIsExempt(): void
    {
        $server = ['SERVER_ADDR' => '10.0.0.5', 'LOCAL_ADDR' => '10.0.0.6'];
        $this->assertTrue(HackBlocker::isOwnServer('127.0.0.1', []));
        $this->assertTrue(HackBlocker::isOwnServer('::1', []));
        $this->assertTrue(HackBlocker::isOwnServer('10.0.0.5', $server));
        $this->assertTrue(HackBlocker::isOwnServer('10.0.0.6', $server));
        $this->assertFalse(HackBlocker::isOwnServer('10.0.0.7', $server));
        $this->assertFalse(HackBlocker::isOwnServer('10.0.0.5', []));
    }

    public function testExemptUserAgentPatterns(): void
    {
        $this->assertTrue(HackBlocker::isExemptUserAgent('sec-audit/1.2 (acme)', 'sec-audit*'));
        $this->assertTrue(HackBlocker::isExemptUserAgent('SEC-AUDIT', 'foo, sec-audit*'));
        $this->assertFalse(HackBlocker::isExemptUserAgent('Mozilla/5.0 sec-audit', 'sec-audit*'));
        $this->assertFalse(HackBlocker::isExemptUserAgent('sec-audit', ''));
        $this->assertFalse(HackBlocker::isExemptUserAgent('Mozilla/5.0', '*'));
        $this->assertFalse(HackBlocker::isExemptUserAgent('', 'sec-audit*'));
    }

    public function testHitsOutsideTheWindowDoNotCount(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        for ($i = 0; $i < 10; $i++) {
            $blocker->recordHit('1.2.3.4', '/wp-login.php', $now + $i, 10, 600, 3600);
        }
        // Eleven minutes later the first ten have dropped out of the window.
        $this->assertNull($blocker->recordHit('1.2.3.4', '/wp-login.php', $now + 660, 10, 600, 3600));
    }

    public function testIpsAreCountedSeparately(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        for ($i = 0; $i < 6; $i++) {
            $this->assertNull($blocker->recordHit('1.1.1.1', '/x', $now, 10, 600, 3600));
            $this->assertNull($blocker->recordHit('2.2.2.2', '/x', $now, 10, 600, 3600));
        }
    }

    public function testBlockManuallyBlocksForTheGivenMinutes(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = 1_800_000_000;
        $this->assertTrue($blocker->blockManually('9.9.9.9', 30, $now));
        $this->assertSame($now + 1800, $blocker->blockedUntil('9.9.9.9', $now + 1));
        $this->assertSame('handmatig geblokkeerd', $blocker->blocks($now + 1)['9.9.9.9']['laatste']);
        $this->assertTrue($blocker->unblock('9.9.9.9'));
    }

    public function testBlockManuallyRefusesBadInput(): void
    {
        $blocker = new HackBlocker($this->root);
        $this->assertFalse($blocker->blockManually('geen-ip', 30));
        $this->assertFalse($blocker->blockManually('', 30));
        $this->assertFalse($blocker->blockManually('9.9.9.9', 0));
        $this->assertSame([], $blocker->blocks());
    }

    public function testBlockExpiresAndCanBeLifted(): void
    {
        $blocker = new HackBlocker($this->root);
        $now = time();
        for ($i = 0; $i < 3; $i++) {
            $blocker->recordHit('1.2.3.4', '/x', $now, 2, 600, 3600);
        }
        $this->assertNull($blocker->blockedUntil('1.2.3.4', $now + 3601));
        $this->assertNotNull($blocker->blockedUntil('1.2.3.4', $now));
        $this->assertTrue($blocker->unblock('1.2.3.4'));
        $this->assertNull($blocker->blockedUntil('1.2.3.4', $now));
        $this->assertFalse($blocker->unblock('1.2.3.4'));
    }
}
