<?php
/**
 * User Forms API
 *
 * Returns the current user's frequently used forms.
 * Available to all logged-in users.
 */

use App\Library\Cookie;
use App\Library\Database;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\Services\Logger;
use Cma\Services\MenuService;

require_once __DIR__ . '/../bootstrap.inc';

header('Content-Type: application/json');

// Get current user
$username = SecurityHelper::getCurrentUserName();
if (empty($username) || !SecurityHelper::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

// Form-specific icons (takes precedence over menu group icons)
$formIcons = [
    // Personen
    'rino_contactpersonen' => 'lnr-user',
    'rino contactpersonen' => 'lnr-user',
    'deelnemers' => 'lnr-users',
    'docenten' => 'lnr-graduation-hat',
    'praktijkopleiders' => 'lnr-briefcase',
    'werkbegeleiders' => 'lnr-hand',
    'supervisoren' => 'lnr-eye',
    'p_opleiders' => 'lnr-license',
    'contactpersonen' => 'lnr-phone-wave',
    'contactpersonen_inventarisatie' => 'lnr-phone-wave',
    'logins' => 'lnr-key',
    'users' => 'lnr-user',
    'groups' => 'lnr-users',
    'stichtingen' => 'lnr-apartment',

    // Opleidingen
    'opleidingen' => 'lnr-graduation-hat',
    'opleidingensoort' => 'lnr-graduation-hat',
    'differentiatie' => 'lnr-layers',
    'locaties' => 'lnr-map-marker',
    'competentie_templates' => 'lnr-star',
    'competenties' => 'lnr-star',
    'urentemplate' => 'lnr-clock',

    // Rooster & planning
    'rooster' => 'lnr-calendar-full',
    'blokken' => 'lnr-layers',
    'agendareserveringen' => 'lnr-calendar-31',
    'ingeplande_tijdsblokken' => 'lnr-calendar-31',
    'aanwezigheid' => 'lnr-checkmark-circle',

    // Taken & afspraken
    'taken' => 'lnr-clipboard-check',
    'afspraak' => 'lnr-calendar-check',

    // Toetsing & evaluatie
    'toetsing' => 'lnr-license2',
    'toetsen' => 'lnr-file-check',
    'praktijktoetsen' => 'lnr-file-check',
    'evaluatie_template' => 'lnr-thumbs-up',
    'kbt' => 'lnr-file-preview',
    'kbt_templates' => 'lnr-file-preview',
    'kbt_beoordelingen' => 'lnr-star',

    // CGO
    'cgo' => 'lnr-file-preview',
    'cgo_template' => 'lnr-file-preview',
    'cgo_templates' => 'lnr-file-preview',

    // Documenten & bijlagen
    'documenten' => 'lnr-document',
    'bijlagen' => 'lnr-paperclip',
    'verslagen' => 'lnr-document',
    'verklaringen' => 'lnr-certificate',
    'benodigdheden' => 'lnr-box',
    'aanmeldingsdocumenten' => 'lnr-file-add',

    // Inventarisatie
    'inventarisatie' => 'lnr-inbox',
    'inventarisatiegroepsomschrijving' => 'lnr-inbox',
    'praktijkopleidingsinstellingen' => 'lnr-inbox',

    // Overig
    'rino_nieuws' => 'lnr-news',
    'rino_nieuws_redactie' => 'lnr-news',
    'algemene_info' => 'lnr-clipboard-user',
    'aankondigingen' => 'lnr-bullhorn',
    'gesprekstype' => 'lnr-group-work',
    'dispensatie' => 'lnr-checkmark-circle',
    'voordrachten' => 'lnr-site-map',
    'voordrachtenpraktijkopleiders' => 'lnr-site-map',
    'vrijstellingaanvragen' => 'lnr-checkmark-circle',
    'wijzigbare_systeemteksten' => 'lnr-papers',
    'snel_naar' => 'lnr-link',
    'auditlog' => 'lnr-clipboard-pencil',
    'iop' => 'lnr-file-check',

    // CMA systeem
    'contentblocks' => 'lnr-layers',
    'formdefinitions' => 'lnr-list',
    'marketingurl' => 'lnr-link',
    'cmamonitoring' => 'lnr-chart-bars',
];

// Menu group icons mapping (same as main.php)
$menuGroupIcons = [
    'dashboard' => 'lnr-home',
    'systeem' => 'lnr-cog',
    'beheer' => 'lnr-database',
    'content' => 'lnr-file-add',
    'rapportage' => 'lnr-document',
    'rapporten' => 'lnr-document',
    'rapportages' => 'lnr-document',
    'rapport' => 'lnr-document',
    'instellingen' => 'lnr-cog',
    'tools' => 'lnr-construction',
    'utilities' => 'lnr-construction',
    'formulieren' => 'lnr-layers',
    'opleidingen' => 'lnr-graduation-hat',
    'toetsing' => 'lnr-diploma',
    'toetsen' => 'lnr-file-check',
    'opdrachten' => 'lnr-file-check',
    'evaluaties' => 'lnr-thumbs-up',
    'rino info' => 'lnr-clipboard-user',
    'rino_info' => 'lnr-clipboard-user',
    'marketing_url' => 'lnr-link',
    'marketing' => 'lnr-link',
    'afspraken' => 'lnr-calendar-31',
    'literatuur' => 'lnr-book',
    'personen' => 'lnr-users2',
    'cgo' => 'lnr-file-preview',
    'gesprekken' => 'lnr-group-work',
    'servicebureau' => 'lnr-site-map',
    'menu' => 'lnr-menu',
    'zoektermen' => 'lnr-magnifier',
    'urls' => 'lnr-link',
    'materialen' => 'lnr-box',
    'artikelen' => 'lnr-file-empty',
    'nieuws' => 'lnr-news',
    'agenda' => 'lnr-calendar-31',
    'agendareserveringen' => 'lnr-calendar-31',
    'tijdsblokken' => 'lnr-calendar-31',
    'kalender' => 'lnr-calendar-31',
    'rooster' => 'lnr-calendar-31',
    'tags' => 'lnr-tag',
    'autos' => 'lnr-car',
    'taken' => 'lnr-clipboard-check',
    'teksten' => 'lnr-papers',
    'inventarisatie' => 'lnr-inbox',
    'audit' => 'lnr-clipboard-pencil',
    'audit_log' => 'lnr-clipboard-pencil',
    'documenten' => 'lnr-document',
];

// Build form -> menu group lookup from MenuService
// Include both formName (JSON forms) and form (legacy forms) properties
$formToMenuGroup = [];
$menuItems = MenuService::getAllItems();
foreach ($menuItems as $item) {
    $menuName = $item['menuName'] ?? '';
    if (empty($menuName)) continue;

    // Check formName (JSON forms)
    $formName = $item['formName'] ?? '';
    if (!empty($formName)) {
        $formToMenuGroup[strtolower($formName)] = strtolower($menuName);
    }

    // Also check form property (legacy forms)
    $legacyForm = $item['form'] ?? '';
    if (!empty($legacyForm)) {
        $formToMenuGroup[strtolower($legacyForm)] = strtolower($menuName);
    }
}

/**
 * Get icon for a form - checks form-specific icons first, then menu group icons
 */
function getFormIcon(string $formName, array $formIcons, array $formToMenuGroup, array $menuGroupIcons): string {
    $formNameLower = strtolower($formName);

    // Check form-specific icon first
    if (isset($formIcons[$formNameLower])) {
        return $formIcons[$formNameLower];
    }

    // Check if form has a menu group
    if (isset($formToMenuGroup[$formNameLower])) {
        $menuGroup = $formToMenuGroup[$formNameLower];
        if (isset($menuGroupIcons[$menuGroup])) {
            return $menuGroupIcons[$menuGroup];
        }
    }

    // Try parent form name (e.g. "deelnemers_bijlagen" → "deelnemers")
    // Split on underscore and try progressively shorter prefixes
    $parts = explode('_', $formNameLower);
    while (count($parts) > 1) {
        array_pop($parts);
        $parentName = implode('_', $parts);
        if (isset($formIcons[$parentName])) {
            return $formIcons[$parentName];
        }
    }

    // Default icon
    return 'lnr-file-empty';
}

// Build form definition cache for title lookups
$formDefCache = [];
$titleToNameMap = [];
$formDirs = [
    __DIR__ . '/../../assets/forms/',
    __DIR__ . '/../assets/forms/definitions/',
];
foreach ($formDirs as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob($dir . '*.json') as $file) {
        $fName = basename($file, '.json');
        if (str_starts_with($fName, '_')) continue;
        $formJson = @file_get_contents($file);
        $formDef = $formJson ? @json_decode($formJson, true) : null;
        if ($formDef) {
            $formDefCache[$fName] = $formDef;
            if (!empty($formDef['title'])) {
                $titleToNameMap[strtolower($formDef['title'])] = $fName;
            }
        }
    }
}

