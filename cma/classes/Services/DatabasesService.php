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
     *
     * Self-healing: when the file is missing or has an empty databases[]
     * array, write a minimal default with the "main" data database so
     * the rest of the system can boot. Without this, fresh installs
     * fail anywhere DatabasesService::getById() is called.
     */
    private static function loadDatabases(): void
    {
        if (self::$databases !== null) {
            return;
        }

        self::$databases = [];

        $data = null;
        if (file_exists(self::$configPath)) {
            $content = file_get_contents(self::$configPath);
            if ($content !== false) {
                $data = json_decode($content, true);
            }
        }

        if (!is_array($data) || empty($data['databases'])) {
            $data = self::writeDefaultConfig();
        }

        self::$databases = $data['databases'] ?? [];
    }

    /**
     * Write a minimal databases.json with the "main" data database
     * and return the resulting structure.
     */
    private static function writeDefaultConfig(): array
    {
        $default = [
            '$schema'     => './schema/databases.schema.json',
            'version'     => '2.0.0',
            'description' => 'Auto-generated default — populate via /cma/preferences.php or migrations.',
            'databases'   => [
                [
                    'id'               => 6,
                    'name'             => 'data',
                    'type'             => 'access',
                    'connectionString' => '',
                    'description'      => 'Main data database',
                ],
            ],
            'lastUpdated' => date('Y-m-d H:i:s'),
        ];

        $dir = dirname(self::$configPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            self::$configPath,
            json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $default;
    }
}
