<?php
/**
 * Migration 9.12.0: legacy ASP-config (global.php / global.asa.php) buiten gebruik stellen.
 *
 * Achtergrond: bij de ASP->PHP-conversie bleven `global.php` (de oude global.asa, NIET geladen)
 * en `global.asa.php` (geladen, maar op veel sites uitgekleed) achter. Instellingen horen nu in
 * `app.php` (code-config) en `.env` (secrets/omgeving). Losse secrets in global.php zorgden o.a.
 * dat Mollie_ApiKey leeg bleef.
 *
 * Deze migratie VERWIJDERT niets, maar HERNOEMT de bestanden naar *.delete_when_migrated
 * (omkeerbaar; handmatig wissen zodra alles bevestigd werkt). Veilig + idempotent:
 *
 *  1. De `require_once .../global.asa.php` in app.php en _bootstrap.php wordt met een
 *     file_exists-guard afgeschermd, zodat het wegvallen van het bestand geen fatal geeft.
 *  2. Bekende env-secrets uit global.php (Mollie_ApiKey, Google_api_key) worden naar het
 *     actieve .env-bestand getild als die sleutel daar nog ontbreekt of leeg is.
 *  3. global.php  -> global.php.delete_when_migrated  (dood; altijd veilig).
 *  4. global.asa.php -> global.asa.php.delete_when_migrated, ALLEEN als Application_OnStart
 *     triviaal is (geen $GLOBALS['Application']-config). Sites die er nog echte config in
 *     hebben (bv. DB-connecties) blijven ongemoeid en krijgen een waarschuwing.
 */

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);

$globalPhp = $basePath . '/global.php';
$globalAsa = $basePath . '/global.asa.php';
$appPhp    = $basePath . '/app.php';
$bootstrap = $basePath . '/_bootstrap.php';
$suffix    = '.delete_when_migrated';

// --- 1. require van global.asa.php afschermen met file_exists (app.php + _bootstrap.php) ---
$needle = "require_once __DIR__ . '/global.asa.php';";
$guard  = "if (file_exists(__DIR__ . '/global.asa.php')) { require_once __DIR__ . '/global.asa.php'; }";
foreach ([$appPhp, $bootstrap] as $f) {
    if (!is_file($f)) {
        continue;
    }
    $src = file_get_contents($f);
    if ($src === false) {
        continue;
    }
    if (strpos($src, $needle) !== false && strpos($src, $guard) === false) {
        @copy($f, $f . '.bak_retire_global');
        file_put_contents($f, str_replace($needle, $guard, $src));
        echo "  Afgeschermd: global.asa.php-require in " . basename($f) . " met file_exists-guard\n";
    } else {
        echo "  Overgeslagen: " . basename($f) . " (require al afgeschermd of niet aanwezig)\n";
    }
}

// --- 2. Bekende env-secrets uit global.php naar het actieve .env tillen (alleen als leeg) ---
if (is_file($globalPhp)) {
    $g = (string)file_get_contents($globalPhp);
    $omgeving = strtoupper((string)($GLOBALS['Application']['omgeving'] ?? 'P'));
    $isTest = ($omgeving !== 'P');

    // global.php zet sommige sleutels conditioneel (test/live). Kies de juiste waarde.
    $extract = function ($key) use ($g, $isTest) {
        if (!preg_match_all('/Application\("' . preg_quote($key, '/') . '"\)\s*=\s*"([^"]*)"/i', $g, $m)) {
            return '';
        }
        $vals = array_values(array_filter($m[1], static function ($v) {
            return trim($v) !== '';
        }));
        if (count($vals) <= 1) {
            return $vals[0] ?? '';
        }
        foreach ($vals as $v) {
            if ($isTest && stripos($v, 'test_') === 0) {
                return $v;
            }
            if (!$isTest && stripos($v, 'live_') === 0) {
                return $v;
            }
        }
        return $vals[0];
    };

    // Bepaal het actieve .env-bestand (omgeving-specifiek, anders .env).
    $envNameMap = ['P' => 'production', 'A' => 'acceptance', 'T' => 'test'];
    $envCandidates = [];
    if (isset($envNameMap[$omgeving])) {
        $envCandidates[] = $basePath . '/.env.' . $envNameMap[$omgeving];
    }
    $envCandidates[] = $basePath . '/.env';
    $envFile = null;
    foreach ($envCandidates as $cand) {
        if (is_file($cand)) {
            $envFile = $cand;
            break;
        }
    }

    $secretMap = ['Mollie_ApiKey' => 'MOLLIE_APIKEY', 'Google_api_key' => 'GOOGLE_API_KEY'];
    if ($envFile !== null) {
        $env = (string)file_get_contents($envFile);
        $changed = false;
        foreach ($secretMap as $appKey => $envKey) {
            $val = trim($extract($appKey));
            if ($val === '') {
                continue;
            }
            // Huidige waarde in .env bepalen.
            $hasKey = preg_match('/^' . preg_quote($envKey, '/') . '=(.*)$/m', $env, $cm);
            $cur = $hasKey ? trim($cm[1]) : '';
            if ($cur !== '') {
                echo "  Overgeslagen .env: " . $envKey . " heeft al een waarde\n";
                continue;
            }
            if ($hasKey) {
                $env = preg_replace('/^' . preg_quote($envKey, '/') . '=.*$/m', $envKey . '=' . $val, $env, 1);
            } else {
                $env = rtrim($env, "\r\n") . "\n" . $envKey . '=' . $val . "\n";
            }
            $changed = true;
            echo "  Verplaatst naar " . basename($envFile) . ": " . $envKey . " (uit global.php)\n";
        }
        if ($changed) {
            @copy($envFile, $envFile . '.bak_retire_global');
            file_put_contents($envFile, $env);
        }
    } else {
        echo "  LET OP: geen .env gevonden; env-secrets uit global.php niet verplaatst\n";
    }
}

// --- 3. global.php hernoemen (dood; altijd veilig) ---
if (is_file($globalPhp)) {
    $dest = $globalPhp . $suffix;
    if (is_file($dest)) {
        echo "  Overgeslagen: global.php (al hernoemd)\n";
    } else {
        rename($globalPhp, $dest);
        echo "  Hernoemd: global.php -> global.php" . $suffix . "\n";
    }
}

// --- 4. global.asa.php hernoemen, alleen als Application_OnStart triviaal is ---
if (is_file($globalAsa)) {
    $asa = (string)file_get_contents($globalAsa);
    // Triviaal = geen $GLOBALS['Application'][...] toewijzingen en geen conn_-config.
    $trivial = (strpos($asa, "\$GLOBALS['Application']") === false)
        && (strpos($asa, '$GLOBALS["Application"]') === false)
        && (stripos($asa, 'conn_data') === false);
    $dest = $globalAsa . $suffix;
    if (is_file($dest)) {
        echo "  Overgeslagen: global.asa.php (al hernoemd)\n";
    } elseif ($trivial) {
        rename($globalAsa, $dest);
        echo "  Hernoemd: global.asa.php -> global.asa.php" . $suffix . " (Application_OnStart was leeg)\n";
    } else {
        echo "  WAARSCHUWING: global.asa.php bevat nog echte config (Application_OnStart) -> NIET hernoemd. Migreer die instellingen eerst naar app.php/.env.\n";
    }
}

echo "Klaar. Bestanden zijn hernoemd naar *" . $suffix . " (omkeerbaar); wis ze handmatig zodra bevestigd.\n";
