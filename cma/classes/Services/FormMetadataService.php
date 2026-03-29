<?php

namespace Cma\Services;

use Cma\JsonFormLoader;

/**
 * Service for loading form metadata from JSON definitions
 * Used for security rights, form lists, etc.
 *
 * Searches both internal (/cma/assets/forms/definitions/) and
 * external (/site/config/forms/) directories.
 */
class FormMetadataService
{
    private static ?array $formsCache = null;

    /**
     * Internal forms directory (inside CMA)
     */
    private const INTERNAL_DEFINITIONS_DIR = __DIR__ . '/../../assets/forms/definitions';

    /**
     * External forms directory (outside CMA)
     */
    private const EXTERNAL_DEFINITIONS_DIR = __DIR__ . '/../../../config/forms';

    /**
     * Get all form metadata from JSON definitions
     *
     * @param bool $visibleOnly Only return visible forms
     * @return array Array of form metadata indexed by form ID
     */
    public static function getAll(bool $visibleOnly = true): array
    {
        self::loadForms();

        if (!$visibleOnly) {
            return self::$formsCache;
        }

        return array_filter(self::$formsCache, fn($f) => $f['visible'] ?? true);
    }

    /**
     * Get form metadata by source form ID
     *
     * @param int $formId Original form ID from database
     * @return array|null
     */
    public static function getById(int $formId): ?array
    {
        self::loadForms();

        return self::$formsCache[$formId] ?? null;
    }

    /**
     * Get form metadata by form name
     *
     * @param string $formName Form name
     * @return array|null
     */
    public static function getByName(string $formName): ?array
    {
        self::loadForms();

        foreach (self::$formsCache as $form) {
            if (($form['name'] ?? '') === $formName) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Get forms that have security by user enabled
     *
     * @return array
     */
    public static function getFormsWithSecurityByUser(): array
    {
        return array_filter(self::getAll(), fn($f) => $f['securityByUser'] ?? false);
    }

    /**
     * Clear cache
     */
    public static function clearCache(): void
    {
        self::$formsCache = null;
    }

    /**
     * Load all form definitions and extract metadata
     * Searches both internal and external directories
     */
    private static function loadForms(): void
    {
        if (self::$formsCache !== null) {
            return;
        }

        self::$formsCache = [];

        // Search both directories
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $definitionsDir) {
            if (!is_dir($definitionsDir)) {
                continue;
            }

            $files = glob($definitionsDir . '/*.json');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                $json = json_decode($content, true);
                if ($json === null) {
                    continue;
                }

                $formId = (int)($json['sourceFormId'] ?? 0);
                if ($formId === 0) {
                    continue;
                }

                // Skip if already loaded (internal takes precedence)
                if (isset(self::$formsCache[$formId])) {
                    continue;
                }

                // Extract metadata
                self::$formsCache[$formId] = [
                    'id' => $formId,
                    'name' => basename($file, '.json'),
                    'title' => $json['title'] ?? '',
                    'visible' => $json['visible'] ?? true,
                    'securityByUser' => $json['securityByUser'] ?? false,
                    'adminOnly' => $json['adminOnly'] ?? false,
                    'extraIconTitle' => $json['extraButtons'][0]['title'] ?? null,
                    'extraIcon2Title' => $json['extraButtons'][1]['title'] ?? null,
                    'extraIcon3Title' => $json['extraButtons'][2]['title'] ?? null,
                    'extraIcon4Title' => $json['extraButtons'][3]['title'] ?? null,
                    'extraIcon5Title' => $json['extraButtons'][4]['title'] ?? null,
                ];
            }
        }
    }
}
