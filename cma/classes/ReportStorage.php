<?php

namespace Cma;

use App\Library\Arr;

/**
 * Report Storage
 *
 * Manages saving and loading of report definitions as JSON files.
 * Reports can be personal (per user) or global (shared).
 */
class ReportStorage
{
    /**
     * Base directory for report storage (outside /cma/ so updates don't overwrite user data)
     */
    private const REPORTS_DIR = __DIR__ . '/../../data/reports';

    /** @var string|null Other base directory (tests); null = the site's data/reports */
    private static ?string $baseDirOverride = null;

    /** Use another base directory for reports (tests), or null for the default. */
    public static function useDirectory(?string $dir): void
    {
        self::$baseDirOverride = $dir === null ? null : rtrim(str_replace('\\', '/', $dir), '/');
    }

    private static function baseDir(): string
    {
        return self::$baseDirOverride ?? self::REPORTS_DIR;
    }

    /**
     * Global reports subdirectory
     */
    private const GLOBAL_DIR = 'global';

    /**
     * Personal reports subdirectory
     */
    private const PERSONAL_DIR = 'personal';

    /**
     * Save a report definition
     *
     * @param array $definition Report definition
     * @param string|null $userId User ID for personal reports
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null]
     */
    public static function save(array $definition, ?string $userId = null): array
    {
        // Generate or use existing ID
        // The id from the browser ends up in a file name: keep only safe characters (an id that is all "../" becomes empty and gets a new one)
        $id = self::sanitizeId((string) ($definition['id'] ?? ''));
        if ($id === '') {
            $id = self::generateId();
        }
        $definition['id'] = $id;

        // Determine if global or personal
        $isGlobal = $definition['isGlobal'] ?? false;

        // Set metadata
        $definition['updatedAt'] = date('Y-m-d H:i:s');
        if (!isset($definition['createdAt'])) {
            $definition['createdAt'] = $definition['updatedAt'];
        }

        if (!$isGlobal && $userId !== null) {
            $definition['createdBy'] = $userId;
        }

        // Validate required fields
        if (empty($definition['name'])) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Rapportnaam is verplicht'
            ];
        }

        // Get file path
        $filePath = self::getPath($id, $isGlobal, $userId);
        $dir = dirname($filePath);

        // Create directory if needed
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => 'Kan map niet aanmaken: ' . $dir
                ];
            }
        }

        // Save JSON file
        $json = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Kan rapport niet naar JSON converteren'
            ];
        }

        // Write to a temporary file and rename it over the report: a crash or a second save at the same moment can no longer leave
        // a half-written (unreadable) report behind.
        $tmp = $filePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $filePath)) {
            @unlink($tmp);
            return [
                'success' => false,
                'id' => null,
                'error' => 'Kan rapport niet opslaan'
            ];
        }

        return [
            'success' => true,
            'id' => $id,
            'path' => $filePath,
            'error' => null
        ];
    }

    /**
     * Load a report definition by ID
     *
     * @param string $id Report ID
     * @param string|null $userId User ID for personal reports
     * @return array|null Report definition or null if not found
     */
    public static function load(string $id, ?string $userId = null): ?array
    {
        // Try global first
        $globalPath = self::getPath($id, true, null);
        if (file_exists($globalPath)) {
            $json = file_get_contents($globalPath);
            $definition = json_decode($json, true);
            if (Arr::isArray($definition)) {
                $definition['isGlobal'] = true;
                return $definition;
            }
        }

        // Try personal
        if ($userId !== null) {
            $personalPath = self::getPath($id, false, $userId);
            if (file_exists($personalPath)) {
                $json = file_get_contents($personalPath);
                $definition = json_decode($json, true);
                if (Arr::isArray($definition)) {
                    $definition['isGlobal'] = false;
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * List all available reports for a user
     *
     * @param string|null $userId User ID
     * @param bool $includeGlobal Include global reports
     * @return array Array of report summaries
     */
    public static function list(?string $userId = null, bool $includeGlobal = true): array
    {
        $reports = [];

        // List global reports
        if ($includeGlobal) {
            $globalDir = self::baseDir() . '/' . self::GLOBAL_DIR;
            if (is_dir($globalDir)) {
                $files = glob($globalDir . '/*.json');
                foreach ($files as $file) {
                    $report = self::loadSummary($file);
                    if ($report !== null) {
                        $report['isGlobal'] = true;
                        $reports[] = $report;
                    }
                }
            }
        }

        // List personal reports
        if ($userId !== null) {
            $personalDir = self::baseDir() . '/' . self::PERSONAL_DIR . '/' . self::sanitizeUserId($userId);
            if (is_dir($personalDir)) {
                $files = glob($personalDir . '/*.json');
                foreach ($files as $file) {
                    $report = self::loadSummary($file);
                    if ($report !== null) {
                        $report['isGlobal'] = false;
                        $reports[] = $report;
                    }
                }
            }
        }

        // Sort by name
        usort($reports, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return $reports;
    }

    /**
     * Delete a report
     *
     * @param string $id Report ID
     * @param string|null $userId User ID
     * @param bool $isGlobal Whether this is a global report
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function delete(string $id, ?string $userId = null, bool $isGlobal = false): array
    {
        $filePath = self::getPath($id, $isGlobal, $userId);

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Rapport niet gevonden'
            ];
        }

        if (!unlink($filePath)) {
            return [
                'success' => false,
                'error' => 'Kan rapport niet verwijderen'
            ];
        }

        return [
            'success' => true,
            'error' => null
        ];
    }

    /**
     * Check if a report exists
     *
     * @param string $id Report ID
     * @param string|null $userId User ID
     * @return bool
     */
    public static function exists(string $id, ?string $userId = null): bool
    {
        // Check global
        if (file_exists(self::getPath($id, true, null))) {
            return true;
        }

        // Check personal
        if ($userId !== null && file_exists(self::getPath($id, false, $userId))) {
            return true;
        }

        return false;
    }

    /**
     * Get file path for a report
     *
     * @param string $id Report ID
     * @param bool $isGlobal Whether this is a global report
     * @param string|null $userId User ID for personal reports
     * @return string
     */
    public static function getPath(string $id, bool $isGlobal, ?string $userId): string
    {
        $sanitizedId = self::sanitizeId($id);

        if ($isGlobal) {
            return self::baseDir() . '/' . self::GLOBAL_DIR . '/' . $sanitizedId . '.json';
        }

        $sanitizedUser = self::sanitizeUserId($userId ?? 'anonymous');
        return self::baseDir() . '/' . self::PERSONAL_DIR . '/' . $sanitizedUser . '/' . $sanitizedId . '.json';
    }

    /**
     * Generate a unique report ID
     *
     * @return string
     */
    private static function generateId(): string
    {
        // Generate UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Sanitize report ID for filesystem
     *
     * @param string $id
     * @return string
     */
    private static function sanitizeId(string $id): string
    {
        // Allow only alphanumeric, dash, underscore
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $id);
    }

    /**
     * Sanitize user ID for filesystem
     *
     * @param string $userId
     * @return string
     */
    private static function sanitizeUserId(string $userId): string
    {
        // Allow only alphanumeric, dash, underscore
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $userId);
    }

    /**
     * Load only summary fields from a report file
     *
     * @param string $filePath
     * @return array|null
     */
    private static function loadSummary(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $json = file_get_contents($filePath);
        $full = json_decode($json, true);

        if (!Arr::isArray($full)) {
            return null;
        }

        // Return only summary fields
        return [
            'id' => $full['id'] ?? basename($filePath, '.json'),
            'name' => $full['name'] ?? '',
            'description' => $full['description'] ?? '',
            'mode' => $full['mode'] ?? 'quick',
            'database' => $full['database'] ?? '',
            'createdBy' => $full['createdBy'] ?? '',
            'createdAt' => $full['createdAt'] ?? '',
            'updatedAt' => $full['updatedAt'] ?? ''
        ];
    }

    /**
     * Duplicate a report
     *
     * @param string $sourceId Source report ID
     * @param string $newName New report name
     * @param string|null $userId User ID
     * @param bool $asGlobal Save as global
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null]
     */
    public static function duplicate(string $sourceId, string $newName, ?string $userId = null, bool $asGlobal = false): array
    {
        $source = self::load($sourceId, $userId);
        if ($source === null) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Bronrapport niet gevonden'
            ];
        }

        // Create copy with new ID and name
        unset($source['id']);
        unset($source['createdAt']);
        unset($source['updatedAt']);

        $source['name'] = $newName;
        $source['isGlobal'] = $asGlobal;

        return self::save($source, $userId);
    }
}
