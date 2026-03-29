<?php
/**
 * Validate parentField values exist in database tables
 *
 * This tool checks all subform parentField values against actual database columns.
 */
require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;
use App\Library\Response;
use Cma\SecurityHelper;

Response::noCache();

// Check access
if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    die("Toegang geweigerd - alleen admins");
}

// Get data connection
$conn = Database::getConnection('data');
if (!$conn) {
    die("Kan geen verbinding maken met database");
}

// Collect form data
$formsDir = dirname(__DIR__, 2) . '/assets/forms';
$formIdToData = [];
$subformsToCheck = [];

// First pass: build map of sourceFormId -> form data
foreach (glob($formsDir . '/*.json') as $file) {
    if (strpos($file, '.schema.json') !== false) continue;

    $data = json_decode(file_get_contents($file), true);
    if (!$data) continue;

    $sourceId = $data['sourceFormId'] ?? null;
    if ($sourceId) {
        $formIdToData[$sourceId] = [
            'file' => basename($file),
            'table' => $data['table'] ?? '',
            'name' => $data['name'] ?? ''
        ];
    }
}

// Second pass: collect subforms to check
foreach (glob($formsDir . '/*.json') as $file) {
    if (strpos($file, '.schema.json') !== false) continue;

    $data = json_decode(file_get_contents($file), true);
    if (!$data || !isset($data['subforms'])) continue;

    foreach ($data['subforms'] as $subform) {
        if (!isset($subform['parentField'])) continue;

        $subformData = null;
        if (isset($subform['sourceFormId'])) {
            $subformData = $formIdToData[$subform['sourceFormId']] ?? null;
        }

        $subformsToCheck[] = [
            'parentForm' => basename($file),
            'subformTitle' => $subform['title'] ?? 'untitled',
            'table' => $subformData['table'] ?? null,
            'parentField' => $subform['parentField'],
            'sourceFormId' => $subform['sourceFormId'] ?? null
        ];
    }
}

// Check each parentField exists in database
$results = [
    'valid' => [],
    'invalid' => [],
    'noTable' => [],
    'errors' => []
];

$checkedColumns = []; // Cache column checks

foreach ($subformsToCheck as $check) {
    $table = $check['table'];
    $field = $check['parentField'];

    if (!$table) {
        $results['noTable'][] = $check;
        continue;
    }

    $cacheKey = $table . '.' . $field;

    // Check cache first
    if (isset($checkedColumns[$cacheKey])) {
        if ($checkedColumns[$cacheKey]) {
            $results['valid'][] = $check;
        } else {
            $results['invalid'][] = $check;
        }
        continue;
    }

    // Query to check if column exists
    try {
        $sql = "SELECT TOP 1 [$field] FROM [$table]";

        // Clear any previous errors
        error_clear_last();

        // Suppress warnings to catch them manually
        $rs = @Database::openRS($sql, $conn, 0);

        $lastError = error_get_last();

        if ($rs !== null && $rs !== false) {
            $checkedColumns[$cacheKey] = true;
            $results['valid'][] = $check;
        } else {
            $checkedColumns[$cacheKey] = false;
            $check['sqlError'] = $lastError['message'] ?? 'Query returned null/false';
            $check['sql'] = $sql;
            $results['invalid'][] = $check;
        }
    } catch (Exception $e) {
        $checkedColumns[$cacheKey] = false;
        $check['sqlError'] = $e->getMessage();
        $check['sql'] = $sql;
        $results['invalid'][] = $check;
        $results['errors'][] = "$table.$field: " . $e->getMessage();
    }
}

// Count unique valid/invalid columns
$uniqueValid = [];
$uniqueInvalid = [];
foreach ($results['valid'] as $v) {
    $uniqueValid[$v['table'] . '.' . $v['parentField']] = true;
}
foreach ($results['invalid'] as $v) {
    $uniqueInvalid[$v['table'] . '.' . $v['parentField']] = true;
}

// Output HTML
cma_html_header('Validatie parentField waarden');
?>
<body class="contentbody tools">
<div id="c">
    <h2>Validatie parentField waarden</h2>
    <p>Controleert of alle parentField waarden bestaan als kolommen in de database.</p>

    <h3>Samenvatting</h3>
    <ul>
        <li><strong style="color:green">Geldig:</strong> <?= count($uniqueValid) ?> unieke kolommen (<?= count($results['valid']) ?> subforms)</li>
        <li><strong style="color:red">Ongeldig:</strong> <?= count($uniqueInvalid) ?> unieke kolommen (<?= count($results['invalid']) ?> subforms)</li>
        <li><strong style="color:orange">Geen tabel:</strong> <?= count($results['noTable']) ?> subforms (form definitie niet gevonden)</li>
    </ul>

    <?php if (!empty($results['invalid'])): ?>
    <h3 style="color:red">Ongeldige parentField waarden</h3>
    <p>Deze kolommen bestaan NIET in de database:</p>
    <table class="datatable">
        <thead>
            <tr>
                <th>Tabel</th>
                <th>parentField</th>
                <th>Parent Form</th>
                <th>Subform</th>
                <th>SQL Fout</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results['invalid'] as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['table']) ?></td>
                <td style="color:red;font-weight:bold"><?= htmlspecialchars($item['parentField']) ?></td>
                <td><?= htmlspecialchars($item['parentForm']) ?></td>
                <td><?= htmlspecialchars($item['subformTitle']) ?></td>
                <td style="font-size:var(--font-size-xs)"><?= htmlspecialchars($item['sqlError'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <lib-message type="success">Alle parentField waarden zijn geldig!</lib-message>
    <?php endif; ?>

    <?php if (!empty($results['noTable'])): ?>
    <h3 style="color:orange">Subforms zonder tabel (form definitie ontbreekt)</h3>
    <table class="datatable">
        <thead>
            <tr>
                <th>Parent Form</th>
                <th>Subform</th>
                <th>sourceFormId</th>
                <th>parentField</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results['noTable'] as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['parentForm']) ?></td>
                <td><?= htmlspecialchars($item['subformTitle']) ?></td>
                <td><?= htmlspecialchars($item['sourceFormId'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['parentField']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h3 style="color:green">Geldige parentField waarden (<?= count($uniqueValid) ?> uniek)</h3>
    <details>
        <summary>Toon alle geldige kolommen</summary>
        <table class="datatable">
            <thead>
                <tr>
                    <th>Tabel</th>
                    <th>parentField</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $shown = [];
            foreach ($results['valid'] as $item):
                $key = $item['table'] . '.' . $item['parentField'];
                if (isset($shown[$key])) continue;
                $shown[$key] = true;
            ?>
                <tr>
                    <td><?= htmlspecialchars($item['table']) ?></td>
                    <td style="color:green"><?= htmlspecialchars($item['parentField']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>

    <?php if (!empty($results['errors'])): ?>
    <h3>Database fouten</h3>
    <ul>
        <?php foreach ($results['errors'] as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p style="margin-top:20px">
        <a href="../tools.php" class="btn">Terug naar Tools</a>
    </p>
</div>
</body>
</html>
