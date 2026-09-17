<?php
/**
 * The settings screen writes the env file that /deploy.php reads with its own
 * dependency-free parser (templates/deploy.php.template). What
 * SystemSettings::envQuote() writes must come back identical through that
 * parser and through EnvFile::parse(): a Windows path, a value with spaces,
 * a value with a hash, a value with quotes.
 *
 *   php tests/TestRunner.php DeployEnvRoundTripTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SystemSettings.php';

use App\Library\EnvFile;
use Cma\Services\SystemSettings;

class DeployEnvRoundTripTest extends TestCase
{
    /** The template's inline reader, lifted from the shipped source and run against one file. */
    private function deployParse(string $file): array
    {
        $src = (string) file_get_contents(__DIR__ . '/../../templates/deploy.php.template');
        $start = strpos($src, "    foreach ((array)file(\$p, FILE_IGNORE_NEW_LINES");
        $end = strpos($src, "    \$envLoadedFrom = \$p;", $start);
        $this->assertTrue($start !== false && $end !== false, 'the reader loop is where the test expects it');
        $loop = substr($src, $start, $end - $start);
        $env = [];
        $p = $file;
        eval($loop);
        return $env;
    }

    public function testValuesSurviveBothReaders(): void
    {
        $cases = [
            'DEPLOY_RECYCLE_TOUCH' => 'C:\\inetpub\\wwwroot\\site\\web.config',
            'DEPLOY_PIPELINE'      => 'git pull --ff-only origin {branch}; composer update',
            'DEPLOY_RUN_TESTS'     => 'php tests/run.php # smoke',
            'DEPLOY_BRANCH'        => 'main',
        ];
        $content = '';
        foreach ($cases as $key => $value) {
            $n = SystemSettings::normalize([strtolower($key) => $value]);
            $this->assertSame([], $n['errors'], "$key accepted");
            $content = SystemSettings::applyEnvContent($content, $key, $n['values'][strtolower($key)]);
        }
        $file = sys_get_temp_dir() . '/deploy-roundtrip-' . bin2hex(random_bytes(4)) . '.env';
        file_put_contents($file, $content . "\n");
        try {
            $viaDeploy = $this->deployParse($file);
            $viaEnvFile = EnvFile::parse($content);
            foreach ($cases as $key => $value) {
                $this->assertSame($value, $viaDeploy[$key] ?? null, "$key through deploy.php's reader");
                $this->assertSame($value, $viaEnvFile[$key] ?? null, "$key through EnvFile");
            }
        } finally {
            @unlink($file);
        }
    }

    public function testAValueWithASingleQuoteStillRoundTripsThroughEnvFile(): void
    {
        $n = SystemSettings::normalize(['deploy_run_tests' => "php -r 'echo 1;'"]);
        $line = SystemSettings::applyEnvContent('', 'DEPLOY_RUN_TESTS', $n['values']['deploy_run_tests']);
        $this->assertSame("php -r 'echo 1;'", EnvFile::parse($line)['DEPLOY_RUN_TESTS']);
    }
}
