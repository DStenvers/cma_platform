<?php
/**
 * DeployPlatformVersionTest.php — een deploy mag het platform niet stilletjes laten staan.
 *
 * Gemeld: op een consumer-site liep het platform niet mee met de deploys. deploy.php dóét
 * `composer update stenversonline/platform` standaard, maar er zijn genoeg manieren waarop
 * die stap er niet aan te pas komt (DEPLOY_COMPOSER_UPDATE=- of =install, ?nocomposer=Y,
 * een deploy.php van vóór die stap, of een update die faalt en terugvalt op install). In
 * al die gevallen eindigde de deploy net zo groen als een die het platform wél bijwerkte.
 *
 * Twee dingen liggen daarom vast:
 *   - het deploy-log en de footer noemen ALTIJD de draaiende platformversie, ook als de
 *     composer-stap is overgeslagen. Dat maakt "welk platform draait hier" een kwestie van
 *     kijken in plaats van gokken - ook van buitenaf, want deploy_status.php toont de tail;
 *   - loopt vendor/ achter op wat composer.lock voorschrijft, dan zegt DeployHealth dat
 *     hardop. Dat is de stille variant: groene deploy, oude code.
 *
 *   php tests/TestRunner.php DeployPlatformVersionTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\DeployHealth;

class DeployPlatformVersionTest extends TestCase
{
    /** @var string[] */
    private array $opruimen = [];

    private function template(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../templates/deploy.php.template');
    }

    /** Een nagebootste site-boom met een vastgelegde en een geïnstalleerde commit. */
    private function nepSite(?string $locked, ?string $installed, string $versie = '1.2.3'): string
    {
        $root = sys_get_temp_dir() . '/dpv_' . bin2hex(random_bytes(6));
        @mkdir($root . '/vendor/composer', 0777, true);
        @mkdir($root . '/vendor/stenversonline/platform', 0777, true);
        $this->opruimen[] = $root;

        if ($locked !== null) {
            file_put_contents($root . '/composer.lock', json_encode(['packages' => [
                ['name' => 'stenversonline/platform', 'source' => ['reference' => $locked]],
            ]]) ?: '');
        }
        if ($installed !== null) {
            file_put_contents($root . '/vendor/composer/installed.json', json_encode(['packages' => [
                ['name' => 'stenversonline/platform', 'source' => ['reference' => $installed]],
            ]]) ?: '');
        }
        file_put_contents($root . '/vendor/stenversonline/platform/composer.json',
            json_encode(['version' => $versie]) ?: '');
        return $root;
    }

    private function logVan(string $root): string
    {
        $f = $root . '/.logs/deploy/deploy.log';
        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    public function testVendorThatFollowsTheLockIsFine(): void
    {
        $ref = str_repeat('a', 40);
        $root = $this->nepSite($ref, $ref, '1.38.21');
        $uit = DeployHealth::platformVersionCheck($root);

        $this->assertTrue($uit['ok'], 'gelijke commits is precies goed');
        $this->assertEquals('1.38.21', $uit['version']);
        $this->assertTrue(str_contains($this->logVan($root), 'volgt composer.lock'),
            'ook het goede geval hoort in het log te staan - anders weet je nooit waar de site staat');
    }

    public function testVendorLaggingBehindTheLockIsReportedLoudly(): void
    {
        $root = $this->nepSite(str_repeat('a', 40), str_repeat('b', 40), '1.30.0');
        $uit = DeployHealth::platformVersionCheck($root, ['mail_to' => '']);

        $this->assertFalse($uit['ok'], 'dit is de stille variant en moet juist opvallen');
        $log = $this->logVan($root);
        $this->assertTrue(str_contains($log, 'LOOPT ACHTER'), 'het log moet het benoemen');
        $this->assertTrue(str_contains($log, 'aaaaaaa'), 'met de commit die de lock wil');
        $this->assertTrue(str_contains($log, 'bbbbbbb'), 'en die vendor heeft');
    }

    public function testNothingToCompareIsNotAComplaint(): void
    {
        // Een site kan het platform als pad-repo hebben, of vendor moet nog gebouwd
        // worden. Klagen zou daar alleen ruis zijn.
        $root = $this->nepSite(str_repeat('a', 40), null);
        $uit = DeployHealth::platformVersionCheck($root);

        $this->assertTrue($uit['ok'], 'geen vergelijking mogelijk is geen fout');
        $this->assertTrue(str_contains($this->logVan($root), 'niet te vergelijken'));
    }

    public function testTheDeployLogsThePlatformVersionEvenWhenComposerIsSkipped(): void
    {
        $tpl = $this->template();
        $voor = strpos($tpl, "\$log('platform vooraf: '");
        $stap = strpos($tpl, "\$composerPkgs = \$envRead('DEPLOY_COMPOSER_UPDATE'");
        $this->assertTrue($voor !== false, 'de versie moet vooraf gelogd worden');
        $this->assertTrue($stap !== false && $voor < $stap,
            'en wel VOOR de composer-stap, anders ontbreekt hij juist als die stap wegvalt');
    }

    public function testTheFooterCarriesThePlatformVersion(): void
    {
        // deploy_status.php toont de tail van het log, dus zo is de draaiende versie van
        // elke consumer-site van buitenaf te zien zonder serverToegang.
        $tpl = $this->template();
        $this->assertTrue(str_contains($tpl, "'platform:       ' . \$platformVersion()"),
            'de footer moet de platformversie noemen');
    }

    public function testTheDeployReportsWhetherTheVersionActuallyMoved(): void
    {
        $tpl = $this->template();
        $this->assertTrue(str_contains($tpl, '(ONGEWIJZIGD)'),
            'een composer-stap die niets veranderde moet als zodanig in het log staan');
        $this->assertTrue(str_contains($tpl, 'DeployHealth::platformVersionCheck('),
            'en de deploy moet de lock-controle aanroepen');
    }

    public function testTheHatchStaysSelfSufficient(): void
    {
        // deploy.php is de reddingsboei: hij moet werken als vendor/ half stuk is. De
        // versiehelper leest daarom alleen bestanden en gebruikt niets uit vendor.
        $tpl = $this->template();
        $start = strpos($tpl, '$platformVersion = static function');
        $eind = strpos($tpl, '$bannerLine = str_repeat', $start ?: 0);
        $this->assertTrue($start !== false && $eind !== false, 'de helper moet er zijn');
        $helper = substr($tpl, $start, $eind - $start);
        $this->assertFalse(str_contains($helper, 'App\\Library'),
            'de helper mag niet van de autoloader afhangen');
        $this->assertTrue(str_contains($helper, 'json_decode'), 'hij leest gewoon de bestanden');
    }

    public function tearDown(): void
    {
        foreach ($this->opruimen as $root) {
            foreach (array_reverse(
                iterator_to_array(new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                ))
            ) as $pad) {
                $pad->isDir() ? @rmdir($pad->getPathname()) : @unlink($pad->getPathname());
            }
            @rmdir($root);
        }
        $this->opruimen = [];
    }
}
