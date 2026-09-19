<?php

namespace Cma\Services;

use App\Library\Database;
use App\Library\SQL;
use Cma\JsonFormLoader;

/**
 * Virtual directories from "directory" fields.
 *
 * A field of type "directory" makes every record reachable as a URL: the field
 * value is the directory name (uppercased, no illegal characters, unique — see
 * FormDataProvider::validateJsonFormData). The old library/lib_404.inc served
 * the field's `dirTemplate` (with [ID] and other [field] placeholders replaced
 * by the record) at that URL from the 404 handler; `dirFilename` names the
 * file the template stands for (e.g. index.php), informational only.
 *
 * Usage from a 404 handler, after the platform bootstrap:
 *
 *   $content = \Cma\Services\DirectoryUrlService::resolve($requestedPath);
 *   if ($content !== null) { http_response_code(200); echo $content; exit; }
 */
class DirectoryUrlService
{
    /**
     * Content for a not-found path, or null when no directory record matches.
     * Both the last path segment ("/aanmelden/" -> AANMELDEN) and the full
     * path without slashes are tried, as lib_404.inc did.
     */
    public static function resolve(string $path): ?string
    {
        $path = trim((string) strtok($path, '?'));
        $path = trim($path, '/');
        if ($path === '' || preg_match('/[\x00-\x1f]/', $path)) {
            return null;
        }
        $candidates = array_values(array_unique([$path, basename($path)]));

        foreach (self::directoryFields() as $spec) {
            $conn = ListServiceHelper::getJsonFormConnection($spec['database']);
            if ($conn === null) {
                continue;
            }
            foreach ($candidates as $dir) {
                $sql = 'SELECT * FROM [' . $spec['table'] . '] WHERE LCASE([' . $spec['field'] . ']) = ' . SQL::postString(strtolower($dir));
                try {
                    $rs = Database::openRS($sql, $conn);
                } catch (\Throwable $e) {
                    $rs = null;
                }
                if ($rs === null || $rs->EOF) {
                    continue;
                }
                $row = $rs->fetchAssoc();
                return self::fillTemplate($spec['template'], $row, $spec['idField']);
            }
        }
        return null;
    }

    /**
     * Every directory field over all form definitions: table, database, field,
     * idField and its dirTemplate.
     */
    public static function directoryFields(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        foreach (JsonFormLoader::listAllForms() as $formName) {
            $def = JsonFormLoader::loadRaw($formName);
            if ($def === null || empty($def['table'])) {
                continue;
            }
            foreach ($def['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'directory' || empty($field['name'])) {
                    continue;
                }
                $cache[] = [
                    'form' => $formName,
                    'table' => $def['table'],
                    'database' => $def['database'] ?? 'data',
                    'idField' => $def['idField'] ?? 'ID',
                    'field' => $field['name'],
                    'template' => (string) ($field['dirTemplate'] ?? ''),
                    'filename' => (string) ($field['dirFilename'] ?? ''),
                ];
            }
        }
        return $cache;
    }

    /**
     * [ID] and [fieldname] placeholders in the template become the record's values.
     */
    public static function fillTemplate(string $template, array $row, string $idField = 'ID'): string
    {
        if ($template === '') {
            return '';
        }
        $lower = [];
        foreach ($row as $key => $value) {
            if (!is_int($key)) {
                $lower[strtolower((string) $key)] = (string) ($value ?? '');
            }
        }
        $template = str_ireplace('[ID]', $lower[strtolower($idField)] ?? '', $template);
        return preg_replace_callback('/\[([a-z_][a-z0-9_]*)\]/i', function ($m) use ($lower) {
            $key = strtolower($m[1]);
            return array_key_exists($key, $lower) ? htmlspecialchars($lower[$key], ENT_QUOTES, 'UTF-8') : $m[0];
        }, $template);
    }
}
