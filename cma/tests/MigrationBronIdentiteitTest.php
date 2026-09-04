<?php
/**
 * MigrationBronIdentiteitTest — een migratie heet "bron:versie", niet alleen "versie".
 *
 * Gemeld: "migraties op siteniveau gaan mis omdat er maar één versienummer is; de site
 * zit onder 1.0, het platform al op 9.x". De loper vergeleek al per bron (elke bron heeft
 * haar eigen versietabel), maar alles eromheen niet: het scherm sorteerde alle bronnen op
 * versie door elkaar, "toepassen tot versie X" vergeleek versies over bronnen heen, en
 * opnieuw-uitvoeren / apply_single / isMigrationApplied zochten op een kaal nummer. Met
 * 0.1.0 (site) naast 9.23.0 (platform) is dat willekeur.
 *
 * Nu: elke migratie draagt een id "<bron>:<versie>"; aanwijzen gaat op dat id, "tot hier"
 * is een positie in de uitvoerlijst, en de doelversie bestaat per bron.
 *
 * Run: php tests/TestRunner.php MigrationBronIdentiteitTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/MigrationService.php';

use Cma\Services\MigrationService;
use App\Library\Application;

class MigrationBronIdentiteitTest extends TestCase
{
    private array $tmp = [];

    /** Een site-bron met deze migraties, naast het echte platformmanifest. */
    private function metSite(array $migrations, string $naam = 'testsite', ?string $target = null): MigrationService
    {
        $dir = sys_get_temp_dir() . '/cma_migid_' . getmypid() . '_' . count($this->tmp);
        @mkdir($dir, 0777, true);
        $file = $dir . '/site_migrations.json';
        $manifest = ['migrations' => $migrations];
        if ($target !== null) { $manifest['targetVersion'] = $target; }
        file_put_contents($file, json_encode($manifest));
        $this->tmp[] = $file;
        Application::set('migration_sources_extra', [['name' => $naam, 'file' => $file, 'trackingDb' => 'data']]);
        return new MigrationService();
    }

    public function tearDown(): void
    {
        Application::set('migration_sources_extra', []);
        foreach ($this->tmp as $f) { @unlink($f); @rmdir(dirname($f)); }
        $this->tmp = [];
    }

    private function m(string $v, string $d = 'x'): array
    {
        return ['version' => $v, 'description' => $d, 'changes' => []];
    }

    public function testElkeMigratieDraagtEenIdVanBronEnVersie(): void
    {
        $svc = $this->metSite([$this->m('0.1.0')]);
        $ids = array_column($svc->getAllMigrations(), '_id');
        $this->assertTrue(in_array('platform:1.0.0', $ids, true), 'het platform-id is "platform:<versie>"');
        $this->assertTrue(in_array('testsite:0.1.0', $ids, true), 'het site-id is "<bron>:<versie>"');
        $this->assertEquals('testsite:0.1.0', MigrationService::migrationId('testsite', '0.1.0'));
    }

    public function testOpzoekenOpIdEnOpKaalNummer(): void
    {
        $svc = $this->metSite([$this->m('0.1.0', 'grademethod')]);
        $this->assertEquals('grademethod', $svc->findMigration('testsite:0.1.0')['description'] ?? null, 'op id');
        $this->assertEquals('grademethod', $svc->findMigration('0.1.0')['description'] ?? null, 'een kaal nummer dat maar één bron kent');
        $this->assertEquals('platform', $svc->findMigration('platform:1.0.0')['_source'] ?? null);
        $this->assertNull($svc->findMigration('nergens:0.1.0'), 'onbekende bron');
        $this->assertNull($svc->findMigration(''), 'leeg');
        // getMigrationByVersion blijft bestaan voor oude aanroepers en doet hetzelfde.
        $this->assertEquals('testsite:0.1.0', $svc->getMigrationByVersion('0.1.0')['_id'] ?? null);
    }

    public function testEenKaalNummerDatTweeBronnenKennenWijstNiets(): void
    {
        // 1.0.0 bestaat ook in het platform: dubbelzinnig, dus null - niet "de eerste de beste".
        $svc = $this->metSite([$this->m('1.0.0')]);
        $this->assertNull($svc->findMigration('1.0.0'), 'dubbelzinnig kaal nummer');
        $this->assertEquals('testsite', $svc->findMigration('testsite:1.0.0')['_source'] ?? null, 'met bron is het eenduidig');
    }

    /** De openstaande lijst zoals de loper hem oplevert: platform eerst, dan de site. */
    private function lijst(MigrationService $svc, array $ids): array
    {
        $uit = [];
        foreach ($ids as $id) { $uit[] = $svc->findMigration($id); }
        return $uit;
    }

    public function testToepassenTotHierIsEenPositieInDeLijst(): void
    {
        $svc = $this->metSite([$this->m('0.1.0'), $this->m('0.2.0')]);
        $pending = $this->lijst($svc, ['platform:9.22.0', 'platform:9.23.0', 'testsite:0.1.0', 'testsite:0.2.0']);
        $this->assertEquals(4, count(array_filter($pending)), 'alle vier bestaan in de manifests');

        $sel = static fn(array $s) => array_column($s, '_id');

        $this->assertEquals(['platform:9.22.0', 'platform:9.23.0', 'testsite:0.1.0', 'testsite:0.2.0'],
            $sel($svc->selectPendingUpTo($pending, null)), 'zonder doel: alles');
        $this->assertEquals(['platform:9.22.0', 'platform:9.23.0', 'testsite:0.1.0', 'testsite:0.2.0'],
            $sel($svc->selectPendingUpTo($pending, 'testsite:0.2.0')), 'de laatste regel: alles');
        $this->assertEquals(['platform:9.22.0', 'platform:9.23.0', 'testsite:0.1.0'],
            $sel($svc->selectPendingUpTo($pending, 'testsite:0.1.0')),
            'tot de eerste site-regel: het hele platform ervoor mee, 0.2.0 niet - vroeger viel op "<= 0.1.0" het hele platform weg');
        $this->assertEquals(['platform:9.22.0'],
            $sel($svc->selectPendingUpTo($pending, 'platform:9.22.0')), 'tot een platform-regel: alleen tot daar');
        $this->assertEquals(['platform:9.22.0', 'platform:9.23.0', 'testsite:0.1.0'],
            $sel($svc->selectPendingUpTo($pending, '0.1.0')), 'een kaal nummer dat uniek is werkt ook');
        $this->assertEquals([], $sel($svc->selectPendingUpTo($pending, 'testsite:0.9.0')), 'onbekend doel: niets, geen "dan maar alles"');
        $this->assertEquals([], $sel($svc->selectPendingUpTo($pending, 'platform:1.0.0')), 'een doel dat niet openstaat: niets');
    }

    public function testDoelversieBestaatPerBron(): void
    {
        $svc = $this->metSite([$this->m('0.1.0'), $this->m('0.3.0')], 'testsite');
        $doelen = $svc->getTargetVersions();
        $this->assertTrue(version_compare($doelen['platform'] ?? '0', '1.0.0', '>='), 'het platform heeft zijn eigen doel');
        $this->assertEquals('0.3.0', $doelen['testsite'] ?? null, 'zonder targetVersion in het manifest: de hoogste versie erin');
        $this->assertEquals($svc->getTargetVersion(), $doelen['platform'], 'getTargetVersion() blijft het platformdoel');

        $svc2 = $this->metSite([$this->m('0.1.0')], 'andersite', '0.5.0');
        $this->assertEquals('0.5.0', $svc2->getTargetVersions()['andersite'] ?? null, 'met targetVersion: die');
    }

    public function testVersietabelPerBronOokZonderService(): void
    {
        $this->assertEquals('_cma_version', MigrationService::trackingTableFor('platform'));
        $this->assertEquals('_cma_version', MigrationService::trackingTableFor(''));
        Application::set('migration_sources_extra', [['name' => 'mijnrino', 'file' => 'x.json', 'trackingTable' => '_cma_mijnrino_version']]);
        $this->assertEquals('_cma_mijnrino_version', MigrationService::trackingTableFor('mijnrino'), 'de geregistreerde tabel');
        $this->assertEquals('_cma_ander_version', MigrationService::trackingTableFor('ander'), 'niet geregistreerd: de standaardnaam');
    }

    public function testHetSchermWerktOpIdsEnNietOpVersievergelijking(): void
    {
        $bron = (string) file_get_contents(__DIR__ . '/../tools/tools_migrations.php');
        $this->assertStringContainsString('data-migration=', $bron, 'rijen dragen het id');
        $this->assertStringNotContainsString('data-version=', $bron, 'en niet meer het kale nummer');
        $this->assertStringNotContainsString('function compareVersions', $bron, 'geen versievergelijking over bronnen heen in de JS');
        $this->assertStringContainsString('pendingVersions.indexOf(selectedVersion)', $bron, '"tot hier" is een positie in de lijst');
        $this->assertStringNotContainsString("usort(\$sortedPending, fn(\$a, \$b) => version_compare", $bron, 'de openstaande lijst wordt niet meer over bronnen heen op versie gesorteerd');
        $this->assertStringContainsString("array_column(\$sortedPending, '_id')", $bron, 'de JS krijgt ids');
    }
}
