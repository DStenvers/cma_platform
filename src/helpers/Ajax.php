<?php

namespace App\Library;

/**
 * Ajax — generic front-controller dispatcher for AJAX endpoints.
 *
 * One endpoint (`ajax.php`) receives a `type` plus parameters, validates the
 * user once, dispatches to the registered handler, and wraps the handler's
 * return value in a single JSON envelope with central error handling.
 *
 * GENERIC: this class hardcodes nothing project-specific. A host project wires
 * in how to check authentication / roles / dev-mode via configure(); sensible
 * fallbacks (COOKIE_ID/COOKIE_TYPE constants) apply if it doesn't.
 *
 * CALLING STYLES (single OR an array of calls):
 *   - Single:  ajax.php?type=like&id=5
 *   - Batch:   ajax.php  with  calls=[{"type":"like","params":{"id":5}},
 *                                     {"type":"unlike","params":{"id":6}}]
 *              → returns {"success":true,"results":[ ...one envelope per call ]}
 *
 * HANDLER CONTRACT — a handler receives its parameters as an array and returns
 * an array (the dispatcher json_encodes it):
 *   Ajax::register('like', function (array $params): array {
 *       $id = (int)($params['id'] ?? 0);
 *       // ... logic, parameterized SQL ...
 *       return ['success' => true, 'aantal' => $n];
 *   }, requiresLogin: true);
 *
 * For batch calls the per-call params are also overlaid onto the request
 * superglobals, so legacy handlers that still read Request::queryInt('id')
 * keep working until they are modernized to use $params.
 */
class Ajax
{
    /** @var array<string, array{fn:callable, requiresLogin:bool, requiresRole:?string}> */
    private static array $handlers = [];

    /** @var array{auth:?callable, isDev:?callable} */
    private static array $config = ['auth' => null, 'isDev' => null];

    /**
     * Wire project-specific behaviour (call once, e.g. in _bootstrap.php).
     *
     * @param array $opts {
     *   auth:  callable(bool $requiresLogin, ?string $requiresRole): bool — true if the
     *          current request is permitted (default: COOKIE_ID/COOKIE_TYPE fallback),
     *   isDev: callable(): bool — whether to expose error detail (default: Application env),
     * }
     */
    public static function configure(array $opts): void
    {
        self::$config = array_merge(self::$config, array_intersect_key($opts, self::$config));
    }

    public static function register(
        string $type,
        callable $handler,
        bool $requiresLogin = true,
        ?string $requiresRole = null
    ): void {
        self::$handlers[$type] = compact('handler', 'requiresLogin', 'requiresRole') + ['fn' => $handler];
    }

    /**
     * Dispatch the current request — single call or an array of calls.
     *
     * @param string $handlersDir directory holding "<type>.php" handler files
     */
    public static function run(string $handlersDir): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        Response::noCache();

        $calls = self::parseCalls();
        if ($calls === null) {
            self::emit(self::error(400, 'invalid_request'));
            return;
        }

        $batch = count($calls) > 1 || self::isBatchRequest();
        $results = [];
        foreach ($calls as $call) {
            $results[] = self::dispatchOne($call['type'], $call['params'], $handlersDir);
        }

