<?php
/**
 * Edit the editable regions of one site page (a "template").
 *
 * The page is found through tblSiteFiles (ID); its regions come from
 * TemplateService::parse(). One control per region: an input for text and
 * title, a textarea for textarea/keyword/description, a CKEditor when the
 * region says html=yes. Datestamps are not shown; template_post.php writes
 * them. The form posts to template_post.php through the standard record
 * toolbar (tb_DoSave), which only submits a form that changed and validates
 * required- fields first.
 */

use App\Library\Application;
use App\Library\Database;
use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use App\Library\Settings;
use App\Library\Str;
use Cma\Services\TemplateService;
use Cma\ToolbarHelper;

require_once __DIR__ . '/bootstrap.inc';
require_once __DIR__ . '/classes/Services/TemplateService.php';

// Editing site pages is an administrator's job: it writes files in the webroot.
if (!\Cma\SecurityHelper::isAdmin()) {
    \App\Library\Error::page('Geen toegang', 'Alleen beheerders kunnen pagina\'s wijzigen.', true);
    exit;
}

Response::noCache();

$id = Request::queryInt('ID');
if ($id <= 0) {
    Error::page('Sjabloon', 'Geen sjabloon opgegeven (ID ontbreekt).', true);
    exit;
}

$row = Database::executeSingleRecord('SELECT ID, FilePath, FileName, FileTitle FROM tblSiteFiles WHERE ID = ?', [$id]);
if (!$row) {
    Error::page('Sjabloon', 'Sjabloon ' . $id . ' staat niet in de lijst; vernieuw de lijst met wijzigbare pagina\'s.', true);
    exit;
}

$relative = Str::trim((string) $row['FilePath']) . Str::trim((string) $row['FileName']);
$url = Application::get('base_path', '/') . ltrim($relative, '/');
$file = Server::mapPath($url);
if (!is_file($file)) {
    Error::page('Sjabloon', 'Bestand niet gevonden: ' . htmlspecialchars($relative) . '. Vernieuw de lijst met wijzigbare pagina\'s.', true);
    exit;
}
$content = file_get_contents($file);
$regions = $content === false ? [] : TemplateService::parse($content);
$title = TemplateService::title((string) $content, (string) $row['FileTitle']);

cma_html_header('Sjabloon: ' . $title);
cma_script('webcomponents/cma-htmledit.js');
ToolbarHelper::writeJS();
?>
<body class="contentbody tools" onload="clearaction()" onunload="checkchanged()">
<form name="main" id="main" action="template_post.php" method="post" onsubmit="return form_valid(this)">
<?php
ToolbarHelper::start(true);
ToolbarHelper::recordButtons('0', false, is_writable($file), false, false, false, false);
ToolbarHelper::separator();
ToolbarHelper::button($url, 'lnr-eye', true, 'Bekijk', 'Bekijk de pagina', '', '', true);
ToolbarHelper::end();
?>
<input type="hidden" name="<?= CONSTIDFLD ?>" value="<?= (int) $row['ID'] ?>">
<input type="hidden" id="actie" name="Actie" value="">
<div id="c" class="tools">
    <p class="cma-tool__hint"><?= htmlspecialchars($relative) ?><?php if (!is_writable($file)): ?> <lib-label type="warning">alleen-lezen</lib-label><?php endif; ?></p>
<?php if ($regions === []): ?>
    <lib-message type="info">Deze pagina heeft geen wijzigbare gebieden (<code>&lt;!-- #beginedit … --&gt;</code>).</lib-message>
<?php else: ?>
    <table class="form-table preferences-table">
<?php foreach ($regions as $region):
    if ($region['type'] === 'datestamp') {
        continue;
    }
    $label = ucfirst($region['name']);
    $field = $region['field'];
?>
        <tr>
            <td class="label-cell"><label for="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($label) ?><?= $region['required'] ? ' <span class="cma-tool__strong" title="Verplicht">*</span>' : '' ?></label></td>
            <td class="input-cell">
<?php if ($region['type'] === 'text' || $region['type'] === 'title'): ?>
                <input type="text" class="form-control" id="<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" data-label="<?= htmlspecialchars($label) ?>" size="<?= min(70, $region['size']) ?>" maxlength="<?= $region['size'] ?>" value="<?= htmlspecialchars($region['value']) ?>">
<?php elseif ($region['html']): ?>
                <cma-htmledit name="<?= htmlspecialchars($field) ?>" height="<?= $region['height'] * 40 ?>" custom-css="<?= htmlspecialchars((string) Settings::get('editor_css')) ?>"<?= Settings::get('editor_allow_br') ? ' allow-br' : '' ?>>
                    <textarea name="<?= htmlspecialchars($field) ?>" data-label="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($region['value']) ?></textarea>
                </cma-htmledit>
<?php else: ?>
                <textarea class="form-control" id="<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" data-label="<?= htmlspecialchars($label) ?>" rows="<?= $region['height'] ?>" cols="78"><?= htmlspecialchars($region['value']) ?></textarea>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</div>
</form>
</body>
</html>
