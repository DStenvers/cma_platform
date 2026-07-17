<?php

namespace App\Library;

/**
 * Dictionary — a VBScript `Scripting.Dictionary` work-alike.
 *
 * The converter maps `CreateObject("Scripting.Dictionary")` to `new Dictionary()`
 * so converted ASP keeps working with the same surface:
 *  - item get/set via array syntax:      $d[$key]      /  $d[$key] = $v
 *  - ->Add($key, $val), ->Item($key), ->Exists($key), ->Remove($key), ->RemoveAll()
 *  - ->Count / ->count  (property-style, like VBScript `.Count`)
 *  - ->Keys() / ->Items()
 *  - foreach ($d as $key) iterates KEYS  (VBScript `for each ... in dict` semantics)
 *
 * Keys are compared case-sensitively (VBScript default CompareMode = vbBinaryCompare).
 */
class Dictionary implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var array<string|int,mixed> */
    private array $data = [];

    /** VBScript: dict.Add key, item */
    public function Add($key, $value): void
    {
        $this->data[self::normalizeKey($key)] = $value;
    }

    /** VBScript: dict.Item(key) — read access; missing key yields '' (empty), like an unset variant. */
    public function Item($key)
    {
        return $this->data[self::normalizeKey($key)] ?? '';
    }

    public function Exists($key): bool
    {
        return array_key_exists(self::normalizeKey($key), $this->data);
    }

    public function Remove($key): void
    {
        unset($this->data[self::normalizeKey($key)]);
    }

    public function RemoveAll(): void
    {
        $this->data = [];
    }

    /** VBScript: dict.Keys -> array of keys */
    public function Keys(): array
    {
        return array_keys($this->data);
    }

    /** VBScript: dict.Items -> array of items */
    public function Items(): array
    {
        return array_values($this->data);
    }

    /* ---------- ArrayAccess: $d[$key] ---------- */

    public function offsetExists($offset): bool
    {
        return array_key_exists(self::normalizeKey($offset), $this->data);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[self::normalizeKey($offset)] ?? '';
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[self::normalizeKey($offset)] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[self::normalizeKey($offset)]);
    }

    /* ---------- Countable: count($d) ---------- */

    public function count(): int
    {
        return count($this->data);
    }

    /* ---------- foreach ($d as $key) yields KEYS (VBScript for-each) ---------- */

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator(array_keys($this->data));
    }

    /* ---------- VBScript `.Count` property access ($d->count / $d->Count) ---------- */

    public function __get($name)
    {
        if (strcasecmp((string)$name, 'count') === 0) {
            return count($this->data);
        }
        return null;
    }

    private static function normalizeKey($key)
    {
        return is_int($key) ? $key : (string)$key;
    }
}
