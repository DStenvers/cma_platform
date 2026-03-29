<?php

namespace App\Library;

/**
 * Encryption Helper Class
 *
 * Provides cryptographic hashing utilities using PHP's native hash functions.
 * Replaces custom SHA-256 implementation with standard PHP hash() function.
 *
 */
class Encryption
{
    /**
     * Generate SHA-256 hash of data
     *
     * Uses PHP's native hash() function for SHA-256 hashing.
     * Returns hexadecimal string representation of the hash.
     *
     * @param string $data The data to hash
     * @return string SHA-256 hash as hexadecimal string
     */
    public static function sha256(string $data): string
    {
        return hash('sha256', $data);
    }
}
