<?php

namespace Cma\Services;

use App\Library\Cache;
use App\Library\Database;
use App\Library\SQL;

/**
 * Module parameters: per-module settings edited in the CMA (form
 * "moduleparameters", table tblCMAModuleParameters) and read by site code —
 * the classic tblModuleParameters that lib_XMLSnippets handed to templates.
 *
 *   $intro = ModuleParameters::get('nieuws', 'intro', 'Welkom');
 *   foreach (ModuleParameters::all('nieuws') as $name => $value) { ... }
 *
 * Values are cached per module for the request and in the 'data' cache group,
 * which a save through the CMA invalidates.
 */
class ModuleParameters
{
    /** @var array<string,array<string,string>> */
    private static array $perModule = [];

    public static function get(string $module, string $name, string $default = ''): string
    {
        $all = self::all($module);
        foreach ($all as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Every parameter of a module: name => value (typed as stored; use
     * (int)/(float) for NUMMER, the HTML of an HTML parameter is stored as-is).
     *
     * @return array<string,string>
     */
    public static function all(string $module): array
    {
        $key = strtolower($module);
        if (isset(self::$perModule[$key])) {
            return self::$perModule[$key];
        }
        $values = [];
        try {
            $conn = Database::getConnection('data');
            if ($conn !== null && Database::tableExistsPDO($conn, 'tblCMAModuleParameters')) {
                $sql = 'SELECT Name, Waarde FROM tblCMAModuleParameters WHERE ModuleNaam = ' . SQL::postString($module) . ' ORDER BY Sortorder, Caption';
                $rs = Database::openRS($sql, $conn);
                while ($rs && !$rs->EOF) {
                    $row = $rs->fetchAssoc();
                    $values[(string)($row['Name'] ?? '')] = (string)($row['Waarde'] ?? '');
                    $rs->MoveNext();
                }
            }
        } catch (\Throwable $e) {
            // a missing table or db hiccup gives defaults, never an error page
        }
        self::$perModule[$key] = $values;
        return $values;
    }

    public static function clearCache(): void
    {
        self::$perModule = [];
    }
}
