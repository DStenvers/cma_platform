<?php
/**
 * TestHarness — shared setup helpers for integration-style tests
 * that need to coax production code into running without a real CMA,
 * IIS or database. Used by Save* / List* / RecordService tests in
 * the sprint-3+ batch.
 *
 * Each helper pokes a static cache via reflection so production
 * code finds what it expects without going down its normal load
 * path. Tests MUST call `TestHarness::reset()` in tearDown to
 * avoid bleeding state into the next test.
 *
 * Pattern is explicit on purpose — no autoloading, no magic. You
 * see exactly which dependencies the test is short-circuiting,
 * which doubles as documentation of what `saveJsonFormRecord`
 * actually depends on at runtime.
 */

class TestHarness
{
    /**
     * Cache the test-specific values so reset() can restore everything.
     * Each entry is [\ReflectionProperty, originalValue].
     *
     * @var array<int,array{0:\ReflectionProperty,1:mixed}>
     */
    private static array $reflectionRestores = [];

    /**
     * Make SecurityHelper::isAdmin() / isDeveloper() / isLoggedIn()
     * return true without touching the users database. Pokes the
     * two static caches (isValidatedCache + currentUserCache) that
     * the helper uses to skip its own DB-lookup path.
     */
    public static function loginAs(int $level, string $userLogin = 'testuser'): void
    {
        self::setStatic(\Cma\SecurityHelper::class, 'isValidatedCache', true);
        self::setStatic(\Cma\SecurityHelper::class, 'currentUserCache', [
            'ID'         => '1',
            'userLogin'  => $userLogin,
            'userLevel'  => $level,
            'userFullName' => $userLogin,
            'userEmail'  => $userLogin . '@example.test',
        ]);
    }

    public static function loginAsAdmin(): void
    {
        self::loginAs(\Cma\SecurityHelper::LEVEL_ADMIN);
    }

    public static function loginAsDeveloper(): void
    {
        self::loginAs(\Cma\SecurityHelper::LEVEL_DEVELOPER);
    }

    /**
     * Make Database::getConnection($name) return our stub instead of
     * trying to open a real PDO. Pokes the named-connection pool the
     * helper checks first.
     */
    public static function injectConnection(string $name, \PDO $conn): void
    {
        $name = strtolower($name);
        $ref = new \ReflectionClass(\App\Library\Database::class);
        $prop = $ref->getProperty('namedConnections');
        $prop->setAccessible(true);
        $current = $prop->getValue();
        if (!isset(self::$reflectionRestores['db_namedConnections'])) {
            self::$reflectionRestores['db_namedConnections'] = [$prop, $current];
        }
        $current[$name] = $conn;
        $prop->setValue(null, $current);
    }

    /**
     * Pre-load a form definition into JsonFormLoader's cache so
     * `load($formName)` returns our test fixture instead of trying
     * to read it from disk.
     */
    public static function injectFormDef(string $formName, array $def): void
    {
        $ref = new \ReflectionClass(\Cma\JsonFormLoader::class);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $current = $prop->getValue();
        if (!isset(self::$reflectionRestores['jfl_cache'])) {
            self::$reflectionRestores['jfl_cache'] = [$prop, $current];
        }
        $current[$formName] = $def;
        // loadRaw uses a 'raw_<name>' key, populate that too so
        // either entry point hits the cache.
        $current['raw_' . $formName] = $def['_json'] ?? $def;
        $prop->setValue(null, $current);
    }

    /**
     * Redirect Logger output to a tmp dir so info/error/debug
     * calls don't try to mkdir under data/logs in a context that
     * may not exist or be writable.
     */
    public static function silenceLogger(): void
    {
        $tmp = sys_get_temp_dir() . '/cma-test-logger-' . bin2hex(random_bytes(3));
        if (!is_dir($tmp)) { mkdir($tmp, 0755, true); }
        self::setStatic(\Cma\Services\Logger::class, 'logDir', $tmp);
        self::setStatic(\Cma\Services\Logger::class, 'minLevel', 'emergency');
    }

    /**
     * Reset ALL state poked by this harness. Call from tearDown
     * so the next test starts from a clean slate. Tests that
     * don't reset will bleed admin-rights / stub connections
     * into siblings.
     */
    public static function reset(): void
    {
        // Restore each property to its captured original value.
        foreach (self::$reflectionRestores as [$prop, $original]) {
            $prop->setValue(null, $original);
        }
        self::$reflectionRestores = [];

        // Caches we didn't capture-and-restore but always want clean.
        self::setStatic(\Cma\SecurityHelper::class, 'isValidatedCache', null);
        self::setStatic(\Cma\SecurityHelper::class, 'currentUserCache', null);
    }

    /**
     * Lower-level helper: write any static property of any class
     * via reflection. Used for the simple flag/value poking that
     * doesn't need restore-on-reset (the reset() resets them to a
     * known clean state regardless).
     */
    public static function setStatic(string $class, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($class);
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue(null, $value);
    }
}
