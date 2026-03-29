<?php

namespace Cma\Services;

/**
 * Service voor het laden van database configuraties uit JSON
 * (voorheen via tblDatabases en tblModules)
 */
class DatabasesService
{
    private static ?array $databases = null;
    private static string $configPath = __DIR__ . '/../../../data/databases.json';

    /**
     * Haal alle databases op uit JSON config
     *
     * @return array
     */
    public static function getAll(): array
    {
        self::loadDatabases();
        return self::$databases;
    }

    /**
     * Haal een database op via ID
     *
     * @param int $id Database ID
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        self::loadDatabases();

        foreach (self::$databases as $db) {
            if ((int)$db['id'] === $id) {
                return $db;
            }
        }

        return null;
    }

    /**
     * Haal connection string op via database ID
     *
     * @param int $id Database ID
     * @return string|null
     */
    public static function getConnectionString(int $id): ?string
    {
        $db = self::getById($id);
        return $db['connectionString'] ?? null;
    }

    /**
     * Wis cache (voor testen of na configuratiewijzigingen)
     */
    public static function clearCache(): void
    {
        self::$databases = null;
    }

    /**
     * Laad databases uit JSON bestand
     */
    private static function loadDatabases(): void
    {
        if (self::$databases !== null) {
            return;
        }

        self::$databases = [];

        if (!file_exists(self::$configPath)) {
            return;
        }

        $content = file_get_contents(self::$configPath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['databases'])) {
            return;
        }

        self::$databases = $data['databases'];
    }
}
