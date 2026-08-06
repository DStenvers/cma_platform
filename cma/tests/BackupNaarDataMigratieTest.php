<?php
/**
 * Tests voor migratie 9.23.0 (<site>/.backup -> <site>/data/.backup) en voor de bepaler die
 * de back-uptool en de service dezelfde map laat kiezen.
 *
 * Waarom hier zoveel gevallen voor één verhuizing: het gaat om back-ups. Een migratie die een
 * kopie overschrijft of weggooit is erger dan een migratie die niets doet, dus dat is precies
 * wat hier wordt vastgelegd — inclusief de tussenstand waarin het pakket al is bijgewerkt maar
 * de migratie nog niet is gedraaid.
 *
 * De migratie accepteert een vooraf gezette $siteRoot, zodat hij tegen een tijdelijke map
 * draait en niet tegen een echte site.
 *
 * Draaien met: php tests/TestRunner.php BackupNaarDataMigratieTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/BackupService.php';

class BackupNaarDataMigratieTest extends TestCase
{
    private const MIGRATIE = __DIR__ . '/../migrations/9.23.0_move_backup_to_data.php';

    private function maakSite(): string
    {
        $dir = sys_get_temp_dir() . '/backupverhuis_' . uniqid('', true);
        mkdir($dir . '/data', 0777, true);
        return $dir;
    }

    private function ruimOp(string $dir): void
    {
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (in_array(basename($f), ['.', '..'], true)) {
                continue;
            }
            is_dir($f) ? $this->ruimOp($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    private function draaiMigratie(string $siteRoot): string
    {
        ob_start();
        include self::MIGRATIE;   // gebruikt $siteRoot uit deze scope
        return (string) ob_get_clean();
    }

    public function testVerhuistDeHeleMapAlsDeNieuwePlekLeegIs(): void
    {
        $site = $this->maakSite();
        mkdir($site . '/.backup', 0777, true);
        file_put_contents($site . '/.backup/2026-01-23_data_backup.mdb', 'kopie');
        file_put_contents($site . '/.backup/backups.json', '{"backups":[]}');

        $this->draaiMigratie($site);

        $this->assertTrue(is_file($site . '/data/.backup/2026-01-23_data_backup.mdb'), 'de kopie staat op de nieuwe plek');
        $this->assertEquals('kopie', file_get_contents($site . '/data/.backup/2026-01-23_data_backup.mdb'), 'de inhoud is ongemoeid');
        $this->assertTrue(is_file($site . '/data/.backup/backups.json'), 'de index gaat mee');
        $this->assertFalse(is_dir($site . '/.backup'), 'de oude map is opgeruimd');

        $this->ruimOp($site);
    }

    public function testOverschrijftNooitEenBestaandeKopie(): void
    {
        $site = $this->maakSite();
        mkdir($site . '/.backup', 0777, true);
        mkdir($site . '/data/.backup', 0777, true);
        file_put_contents($site . '/.backup/zelfde.mdb', 'OUD');
        file_put_contents($site . '/data/.backup/zelfde.mdb', 'NIEUW');
        file_put_contents($site . '/.backup/alleen_oud.mdb', 'x');

        $uitvoer = $this->draaiMigratie($site);

        $this->assertEquals('NIEUW', file_get_contents($site . '/data/.backup/zelfde.mdb'),
            'een bestaande kopie op de nieuwe plek mag niet overschreven worden');
        $this->assertTrue(is_file($site . '/.backup/zelfde.mdb'),
            'en de oude mag dan ook niet weggegooid worden — bij back-ups is dubbel beter dan weg');
        $this->assertTrue(is_file($site . '/data/.backup/alleen_oud.mdb'), 'de rest verhuist wel gewoon');
        $this->assertStringContainsString('zelfde.mdb', $uitvoer, 'de migratie meldt wat er bleef staan');

        $this->ruimOp($site);
    }

    public function testIsIdempotent(): void
    {
        $site = $this->maakSite();
        mkdir($site . '/.backup', 0777, true);
        file_put_contents($site . '/.backup/een.mdb', 'a');

        $this->draaiMigratie($site);
        $tweede = $this->draaiMigratie($site);

        $this->assertTrue(is_file($site . '/data/.backup/een.mdb'), 'de kopie staat er nog steeds');
        $this->assertStringContainsString('Overgeslagen', $tweede, 'de tweede keer valt er niets meer te doen');

        $this->ruimOp($site);
    }

    public function testDoetNietsZonderDataMap(): void
    {
        // Geen data/ betekent: dit is geen siteroot. Dan is stil doorgaan gevaarlijker dan
        // niets doen — er zou een data/.backup ontstaan op een plek die niet de site is.
        $site = sys_get_temp_dir() . '/backupverhuis_geen_data_' . uniqid('', true);
        mkdir($site . '/.backup', 0777, true);
        file_put_contents($site . '/.backup/een.mdb', 'a');

        $this->draaiMigratie($site);

        $this->assertTrue(is_file($site . '/.backup/een.mdb'), 'de kopie blijft staan waar hij stond');
        $this->assertFalse(is_dir($site . '/data'), 'er wordt geen data/ aangemaakt');

        $this->ruimOp($site);
    }

    // ── De bepaler: tool en service moeten dezelfde map kiezen ──────────────────────────
    public function testBepalerKiestDeNieuwePlek(): void
    {
        $site = $this->maakSite();
        mkdir($site . '/data/.backup', 0777, true);

        $this->assertEquals($site . '/data/.backup', \Cma\Services\BackupService::bepaalBackupDir($site),
            'met een bestaande data/.backup is dat de plek');

        $this->ruimOp($site);
    }

    public function testBepalerHoudtDeOudePlekTotDeMigratieIsGedraaid(): void
    {
        // Dit is het venster tussen "pakket bijgewerkt" en "migratie gedraaid". Zonder deze
        // terugval kijkt de tool daar naar een lege map en lijken de back-ups verdwenen.
        $site = $this->maakSite();
        mkdir($site . '/.backup', 0777, true);
        file_put_contents($site . '/.backup/een.mdb', 'a');

        $this->assertEquals($site . '/.backup', \Cma\Services\BackupService::bepaalBackupDir($site),
            'zolang de oude map inhoud heeft en de nieuwe er niet is, blijft de oude in gebruik');

        // En zodra de migratie is gedraaid, verschuift hij mee.
        $this->draaiMigratie($site);
        $this->assertEquals($site . '/data/.backup', \Cma\Services\BackupService::bepaalBackupDir($site),
            'na de verhuizing wijst de bepaler naar data/.backup');

        $this->ruimOp($site);
    }

    public function testBepalerNegeertEenLegeOudeMap(): void
    {
        $site = $this->maakSite();
        mkdir($site . '/.backup', 0777, true);

        $this->assertEquals($site . '/data/.backup', \Cma\Services\BackupService::bepaalBackupDir($site),
            'een lege oude map is geen reden om daar te blijven schrijven');

        $this->ruimOp($site);
    }
}
