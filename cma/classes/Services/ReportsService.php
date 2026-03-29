<?php

namespace Cma\Services;

/**
 * Service for loading report definitions from JSON configuration
 */
class ReportsService
{
    private static ?array $reports = null;
    private static string $configPath = __DIR__ . '/../../../data/reports.json';

    /**
     * Get all reports from JSON config
     *
     * @param bool $visibleOnly Only return visible reports
     * @return array
     */
    public static function getAll(bool $visibleOnly = true): array
    {
        self::loadReports();

        if (!$visibleOnly) {
            return self::$reports;
        }

        return array_filter(self::$reports, fn($r) => $r['visible'] ?? true);
    }

    /**
     * Get a report by ID
     *
     * @param int $id Report ID
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        self::loadReports();

        foreach (self::$reports as $report) {
            if ((int)$report['id'] === $id) {
                return $report;
            }
        }

        return null;
    }

    /**
     * Get reports grouped by module
     *
     * @param bool $visibleOnly Only return visible reports
     * @return array Grouped by module name
     */
    public static function getGroupedByModule(bool $visibleOnly = true): array
    {
        $reports = self::getAll($visibleOnly);
        $grouped = [];

        foreach ($reports as $report) {
            $module = $report['module'] ?? 'Overig';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $report;
        }

        // Sort modules alphabetically
        ksort($grouped);

        return $grouped;
    }

    /**
     * Get reports as options for dropdown/checklist
     * Returns array with id, title, and module for display
     *
     * @param bool $visibleOnly Only return visible reports
     * @return array
     */
    public static function getAsOptions(bool $visibleOnly = true): array
    {
        $reports = self::getAll($visibleOnly);
        $options = [];

        foreach ($reports as $report) {
            $options[] = [
                'id' => $report['id'],
                'title' => $report['title'],
                'module' => $report['module'] ?? '',
            ];
        }

        // Sort by module, then title
        usort($options, function($a, $b) {
            $moduleCompare = strcasecmp($a['module'], $b['module']);
            if ($moduleCompare !== 0) {
                return $moduleCompare;
            }
            return strcasecmp($a['title'], $b['title']);
        });

        return $options;
    }

    /**
     * Get sub-reports for a parent report
     *
     * @param int $parentReportId Parent report ID
     * @return array
     */
    public static function getSubReports(int $parentReportId): array
    {
        self::loadReports();

        return array_filter(self::$reports, function($r) use ($parentReportId) {
            return (int)($r['parentReportId'] ?? 0) === $parentReportId;
        });
    }

    /**
     * Get database ID for a report
     *
     * @param int $reportId Report ID
     * @return int|null
     */
    public static function getDatabaseId(int $reportId): ?int
    {
        $report = self::getById($reportId);
        if ($report === null) {
            return null;
        }
        return $report['databaseId'] ?? 6; // Default to database 6 (main)
    }

    /**
     * Update a report by ID in the JSON config file
     *
     * @param int $id Report ID
     * @param array $updates Fields to update (merged with existing)
     * @return bool True on success
     */
    public static function updateById(int $id, array $updates): bool
    {
        $configPath = self::$configPath;
        if (!file_exists($configPath)) {
            return false;
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['reports'])) {
            return false;
        }

        $found = false;
        foreach ($data['reports'] as &$report) {
            if ((int)$report['id'] === $id) {
                foreach ($updates as $key => $value) {
                    $report[$key] = $value;
                }
                $found = true;
                break;
            }
        }
        unset($report);

        if (!$found) {
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $result = file_put_contents($configPath, $json);
        if ($result === false) {
            return false;
        }

        // Clear cache so changes take effect immediately
        self::clearCache();
        return true;
    }

    /**
     * Get the raw JSON content of the reports config file
     *
     * @return string|false
     */
    public static function getRawJson()
    {
        if (!file_exists(self::$configPath)) {
            return false;
        }
        return file_get_contents(self::$configPath);
    }

    /**
     * Clear cached reports (for testing or after config changes)
     */
    public static function clearCache(): void
    {
        self::$reports = null;
    }

    /**
     * Load reports from JSON file
     */
    private static function loadReports(): void
    {
        if (self::$reports !== null) {
            return;
        }

        self::$reports = [];

        if (!file_exists(self::$configPath)) {
            return;
        }

        $content = file_get_contents(self::$configPath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['reports'])) {
            return;
        }

        self::$reports = $data['reports'];
    }
}
