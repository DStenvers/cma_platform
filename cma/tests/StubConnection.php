<?php
/**
 * StubConnection — PDO-shaped test double for service-layer tests.
 *
 * Lets a test pre-load a queue of fake query results and then assert
 * exactly which SQL + parameters the production code executed. NOT
 * representative of ODBC-dialect quirks (identifier quoting, encoding)
 * — for that you need the blank.mdb fixture documented in the
 * `testing` topic. This stub is for query-shape regression coverage:
 * "did RecordService::save build the UPDATE I expected?".
 *
 * Usage:
 *   $conn = StubConnection::create();
 *   $conn->enqueueResult([['ID' => 1, 'naam' => 'Alice']]);
 *   $rs = Database::openRS('SELECT * FROM tblUsers WHERE ID = 1', $conn);
 *   // ... production code runs ...
 *   $calls = $conn->getCalls();
 *   $this->assertEquals('SELECT * FROM tblUsers WHERE ID = 1', $calls[0]['sql']);
 *
 * Supported interface:
 *   - prepare(sql) → StubStatement that records execute() params
 *   - query(sql) → next-queued result wrapped in a StubStatement
 *   - exec(sql) → next-queued affected-row count (default 1)
 *   - lastInsertId() → next-queued ID (default '1')
 *   - getAttribute(PDO::ATTR_DRIVER_NAME) → 'sqlite' by default,
 *     override via setDriverName() to mimic 'odbc' / 'sqlsrv' / 'mysql'
 *   - setAttribute() → no-op
 *
 * Each call is recorded on $this->calls with keys [type, sql, params].
 */

class StubConnection extends \PDO
{
    /** @var array<array{type:string,sql:string,params:array}> */
    private array $calls = [];

    /** @var array<int,mixed> Queue of results for query()/prepare()->execute(). */
    private array $resultQueue = [];

    /** @var array<int,int> Queue of affected-row counts for exec(). */
    private array $execQueue = [];

    /** @var array<int,string> Queue of lastInsertId returns. */
    private array $insertIdQueue = [];

    private string $driverName = 'sqlite';

    /**
     * Construct via the static `create()` factory rather than `new`. The
     * private constructor signals that the parent PDO constructor is
     * intentionally bypassed (no DSN, no driver) — `instanceof PDO`
     * still passes since we extend it.
     */
    private function __construct() {}

    /**
     * Bypass PDO's constructor entirely (it would need a working DSN +
     * driver — pdo_sqlite isn't available in every CI / WSL env, and
     * we don't need it for query-shape recording). The reflection trick
     * gives us a valid PDO-typed instance whose state is whatever we set.
     */
    public static function create(): self
    {
        return (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
    }

    /**
     * Push a single query result onto the queue. Each result is an array
     * of rows (associative arrays). The next query()/execute() returns it.
     */
    public function enqueueResult(array $rows): void
    {
        $this->resultQueue[] = $rows;
    }

    /**
     * Push a row-count for the next exec() call (e.g. INSERT/UPDATE/DELETE).
     */
    public function enqueueExec(int $affectedRows): void
    {
        $this->execQueue[] = $affectedRows;
    }

    /**
     * Push a lastInsertId() return for the next call after an INSERT.
     */
    public function enqueueInsertId(string $id): void
    {
        $this->insertIdQueue[] = $id;
    }

    public function setDriverName(string $name): void
    {
        $this->driverName = $name;
    }

    /**
     * @return array<array{type:string,sql:string,params:array}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getLastCall(): ?array
    {
        return empty($this->calls) ? null : $this->calls[count($this->calls) - 1];
    }

    public function reset(): void
    {
        $this->calls = [];
        $this->resultQueue = [];
        $this->execQueue = [];
        $this->insertIdQueue = [];
    }

    // ---- PDO override surface --------------------------------------

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new StubStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $stmt = new StubStatement($this, $query);
        $stmt->loadFromQueue();
        $this->calls[] = ['type' => 'query', 'sql' => $query, 'params' => []];
        return $stmt;
    }

    public function exec(string $statement): int|false
    {
        $this->calls[] = ['type' => 'exec', 'sql' => $statement, 'params' => []];
        return array_shift($this->execQueue) ?? 1;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return array_shift($this->insertIdQueue) ?? '1';
    }

    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === \PDO::ATTR_DRIVER_NAME) {
            return $this->driverName;
        }
        return null;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function inTransaction(): bool { return false; }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }

    // ---- Internal hook for StubStatement ---------------------------

    /**
     * Called by StubStatement::execute(). Records the call and returns
     * the next queued result-set (or empty if none).
     */
    public function recordStatement(string $sql, array $params): array
    {
        $this->calls[] = ['type' => 'prepare', 'sql' => $sql, 'params' => $params];
        return array_shift($this->resultQueue) ?? [];
    }
}

/**
 * StubStatement — PDOStatement double tied to a StubConnection.
 * Returns the next queued result-set on execute(), records SQL + params.
 */
class StubStatement extends \PDOStatement
{
    private StubConnection $conn;
    private string $sql;
    private array $rows = [];
    private int $cursor = 0;

    public function __construct(StubConnection $conn, string $sql)
    {
        $this->conn = $conn;
        $this->sql = $sql;
    }

    /** Pre-load this statement's result without going through execute(). */
    public function loadFromQueue(): void
    {
        $this->rows = $this->conn->recordStatement($this->sql, []);
        $this->cursor = 0;
    }

    public function execute(?array $params = null): bool
    {
        $this->rows = $this->conn->recordStatement($this->sql, $params ?? []);
        $this->cursor = 0;
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->cursor >= count($this->rows)) {
            return false;
        }
        return $this->rows[$this->cursor++];
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $remaining = array_slice($this->rows, $this->cursor);
        $this->cursor = count($this->rows);
        return $remaining;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function closeCursor(): bool
    {
        $this->cursor = count($this->rows);
        return true;
    }

    public function columnCount(): int
    {
        return empty($this->rows) ? 0 : count((array)$this->rows[0]);
    }
}
