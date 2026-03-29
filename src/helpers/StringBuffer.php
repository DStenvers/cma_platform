<?php

namespace App\Library;

/**
 * StringBuffer Class
 *
 * Efficient string building class for PHP.
 * Replaces ASP's StringBuffer class which used ADODB.Stream for large strings.
 *
 * In PHP, string concatenation is already efficient due to copy-on-write,
 * but this class provides a consistent API and can be optimized if needed.
 *
 * Usage:
 *   $buf = new StringBuffer();
 *   $buf->append('Hello ');
 *   $buf->append('World');
 *   echo $buf->toString(); // "Hello World"
 */
class StringBuffer
{
    private string $buffer = '';
    private int $size = 0;

    /**
     * Append a string to the buffer
     *
     * @param string $value The string to append
     * @return void
     */
    public function append($value): void
    {
        $strValue = (string)$value;
        $this->buffer .= $strValue;
        $this->size += strlen($strValue);
    }

    /**
     * Append a string followed by a newline
     *
     * @param string $value The string to append
     * @return void
     */
    public function appendLine($value): void
    {
        $this->append($value . "\r\n");
    }

    /**
     * Clear the buffer
     *
     * @return void
     */
    public function clear(): void
    {
        $this->buffer = '';
        $this->size = 0;
    }

    /**
     * Get the buffer contents as a string
     *
     * @return string The buffer contents
     */
    public function toString(): string
    {
        return $this->buffer;
    }

    /**
     * Get the buffer contents (alias for toString)
     *
     * @return string The buffer contents
     */
    public function __toString(): string
    {
        return $this->buffer;
    }

    /**
     * Get the size of the buffer in bytes
     *
     * @return int The buffer size
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Save the buffer contents to a file
     *
     * @param string $filename The file path to save to
     * @return bool True on success, false on failure
     */
    public function saveToFile(string $filename): bool
    {
        return file_put_contents($filename, $this->buffer) !== false;
    }
}
