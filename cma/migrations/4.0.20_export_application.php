<?php
/**
 * Export Application Config naar app.json - Migratie Script
 *
 * Exporteert applicatie configuratie uit tblApplications naar app.json
 */

use App\Library\Database;
use App\Library\Response;
use Cma\SecurityHelper;

// Fix pad wanneer uitgevoerd als migratie
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

// Controleer of het als migratie wordt uitgevoerd
$isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');

if (!$isMigration) {
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo "Toegang geweigerd - alleen developers";
        exit(1);
    }
    Response::noCache();
}

// Exporteer applicatie configuratie
$result = exportApplicationToAppJson();

if ($isMigration) {
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        if (defined('MIGRATION_RUNNING')) {
            return true;
        }
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        if (defined('MIGRATION_RUNNING')) {
            return false;
        }
        exit(1);
    }
} else {
    cma_html_header('Applicatie config exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        echo '<lib-message type="success"><strong>Applicatie configuratie succesvol geëxporteerd!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    } else {
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . '</lib-message>';
    }

    echo '</div></body></html>';
}

/**
 * Exporteer applicatie configuratie uit tblApplications naar app.json
 */
function exportApplicationToAppJson(): array
{
    $appPath = dirname(__DIR__, 2) . '/data/app.json';

    try {
        // Controleer of app.json al bestaat met niet-standaard waarden
        if (file_exists($appPath)) {
            $existing = json_decode(file_get_contents($appPath), true);
            if ($existing !== null && !empty($existing['company']['logo'])) {
                return ['success' => true, 'message' => 'Applicatie configuratie is al aanwezig in app.json'];
            }
        }

        // Haal data uit tblApplications
        $connrep = Database::getRepConnection();
        if ($connrep === null) {
            return ['success' => false, 'message' => 'Kan geen verbinding maken met rep database'];
        }

        $sql = 'SELECT TOP 1 Company_Logo, Company_Logo_Width, Company_Logo_Height, URL, backgroundColor FROM tblApplications';
        $cursorType = defined('adOpenForwardOnly') ? adOpenForwardOnly : 0;
        $rs = Database::openRS($sql, $connrep, $cursorType);

        $appData = [
            '$schema' => './schema/app.schema.json',
            'company' => [
                'logo' => '',
                'logoWidth' => 200,
                'logoHeight' => 50,
                'url' => '../',
                'backgroundColor' => '#3F096E'
            ]
        ];

        if ($rs !== null && !$rs->EOF) {
            $appData['company'] = [
                'logo' => $rs->fields['Company_Logo'] ?? '',
                'logoWidth' => (int)($rs->fields['Company_Logo_Width'] ?? 200),
                'logoHeight' => (int)($rs->fields['Company_Logo_Height'] ?? 50),
                'url' => $rs->fields['URL'] ?? '../',
                'backgroundColor' => $rs->fields['backgroundColor'] ?? '#3F096E'
            ];
        }

        // Sla op
        $json = json_encode($appData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($appPath, $json) === false) {
            return ['success' => false, 'message' => 'Kan app.json niet opslaan'];
        }

        return [
            'success' => true,
            'message' => 'Applicatie configuratie geëxporteerd naar app.json'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage()];
    }
}
