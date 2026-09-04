<?php
/**
 * InstallerSiteConfigsTest — wat een verse site bij een release moet krijgen.
 *
 * Run with: php tests/TestRunner.php InstallerSiteConfigsTest
 *
 * Twee dingen worden hier vastgelegd.
 *
 * 1. De per-site configs in data/. Een kale installatie (casa) heeft die map
 *    helemaal niet, en dan valt er in de CMA niets te openen laat staan te
 *    bewerken. De Installer maakt ze leeg aan.
 *
 * 2. Dat hij dat NIET doet bovenop een legacy-naam. MenuService, ReportsService,
 *    de tools-catalogus en cma_get_app_logo() pakken alle vier het cma_-bestand
 *    als het bestaat en vallen anders terug op de oude naam. Een lege
 *    data/cma_menu.json neerzetten op een site die nog data/menu.json gebruikt
 *    voegt dus geen standaard toe — die schaduwt het echte menu met een leeg
 *    menu, en dat merk je pas als de zijbalk leeg is.
 *
 * Pure filesystem-test: nepsite in een tijdelijke map, geen composer, geen DB.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Installer;

class InstallerSiteConfigsTest extends TestCase
{
    private string $tmpRoot;
    private string $platformDir;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/installer-cfg-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/data', 0755, true);
        // De echte pakketmap: siteConfigDefaults() leest daar de gebundelde
        // branding uit, dus die moet de echte zijn en geen verzinsel.
        $this->platformDir = dirname(__DIR__, 2);
    }

    public function tearDown(): void
    {
        foreach ((array) glob($this->tmpRoot . '/data/migrations/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpRoot . '/data/migrations');
        foreach ((array) glob($this->tmpRoot . '/data/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpRoot . '/data');
        @rmdir($this->tmpRoot);
    }

    private function lees(string $naam): array
    {
        $pad = $this->tmpRoot . '/data/' . $naam;
        $this->assertTrue(is_file($pad), "data/$naam is niet aangemaakt");
        $data = json_decode((string) file_get_contents($pad), true);
        $this->assertTrue(is_array($data), "data/$naam is geen geldige JSON");
        return $data;
    }

    public function testKaleSiteKrijgtAlleConfigs(): void
    {
        $log = Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);
        $this->assertCount(7, $log, 'elke aangemaakte config hoort één regel op te leveren');

        $this->assertEquals([], $this->lees('cma_menu.json')['menus']);
        $this->assertEquals([], $this->lees('cma_reports.json')['reports']);
        $this->assertEquals([], $this->lees('cma_tools.json')['groups']);
        $this->assertEquals([], $this->lees('databases.json')['databases']);
        $this->assertEquals([], $this->lees('image-profiles.json')['managed_paths']);

        // Branding is de uitzondering: cma_get_app_logo() pakt het eerste bestand
        // dat bestaat, dus een leeg bestand hier zou het gebundelde logo verbergen.
        $this->assertNotEmpty($this->lees('cma_branding.json')['company']['logo']);
    }

    public function testKaleSiteKrijgtEenLegeEigenMigratiebron(): void
    {
        // Elke site krijgt haar eigen migratiebron: een leeg manifest in data/migrations/,
        // dat app.php.template registreert. Zo bestaat er vanaf dag één een plek voor
        // schemawijzigingen van de site, met een eigen versiereeks naast die van het platform.
        Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);
        $m = $this->lees('migrations/project_migrations.json');
        $this->assertEquals([], $m['migrations']);
        $this->assertEquals('1.0', $m['schemaVersion'] ?? null, 'schemaVersion is verplicht volgens het schema');
        $this->assertEquals('0.0.0', $m['targetVersion'] ?? null, 'targetVersion ook; de site begint bij 0');
        $this->assertEquals('../../cma/config/schema/migrations.schema.json', $m['$schema'] ?? null, 'het schema, vanuit de submap');
        $this->assertTrue(is_file($this->platformDir . '/cma/config/schema/migrations.schema.json'));

        $template = (string) file_get_contents($this->platformDir . '/templates/app.php.template');
        $this->assertStringContainsString("['migration_sources_extra']", $template, 'app.php.template registreert de bron');
        $this->assertStringContainsString("/data/migrations/project_migrations.json'", $template, 'en wijst naar het manifest dat hier is aangemaakt');
    }

    public function testAangemaakteConfigsVerwijzenNaarHunSchema(): void
    {
        Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);

        foreach (['cma_menu.json' => 'menu', 'cma_reports.json' => 'cma_reports',
                  'cma_tools.json' => 'cma_tools', 'cma_branding.json' => 'cma_branding',
                  'databases.json' => 'databases'] as $bestand => $schema) {
            $ref = $this->lees($bestand)['$schema'] ?? '';
            $this->assertEquals('../cma/config/schema/' . $schema . '.schema.json', $ref);
            $this->assertTrue(
                is_file($this->platformDir . '/cma/config/schema/' . $schema . '.schema.json'),
                "$bestand verwijst naar een schema dat niet bestaat"
            );
        }
    }

    public function testLegacyNaamWordtNietGeschaduwd(): void
    {
        file_put_contents($this->tmpRoot . '/data/menu.json', '{"menus":[{"id":1,"name":"Beheer"}]}');
        file_put_contents($this->tmpRoot . '/data/app.json', '{"company":{"logo":"eigen.svg"}}');

        Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);

        $this->assertFalse(is_file($this->tmpRoot . '/data/cma_menu.json'),
            'een lege cma_menu.json bovenop data/menu.json verbergt het echte menu');
        $this->assertFalse(is_file($this->tmpRoot . '/data/cma_branding.json'));
        // De configs zonder legacy-tweeling komen er wél gewoon bij.
        $this->assertTrue(is_file($this->tmpRoot . '/data/cma_reports.json'));
    }

    public function testBestaandeConfigWordtNietOverschreven(): void
    {
        file_put_contents($this->tmpRoot . '/data/cma_reports.json', '{"reports":[{"id":7}]}');

        Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);

        $this->assertEquals([['id' => 7]], $this->lees('cma_reports.json')['reports']);
    }

    public function testTweedeKeerDraaienDoetNiets(): void
    {
        Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir);
        $this->assertEquals([], Installer::ensureSiteConfigs($this->tmpRoot, $this->platformDir));
    }

    /**
     * De onderhouds- en 404-pagina horen bij een release te ontstaan als ze er
     * nog niet zijn. Dat is precies wat TEMPLATE_FILES doet, mits er ook een
     * template naast ligt om te kopiëren.
     */
    public function testOnderhoudsEn404PaginaZittenInDeTemplates(): void
    {
        $ref = new \ReflectionClass(Installer::class);
        $templates = $ref->getConstant('TEMPLATE_FILES');

        $this->assertEquals('src/pages/maintenance.php', $templates['src/pages/maintenance.php.template'] ?? null);
        $this->assertEquals('404.php', $templates['404.php.template'] ?? null);

        foreach ($templates as $bron => $doel) {
            $this->assertTrue(is_file($this->platformDir . '/templates/' . $bron)
                || $bron === '.env.example',
                "template $bron ontbreekt in het pakket, dus $doel ontstaat nooit");
        }
    }
}
