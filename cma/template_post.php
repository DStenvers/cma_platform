<?php
/**
 * Save the editable regions of a site page (posted by template_edit.php).
 *
 * The file is looked up again through tblSiteFiles (the posted ID); the
 * posted field values go into the regions with TemplateService::render(),
 * everything outside the regions stays as it was, datestamps get today.
 * The index title follows the page's <title> so the list stays right.
 * Afterwards back to the editor, which shows the saved state.
 */

use App\Library\Application;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Str;
use Cma\Services\TemplateService;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/classes/Services/TemplateService.php';

// Editing site pages is an administrator's job: it writes files in the webroot.
if (!\Cma\SecurityHelper::isAdmin()) {
    \App\Library\Error::page('Geen toegang', 'Alleen beheerders kunnen pagina\'s wijzigen.', true);
    exit;
}

Response::noCache();

if (Request::method() !== 'POST') {
    Error::page('Sjabloon opslaan', 'Deze pagina verwacht een formulier (POST).', true);
    exit;
}

$id = Request::postInt(CONSTIDFLD);
$row = $id > 0 ? Database::executeSingleRecord('SELECT ID, FilePath, FileName FROM tblSiteFiles WHERE ID = ?', [$id]) : null;
if (!$row) {
    Error::page('Sjabloon opslaan', 'Sjabloon ' . $id . ' staat niet in de lijst.', true);
    exit;
}

$relative = Str::trim((string) $row['FilePath']) . Str::trim((string) $row['FileName']);
$file = Server::mapPath(Application::get('base_path', '/') . ltrim($relative, '/'));
if (!is_file($file)) {
    Error::page('Sjabloon opslaan', 'Bestand niet gevonden: ' . htmlspecialchars($relative) . '.', true);
    exit;
}
if (!is_writable($file)) {
    Error::page('Sjabloon opslaan', 'Bestand is alleen-lezen: ' . htmlspecialchars($relative) . '.', true);
    exit;
}

$missing = lib_FormValRequired();
if ($missing !== '') {
    Error::page('Sjabloon opslaan', $missing, true);
    exit;
}

$content = file_get_contents($file);
if ($content === false) {
    Error::page('Sjabloon opslaan', 'Bestand kon niet worden gelezen: ' . htmlspecialchars($relative) . '.', true);
    exit;
}

$values = [];
foreach (TemplateService::parse($content) as $region) {
    if ($region['type'] !== 'datestamp') {
        $values[$region['field']] = (string) Request::post($region['field'], '');
    }
}
$updated = TemplateService::render($content, $values);

if (file_put_contents($file, $updated) === false) {
    Error::page('Sjabloon opslaan', 'Bestand kon niet worden geschreven: ' . htmlspecialchars($relative) . '.', true);
    exit;
}

Database::execute('UPDATE tblSiteFiles SET FileTitle = ? WHERE ID = ?', [TemplateService::title($updated, (string) $row['FileName']), $id]);

Response::redirect('template_edit.php?ID=' . $id);