        if ($batch) {
            // Strip the internal status marker from each per-call envelope.
            $clean = array_map(static function (array $r): array {
                unset($r['__status']);
                return $r;
            }, $results);
            self::emit(['success' => true, 'results' => $clean]);
        } else {
            // Single call: emit its envelope directly (and mirror its HTTP status).
            $only = $results[0];
            if (isset($only['__status'])) {
                http_response_code($only['__status']);
                unset($only['__status']);
            }
            self::emit($only);
        }
    }

    /**
     * Resolve and invoke one (type, params) call; returns its JSON envelope.
     * Never throws — failures become error envelopes.
     */
    private static function dispatchOne(string $type, array $params, string $handlersDir): array
    {
        if ($type === '' || !preg_match('/^[A-Za-z0-9_]+$/', $type)) {
            return self::error(400, 'invalid_type', $type);
        }

        if (!isset(self::$handlers[$type])) {
            $file = $handlersDir . '/' . $type . '.php';
            if (is_file($file)) {
                require_once $file; // expected to call Ajax::register($type, ...)
            }
        }
        if (!isset(self::$handlers[$type])) {
            return self::error(404, 'unknown_type', $type);
        }

        $h = self::$handlers[$type];
        if (!self::authorize($h['requiresLogin'], $h['requiresRole'])) {
            return self::error($h['requiresLogin'] && !self::loggedIn() ? 401 : 403,
                               $h['requiresLogin'] && !self::loggedIn() ? 'not_authenticated' : 'forbidden',
                               $type);
        }

        // Overlay this call's params onto the request so both $params-style and
        // legacy Request::-style handlers see the right values; restore after.
        $savedGet = $_GET; $savedReq = $_REQUEST;
        $_GET = $params + ['type' => $type];
        $_REQUEST = $params + $_REQUEST + ['type' => $type];
        try {
            ob_start();
            $data = (self::$handlers[$type]['fn'])($params);
            $echoed = ob_get_clean();
            if (is_array($data)) {
                return $data + ['type' => $type];
            }
            if (trim($echoed) !== '') {
                $decoded = json_decode($echoed, true);
                return (is_array($decoded) ? $decoded : ['success' => true, 'raw' => $echoed]) + ['type' => $type];
            }
            return ['success' => true, 'type' => $type];
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return self::error(500, 'server_error', self::isDev() ? $e->getMessage() : null);
        } finally {
            $_GET = $savedGet; $_REQUEST = $savedReq;
        }
    }

    /**
     * Normalise the request into a list of {type, params} calls.
     * Returns null on a malformed batch payload.
     *
     * @return array<int, array{type:string, params:array}>|null
     */
    private static function parseCalls(): ?array
    {
        if (self::isBatchRequest()) {
            $raw = Request::query('calls', '') ?: Request::post('calls', '');
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $calls = [];
            foreach ($decoded as $c) {
                if (!is_array($c) || !isset($c['type'])) {
                    return null;
                }
                $calls[] = ['type' => (string)$c['type'], 'params' => (array)($c['params'] ?? [])];
            }
            return $calls;
        }
        // Single call: type + everything else as params.
        $type = Request::query('type', '') ?: Request::post('type', '');
        $params = $_REQUEST;
        unset($params['type']);
        return [['type' => (string)$type, 'params' => $params]];
    }

    private static function isBatchRequest(): bool
    {
        return (Request::query('calls', '') ?: Request::post('calls', '')) !== '';
    }

    // --- auth (configurable, with safe defaults) -----------------------------

    private static function authorize(bool $requiresLogin, ?string $requiresRole): bool
    {
        if (is_callable(self::$config['auth'])) {
            return (bool)(self::$config['auth'])($requiresLogin, $requiresRole);
        }
        if ($requiresLogin && !self::loggedIn()) {
            return false;
        }
        if ($requiresRole !== null && !self::hasRole($requiresRole)) {
            return false;
        }
        return true;
    }

    private static function loggedIn(): bool
    {
        $id = defined('COOKIE_ID') ? Cookie::get(COOKIE_ID, '') : Cookie::get('id', '');
        return $id !== '' && $id !== null;
    }

    private static function hasRole(string $role): bool
    {
        $type = defined('COOKIE_TYPE') ? Cookie::get(COOKIE_TYPE, '') : Cookie::get('type', '');
        return (string)$type === $role;
    }

    private static function isDev(): bool
    {
        if (is_callable(self::$config['isDev'])) {
            return (bool)(self::$config['isDev'])();
        }
        $env = strtoupper((string)Application::get('omgeving', Application::get('environment', '')));
        return in_array($env, ['L', 'O', 'T', 'LOCAL', 'DEV', 'TEST'], true);
    }

    // --- output --------------------------------------------------------------

    private static function error(int $status, string $error, ?string $detail = null): array
    {
        $env = ['success' => false, 'error' => $error, '__status' => $status];
        if ($detail !== null && $detail !== '') {
            $env['detail'] = $detail;
        }
        return $env;
    }

    private static function emit(array $payload): void
    {
        unset($payload['__status']);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Introspection: registered action types. */
    public static function registeredTypes(): array
    {
        return array_keys(self::$handlers);
    }
}
