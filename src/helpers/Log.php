<?php

namespace App\Library;

/**
 * Log - Simple File-Based Logging Helper
 *
 * Provides basic logging functionality to replace VBScript FileSystemObject-based logging.
 * This is a lightweight logging solution for converted ASP applications.
 *
 * Usage:
 *   Log::init('logfile.txt');
 *   Log::write('Log message');
 *   Log::writeLine('Log message with newline');
 *   Log::close();
 *
 * Thread-safe file locking is used to prevent race conditions.
 */
class Log {
    /**
     * @var resource|null File handle
     */
    private static $fileHandle = null;

    /**
     * @var string Current log file path
     */
    private static $logFilename = null;

    /**
     * Initialize logging to a file
     *
     * @param string $filename Log file name (relative to document root)
     * @return bool Success status
     */
    public static function init(string $filename): bool {
        try {
            // Close existing handle if open
            self::close();

            // Map path using Server helper
            self::$logFilename = Server::mapPath($filename);

            // Create directory if it doesn't exist
            $dir = dirname(self::$logFilename);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Open file for appending with exclusive lock
            self::$fileHandle = fopen(self::$logFilename, 'a');

            if (self::$fileHandle === false) {
                error_log("Log::init() failed to open file: " . self::$logFilename);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            error_log("Log::init() exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Write text to log file (no newline)
     *
     * @param string $message Message to write
     * @return bool Success status
     */
    public static function write(string $message): bool {
        try {
            // Auto-initialize if not already done
            if (self::$fileHandle === null) {
                self::init('logfile.txt');
            }

            if (self::$fileHandle === null) {
                return false;
            }

            // Acquire exclusive lock, write, release lock
            if (flock(self::$fileHandle, LOCK_EX)) {
                fwrite(self::$fileHandle, $message);
                fflush(self::$fileHandle);
                flock(self::$fileHandle, LOCK_UN);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            error_log("Log::write() exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Write line to log file (with newline)
     *
     * @param string $message Message to write
     * @return bool Success status
     */
    public static function writeLine(string $message): bool {
        return self::write($message . PHP_EOL);
    }

    /**
     * Close the log file
     *
     * @return void
     */
    public static function close(): void {
        if (self::$fileHandle !== null) {
            fclose(self::$fileHandle);
            self::$fileHandle = null;
            self::$logFilename = null;
        }
    }

    /**
     * Get current log filename
     *
     * @return string|null Current log filename or null if not initialized
     */
    public static function getFilename(): ?string {
        return self::$logFilename;
    }
}