$response = [];

try {
    $conn = Database::getConnection('data');

    // Get top 10 most used forms for current user
    // Use Form (handle) preferentially, fallback to Formname for legacy records
    $usernameSafe = addslashes($username);
    $sql = "SELECT TOP 10 IIf(Form Is Null Or Form='', Formname, Form) AS FormHandle,
                   Count(*) AS cnt
            FROM tblCMAMonitoring
            WHERE Username = '{$usernameSafe}'
              AND (Form Is Not Null Or Formname Is Not Null)
              AND IIf(Form Is Null Or Form='', Formname, Form) <> ''
            GROUP BY IIf(Form Is Null Or Form='', Formname, Form)
            ORDER BY Count(*) DESC";

    $rs = Database::openRS($sql, $conn);
    if ($rs) {
        while (!$rs->EOF) {
            $formHandle = $rs->fields['FormHandle'];
            $count = (int)$rs->fields['cnt'];

            // Resolve display title from form definitions
            $formDef = $formDefCache[$formHandle] ?? null;
            if ($formDef) {
                $formName = $formDef['name'] ?? $formHandle;
                $title = $formDef['title'] ?? $formName;
            } else {
                // Try reverse lookup from title (legacy)
                $formNameLookup = $titleToNameMap[strtolower($formHandle)] ?? null;
                if ($formNameLookup) {
                    $formName = $formNameLookup;
                    $title = $formDefCache[$formName]['title'] ?? $formHandle;
                } else {
                    $formName = $formHandle;
                    $title = $formHandle;
                }
            }

            $icon = getFormIcon($formName, $formIcons, $formToMenuGroup, $menuGroupIcons);
            $response[] = [
                'name' => $formName,
                'title' => $title,
                'count' => $count,
                'icon' => $icon,
            ];
            $rs->MoveNext();
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    Logger::error('API user_forms error', [
        'message' => $e->getMessage(),
        'username' => $username ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
