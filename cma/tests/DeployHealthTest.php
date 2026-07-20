<?php
/**
 * Tests for App\Library\DeployHealth
 *
 * Run with: php cma/tests/TestRunner.php DeployHealthTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\DeployHealth;

class DeployHealthTest extends TestCase
{
    private string $root = '';

    private function makeSite(bool $withCma): string
    {
        // Unique temp site root per call. No Date.now reliance — uniqid is fine here.
        $root = sys_get_temp_dir() . '/dh_' . uniqid('', true);
        @mkdir($root . '/logs', 0777, true);
        @mkdir($root . '/cma', 0777, true);
        if ($withCma) {
            foreach (DeployHealth::CMA_PROBES as $rel) {
                $abs = $root . '/' . $rel;
                @mkdir(dirname($abs), 0777, true);
                file_put_contents($abs, "<?php\n");
            }
        }
        $this->root = $root;
        return $root;
    }

    private function cleanup(): void
    {
        if ($this->root === '' || !is_dir($this->root)) { return; }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
        $this->root = '';
    }

    public function testSyncOkWhenAllProbesPresent(): void
    {
        $root = $this->makeSite(true);
        try {
            $res = DeployHealth::cmaSyncCheck($root);
            $this->assertTrue($res['ok']);
            $this->assertIsArray($res['missing']);
            $this->assertCount(0, $res['missing']);

            $logged = file_get_contents($root . '/.logs/deploy/deploy.log');
            $this->assertStringContainsString('/cma/ sync OK', $logged);
        } finally {
            $this->cleanup();
        }
    }

    public function testSyncFailsAndListsMissingProbes(): void
    {
        $root = $this->makeSite(true);
        try {
            // Break the sync: remove two core entrypoints.
            unlink($root . '/cma/form.php');
            unlink($root . '/cma/main.php');

            $res = DeployHealth::cmaSyncCheck($root);
            $this->assertFalse($res['ok']);
            $this->assertCount(2, $res['missing']);
            $this->assertContains('cma/form.php', $res['missing']);
            $this->assertContains('cma/main.php', $res['missing']);

            $logged = file_get_contents($root . '/.logs/deploy/deploy.log');
            $this->assertStringContainsString('/cma/ sync FAILED', $logged);
            $this->assertStringContainsString('cma/form.php', $logged);
        } finally {
            $this->cleanup();
        }
    }

    public function testRespectsLogFileOverride(): void
    {
        $root = $this->makeSite(false); // no cma probes → all missing
        try {
            $custom = $root . '/logs/custom-deploy.log';
            $res = DeployHealth::cmaSyncCheck($root, ['log_file' => $custom]);
            $this->assertFalse($res['ok']);
            $this->assertCount(count(DeployHealth::CMA_PROBES), $res['missing']);
            $this->assertTrue(is_file($custom), 'health-check must honour the log_file override');
        } finally {
            $this->cleanup();
        }
    }

    // ------------------------------------------------------------------
    // Additional coverage: partial-missing, path normalisation, log-dir
    // auto-create, no-mail-when-no-recipient.
    // ------------------------------------------------------------------

    public function testPartialMissingListsOnlyAbsentProbes(): void
    {
        $root = $this->makeSite(true);
        try {
            // Remove exactly one probe — the rest stay present.
            unlink($root . '/cma/bootstrap.inc');

            $res = DeployHealth::cmaSyncCheck($root);
            $this->assertFalse($res['ok']);
            $this->assertCount(1, $res['missing']);
            $this->assertContains('cma/bootstrap.inc', $res['missing']);
        } finally {
            $this->cleanup();
        }
    }

    public function testTrailingSlashAndBackslashesAreNormalised(): void
    {
        $root = $this->makeSite(true);
        try {
            // Pass the root back-slashed and with a trailing separator — the
            // check normalises both and still resolves every probe.
            $weird = str_replace('/', '\\', $root) . '\\';
            $res = DeployHealth::cmaSyncCheck($weird);
            $this->assertTrue($res['ok'], 'normalised path must resolve the probes');
            $this->assertCount(0, $res['missing']);
        } finally {
            $this->cleanup();
        }
    }

    public function testLogDirectoryIsCreatedWhenMissing(): void
    {
        // Point the log at a not-yet-existing nested dir; cmaSyncCheck must
        // mkdir it rather than silently dropping the alert line.
        $root = $this->makeSite(true);
        try {
            $custom = $root . '/nested/deep/deploy.log';
            DeployHealth::cmaSyncCheck($root, ['log_file' => $custom]);
            $this->assertTrue(is_file($custom));
            $this->assertStringContainsString('sync OK', file_get_contents($custom));
        } finally {
            $this->cleanup();
        }
    }

    public function testFailureAlertIsNoOpWithoutRecipient(): void
    {
        // With no mail_to and no DEPLOY_HEALTH_EMAIL/ADMIN_EMAIL/MAIL_FROM env,
        // the alert path must silently no-op (no throw) and still return the
        // structured failure. Guard the env keys so the host config can't leak in.
        $saved = [];
        foreach (['DEPLOY_HEALTH_EMAIL', 'ADMIN_EMAIL', 'MAIL_FROM'] as $k) {
            $saved[$k] = $_ENV[$k] ?? null;
            unset($_ENV[$k]);
        }
        $root = $this->makeSite(false);
        try {
            $res = DeployHealth::cmaSyncCheck($root);
            $this->assertFalse($res['ok']);
            // deploy.log records the FAILED line but NOT an "alert e-mail verstuurd" line.
            $logged = file_get_contents($root . '/.logs/deploy/deploy.log');
            $this->assertStringContainsString('sync FAILED', $logged);
            $this->assertStringNotContainsString('alert e-mail verstuurd', $logged);
        } finally {
            $this->cleanup();
            foreach ($saved as $k => $v) {
                if ($v === null) { unset($_ENV[$k]); } else { $_ENV[$k] = $v; }
            }
        }
    }
}
