<?php

declare(strict_types=1);

namespace App\Library;

/**
 * Het vlagbestand "er staan migraties open" (migrations_pending.flag in de root van de site), net als maintenance.flag.
 *
 * Waarom een bestand: de front-end wil een banner kunnen tonen ("Er zijn migraties vereist") zonder bij elk verzoek de migratieservice (alle bronnen, versietabellen) te raadplegen
 * en zonder een cache die na een geslaagde migratie nog een tijd het oude antwoord geeft. De vlag wordt bijgewerkt op de plekken waar de uitkomst vaststaat:
 *
 *   - Cma\Services\MigrationService na elke uitvoer (alles, tot een versie, één migratie, opnieuw uitvoeren): de vlag volgt het aantal dat daarna nog openstaat;
 *   - cma/migrate.php, ook met --check: de deploy (deploy.php) en wie lokaal na een update `php cma/migrate.php --check` draait.
 *
 * Geen openstaande migraties: de vlag is weg. Wel: de vlag staat er, met het aantal en het tijdstip erin.
 *
 * Alleen native PHP en Bootstrap::getRootDir(): dit mag nooit een pagina breken en moet ook werken als de migratieservice niet is geladen.
 */
final class MigrationFlag
{
    public const FILE = 'migrations_pending.flag';

    /** De map waar de vlag staat: de root van de site (waar _bootstrap.php staat). */
    private static function root(?string $root): string
    {
        if ($root !== null && $root !== '') {
            return rtrim($root, '/\\');
        }
        try {
            $r = Bootstrap::getRootDir();
            if ($r !== '') {
                return rtrim($r, '/\\');
            }
        } catch (\Throwable $e) {
            // Bootstrap nog niet gestart: val terug op de plek in vendor/ (vendor/stenversonline/platform/src/helpers -> root).
        }
        return dirname(__DIR__, 5);
    }

    /** Het pad van het vlagbestand. */
    public static function path(?string $root = null): string
    {
        return self::root($root) . DIRECTORY_SEPARATOR . self::FILE;
    }

    /** Staat de vlag er? Een bestandscontrole, geen database. */
    public static function exists(?string $root = null): bool
    {
        return @is_file(self::path($root));
    }

    /** Het aantal openstaande migraties uit de vlag; 0 zonder vlag, null als de vlag er staat maar het aantal niet bekend is. */
    public static function count(?string $root = null): ?int
    {
        if (!self::exists($root)) {
            return 0;
        }
        $d = json_decode((string) @file_get_contents(self::path($root)), true);
        return is_array($d) && isset($d['n']) ? (int) $d['n'] : null;
    }

    /** Zet de vlag als er migraties openstaan en haalt hem weg als dat er geen zijn. Elke fout wordt ingeslikt: een vlag mag niets stuk maken. */
    public static function sync(int $openstaand, ?string $root = null): void
    {
        try {
            $pad = self::path($root);
            if ($openstaand > 0) {
                @file_put_contents($pad, json_encode(['n' => $openstaand, 't' => time()]), LOCK_EX);
            } elseif (@is_file($pad)) {
                @unlink($pad);
            }
        } catch (\Throwable $e) {
            // bewust niets
        }
    }

    /**
     * De banner voor de front-end: leeg als de vlag er niet staat. Eén bestandscontrole per verzoek.
     *
     * @param string $cmaUrl waar "Uitvoeren in CMA" naartoe gaat, relatief aan de site-root (standaard het migratiescherm)
     * @param string $basis  voorvoegsel voor die url (de base_path van de site), meestal '' of '/'
     */
    public static function bannerHtml(string $cmaUrl = 'cma/tools.php?tool=migrations', string $basis = ''): string
    {
        if (!self::exists()) {
            return '';
        }
        return '<div class="migratie-banner" style="background:#fff3cd;color:#856404;border-bottom:1px solid #ffeeba;padding:8px 16px;text-align:center;font-size:14px">'
            . 'Er zijn migraties vereist. '
            . '<a href="' . htmlspecialchars($basis . $cmaUrl, ENT_QUOTES) . '" style="color:#856404;font-weight:bold">Uitvoeren in CMA</a>'
            . '</div>' . PHP_EOL;
    }
}
