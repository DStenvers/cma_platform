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
        foreach (['/data/ip_blocks.json', '/.logs/404/hackhits.json'] as $file) {
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
