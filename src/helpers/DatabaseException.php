<?php

namespace App\Library;

/**
 * A database call failed. Thrown by every Database read/write helper instead
 * of a sentinel (null, [], 0, false) so a broken query hits the error handler
 * like any other failure: logged, mailed when the site asks for that, and
 * visible to an admin — never an empty list that looks like "no records".
 *
 * Code that expects a query to fail (a schema probe, an optional column) wraps
 * the call in try/catch and decides what the failure means; everything else
 * lets it propagate.
 */
class DatabaseException extends \RuntimeException
{
    private string $sql;

    /** @var array<int|string,mixed> */
    private array $params;

    /**
     * @param array<int|string,mixed> $params
     */
    public function __construct(string $message, string $sql = '', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->params = $params;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /** @return array<int|string,mixed> */
    public function getParams(): array
    {
        return $this->params;
    }
}
