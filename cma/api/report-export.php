<?php
/**
 * Report Export API
 *
 * Exports report data to various formats.
 *
 * Endpoints:
 * - POST ?action=csv - Export to CSV
 * - POST ?action=excel - Export to Excel
 * - POST ?action=html - Export to HTML (Word-compatible)
 * - GET ?action=formats - Get available export formats
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Arr;
use App\Library\Response;
use App\Library\Request;
use App\Library\Database;
use Cma\SecurityHelper;
use Cma\QueryBuilder;
use Cma\ReportStorage;
use Cma\ReportExporter;
use Cma\CmaRepository;
use Cma\ConfigLoader;

// Set Content-Type early to prevent debug/profiler output from corrupting response
header('Content-Type: application/json; charset=utf-8');

// Security check - require valid login
if (!SecurityHelper::isLoggedIn()) {
    if (Request::query('action', '') === 'formats') {
        Response::json(['success' => false, 'error' => 'Niet ingelogd'], 401);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Niet ingelogd';
    }
    exit;
}

$action = Request::post('action', Request::query('action', ''));

switch ($action) {
    case 'csv':
        handleExport(ReportExporter::FORMAT_CSV);
        break;

    case 'excel':
        handleExport(ReportExporter::FORMAT_EXCEL);
        break;

    case 'json':
        handleExport(ReportExporter::FORMAT_JSON);
        break;

    case 'html':
        handleExport(ReportExporter::FORMAT_HTML);
        break;

    case 'formats':
        handleGetFormats();
        break;

    default:
        Response::noCache();
        header('Content-Type: application/json; charset=utf-8');
        Response::json(['success' => false, 'error' => 'Onbekende actie: ' . $action], 400);
}

/**
 * Maximum records for non-CSV export formats
 */
const MAX_ROWS_FOR_FULL_EXPORT = 15000;

/**
 * Handle export request
 */
function handleExport(string $format): void
{
    // Get report definition (either from body or by ID)
    $definition = getDefinitionFromRequest();
    $reportId = Request::post('reportId', Request::query('reportId', ''));

    // If report ID is provided, load the definition
    if (empty($definition) && !empty($reportId)) {
        $userId = SecurityHelper::getCurrentUserId();
        $definition = ReportStorage::load($reportId, $userId);

        if ($definition === null) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Rapport niet gevonden';
            exit;
        }
    }

    if ($definition === null) {
        Response::noCache();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Rapport definitie is verplicht';
        exit;
    }

    // Get database ID
    $databaseId = $definition['database'] ?? 0;
    if (!is_numeric($databaseId) || (int)$databaseId <= 0) {
        Response::noCache();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Database ID is verplicht';
        exit;
    }

    // Get parameters
    $parameters = [];
    if (isset($definition['parameters'])) {
        $parameters = getParameterValues($definition['parameters']);
    }

    try {
        // Get connection using connection name for proper pooling
        $dbConfig = ConfigLoader::getDatabase((int)$databaseId);
        $connName = $dbConfig['name'] ?? null;

        if ($connName) {
            $conn = Database::getConnection($connName);
        } else {
            $resolvedConnStr = CmaRepository::getResolvedConnectionString((int)$databaseId);
            $conn = Database::getConnection($resolvedConnStr);
        }

        if ($conn === null) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Kan geen verbinding maken met database';
            exit;
        }

        // Build query
        $builder = new QueryBuilder($definition, $conn);
        $builder->setParameters($parameters);

        // For non-CSV formats, check row count first
        if ($format !== ReportExporter::FORMAT_CSV) {
            $countResult = $builder->executeCount();
            if ($countResult['success'] && ($countResult['count'] ?? 0) > MAX_ROWS_FOR_FULL_EXPORT) {
                Response::noCache();
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Bij meer dan ' . number_format(MAX_ROWS_FOR_FULL_EXPORT, 0, ',', '.') . ' records is alleen CSV export beschikbaar. Dit rapport bevat ' . number_format($countResult['count'], 0, ',', '.') . ' records.';
                exit;
            }
        }

        // Get filename
        $filename = $definition['name'] ?? 'rapport';
        $filename = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $filename);
        if (empty($filename)) {
            $filename = 'rapport';
        }

        // Add date to filename
        $filename .= '_' . date('Y-m-d');

        // Export using builder
        ReportExporter::exportFromBuilder($builder, $format, $filename, $definition['name'] ?? '');

    } catch (\Exception $e) {
        Response::noCache();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fout bij exporteren: ' . $e->getMessage();
        exit;
    }
}

/**
 * Get available export formats
 */
function handleGetFormats(): void
{
    Response::noCache();
    header('Content-Type: application/json; charset=utf-8');

    $formats = ReportExporter::getAvailableFormats();

    Response::json([
        'success' => true,
        'formats' => $formats
    ]);
}

/**
 * Get report definition from request body
 */
function getDefinitionFromRequest(): ?array
{
    // Try JSON body first
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (Arr::isArray($json)) {
            // Definition might be the whole body or in a 'definition' key
            if (isset($json['definition']) && Arr::isArray($json['definition'])) {
                return $json['definition'];
            }
            // Check if it looks like a definition (has 'name' or 'tables')
            if (isset($json['name']) || isset($json['tables'])) {
                return $json;
            }
        }
    }

    // Try POST parameter
    $definitionJson = Request::post('definition', '');
    if (!empty($definitionJson)) {
        $definition = json_decode($definitionJson, true);
        if (Arr::isArray($definition)) {
            return $definition;
        }
    }

    return null;
}

/**
 * Extract parameter values from runtime input or default values
 */
function getParameterValues(array $parameterDefinitions): array
{
    $values = [];

    // Check for runtime values in request
    $runtimeValues = [];
    $runtimeJson = Request::post('parameterValues', '');
    if (!empty($runtimeJson)) {
        $runtimeValues = json_decode($runtimeJson, true) ?? [];
    }

    foreach ($parameterDefinitions as $param) {
        $name = $param['name'] ?? '';
        if (empty($name)) {
            continue;
        }

        // Remove @ prefix if present for lookup
        $lookupName = ltrim($name, '@');

        // Use runtime value if provided, otherwise use default
        if (isset($runtimeValues[$lookupName])) {
            $values[$lookupName] = $runtimeValues[$lookupName];
        } elseif (isset($runtimeValues[$name])) {
            $values[$lookupName] = $runtimeValues[$name];
        } elseif (isset($param['default'])) {
            $values[$lookupName] = resolveDefaultValue($param['default']);
        } else {
            $values[$lookupName] = '';
        }
    }

    return $values;
}

/**
 * Resolve default value placeholders
 */
function resolveDefaultValue(string $default): string
{
    switch (strtolower($default)) {
        case 'vandaag':
        case 'today':
            return date('Y-m-d');

        case 'vandaag-7':
            return date('Y-m-d', strtotime('-7 days'));

        case 'vandaag-30':
            return date('Y-m-d', strtotime('-30 days'));

        case 'vandaag-90':
            return date('Y-m-d', strtotime('-90 days'));

        case 'vandaag+7':
            return date('Y-m-d', strtotime('+7 days'));

        case 'vandaag+30':
            return date('Y-m-d', strtotime('+30 days'));

        case 'eerstevandemaand':
        case 'firstofmonth':
            return date('Y-m-01');

        case 'laatstevandemaand':
        case 'lastofmonth':
            return date('Y-m-t');

        default:
            return $default;
    }
}
