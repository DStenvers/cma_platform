<?php
/**
 * The PHP-environment advice on the dashboard for admins and developers:
 * version, OPcache, JIT, APCu, realpath cache. Pure: every input is a
 * parameter, so the rules are testable without a live PHP configuration.
 */

namespace Cma\Services;

class PhpAdvice
{
    /** Below this version PDO_ODBC mangles long string literals on write. */
    public const MIN_PHP_VERSION = '8.3.14';

    /**
     * @param string $phpVersion   PHP_VERSION
     * @param bool   $opcache      OPcache active
     * @param string $jit          ini opcache.jit ('tracing', '1255', '0', 'off', …)
     * @param int    $jitBuffer    opcache.jit_buffer_size in bytes
     * @param bool   $apcu         APCu available
     * @param int    $realpathKb   realpath_cache_size in KB
     * @return string[] HTML fragments, one per warning; empty when all is well
     */
    public static function warnings(string $phpVersion, bool $opcache, string $jit, int $jitBuffer, bool $apcu, int $realpathKb): array
    {
        $w = [];
        if (version_compare($phpVersion, self::MIN_PHP_VERSION, '<')) {
            $w[] = '<span class="cma-page__strong">PHP ' . htmlspecialchars($phpVersion) . '</span> — een upgrade naar ' . self::MIN_PHP_VERSION
                . ' of hoger (bij voorkeur 8.4) is aan te raden: oudere versies hebben een fout in PDO_ODBC waardoor lange tekstwaarden verminkt in de database komen (onder meer de notificaties van CMA Monitoring).';
        }
        if (!$opcache) {
            $w[] = '<span class="cma-page__strong">OPcache</span> is niet actief. Dit vertraagt elke pagina-aanvraag aanzienlijk doordat PHP-bestanden steeds opnieuw gecompileerd worden.';
        } elseif (!self::jitOn($jit, $jitBuffer)) {
            $w[] = '<span class="cma-page__strong">JIT</span> staat uit. Zet in php.ini <code>opcache.jit=tracing</code> en <code>opcache.jit_buffer_size=64M</code> en recycle de app-pool; dat kan alleen in php.ini (PHP_INI_SYSTEM), niet in .user.ini.';
        }
        if (!$apcu) {
            $w[] = '<span class="cma-page__strong">APCu</span> is niet geïnstalleerd. Zonder APCu valt de cache terug op bestandssysteem-I/O, wat formulierlijsten en templates aanzienlijk vertraagt.';
        }
        if ($realpathKb > 0 && $realpathKb < 4096) {
            $w[] = '<span class="cma-page__strong">realpath_cache_size</span> is laag (' . $realpathKb . 'K). Verhoog naar minimaal 4M voor betere prestaties.';
        }
        return $w;
    }

    public static function jitOn(string $jit, int $jitBuffer): bool
    {
        $j = strtolower(trim($jit));
        return $jitBuffer > 0 && $j !== '' && $j !== '0' && $j !== 'off' && $j !== 'disable' && $j !== 'no' && $j !== 'false';
    }
}
