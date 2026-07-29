<?php

namespace App\Library;

/**
 * File - File and Directory Operations Helper
 *
 * Provides file and directory management functionality to replace VBScript FileSystemObject.
 * All methods use native PHP file operations for better performance and security.
 *
 * Usage:
 *   File::exists('path/to/file.txt')
 *   File::delete('path/to/file.txt')
 *   File::copy('source.txt', 'dest.txt')
 *   File::folderExists('path/to/dir')
 *   File::createFolder('path/to/dir')
 *   File::readAsciiFile('file.txt')
 *   File::createAsciiFile('file.txt', 'content')
 *
 * Path mapping is handled automatically via Server::mapPath()
 */
class File {
    /**
     * Check if a file exists
     *
     * @param string $path File path (relative or absolute)
     * @return bool True if file exists, false otherwise
     */
    public static function exists(?string $path): bool {
        // Tolerate null (VBScript fso.FileExists(Empty) returns False, not a
        // TypeError) — converted code passes an uninitialised path variant.
        if ($path === null || $path === '') {
            return false;
        }

        $mappedPath = Server::mapPath($path);
        return file_exists($mappedPath) && is_file($mappedPath);
    }

    /**
     * Delete a file
     *
     * @param string $path File path (relative or absolute)
     * @return bool True on success, false on failure
     */
    public static function delete(string $path): bool {
        if ($path === '') {
            return false;
        }

        try {
            $mappedPath = Server::mapPath($path);

            if (!file_exists($mappedPath)) {
                return false;
            }

            return unlink($mappedPath);

        } catch (\Exception $e) {
            error_log("File::delete() exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a folder exists
     *
     * @param string $path Folder path (relative or absolute)
     * @return bool True if folder exists, false otherwise
     */
    public static function folderExists(string $path): bool {
        if ($path === '') {
            return false;
        }

        $mappedPath = Server::mapPath($path);
        return file_exists($mappedPath) && is_dir($mappedPath);
    }

    /**
     * Express one path relative to another, purely as string arithmetic.
     *
     * Used for the `$schema` reference a generated JSON file carries: that
     * reference resolves against the file's OWN directory, so a literal like
     * '../schema/form-definition.schema.json' is only correct at one nesting
     * depth. Generated forms land in two different places (cma/assets/forms/
     * definitions/ and the site's assets/forms/), so the value has to be
     * computed from the destination rather than hardcoded.
     *
     * Neither path needs to exist; only the strings are compared. Both are
     * normalised (backslashes, '.', '..', duplicate separators) first. When
     * the two live on different Windows drives no relative path exists, so
     * $to is returned unchanged.
     *
     * @param string $fromDir Directory the result is relative TO
     * @param string $to      File or directory the result points AT
     * @return string Relative path using forward slashes, e.g. '../../cma/config/schema/x.json'
     */
    public static function relativePath(string $fromDir, string $to): string {
        $split = static function (string $path): array {
            $parts = [];
            foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                    array_pop($parts);
                    continue;
                }
                $parts[] = $part;
            }
            return $parts;
        };

        $from = $split($fromDir);
        $dest = $split($to);

        // Different roots (C:/ vs D:/, or one absolute and one relative) have
        // no common ancestor to walk up to.
        if (($from[0] ?? null) !== ($dest[0] ?? null)) {
            return str_replace('\\', '/', $to);
        }

        while ($from !== [] && $dest !== [] && $from[0] === $dest[0]) {
            array_shift($from);
            array_shift($dest);
        }

        return implode('/', array_merge(array_fill(0, count($from), '..'), $dest));
    }

    /**
     * Copy a file
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @return bool True if copy succeeded, false otherwise
     */
    public static function copy(string $source, string $destination): bool {
        if (!self::exists($source)) {
            return false;
        }

        try {
            $mappedSource = Server::mapPath($source);
            $mappedDest = Server::mapPath($destination);

            // Create destination directory if it doesn't exist
            $destDir = dirname($mappedDest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $result = copy($mappedSource, $mappedDest);

            return $result && self::exists($destination);

        } catch (\Exception $e) {
            error_log("File::copy() exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a folder (directory), and any missing parent directories.
     *
     * Walks the path one segment at a time, creating each missing level, instead
     * of relying on a single recursive mkdir(): on IIS a recursive create reports
     * failure as one opaque false and can give up when an *intermediate* segment
     * can't be made (e.g. a freshly deployed tree whose app-pool identity only
     * inherits write rights part-way down). Stepwise creation also tolerates a
     * half-present chain and a parallel request winning the race on a level.
     *
     * @param string $path Folder path. By default a virtual/URL path resolved via
     *                     Server::mapPath(); pass $raw = true to treat $path as a
     *                     literal filesystem path (no document-root mapping or
     *                     allowedPaths sandbox) — for consumers outside the CMA
     *                     document root, e.g. the webhooks layer's var/log tree.
     * @param bool   $raw  Skip Server::mapPath() and use $path verbatim.
     * @return bool True if the folder was created or already exists, false on failure
     */
    public static function createFolder(string $path, bool $raw = false): bool {
        if ($path === '') {
            return false;
        }

        try {
            $target = $raw ? $path : Server::mapPath($path);

            // If folder already exists, return true
            if (is_dir($target)) {
                return true;
            }

            // Create the folder and any missing parents, one level at a time
            return self::makeDirStepwise($target, 0755);

        } catch (\Exception $e) {
            error_log("File::createFolder() exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create $path and every missing parent, one directory at a time. Preserves
     * POSIX absolute roots ("/…") and Windows drive roots ("C:\…"); relative
     * paths resolve against the current working directory.
     *
     * @return bool True once $path exists as a directory.
     */
    private static function makeDirStepwise(string $path, int $mode): bool {
        $norm = str_replace('\\', '/', $path);

        // Peel off the root so the re-joined segments stay anchored correctly.
        $prefix = '';
        if (preg_match('#^[A-Za-z]:/#', $norm)) {        // Windows drive, "C:/..."
            $prefix = substr($norm, 0, 3);               // -> "C:/"
            $norm   = substr($norm, 3);
        } elseif ($norm !== '' && $norm[0] === '/') {     // POSIX absolute
            $prefix = '/';
            $norm   = ltrim($norm, '/');
        }

        // Build the path up one segment at a time, creating each missing level.
        $current = $prefix === '' ? '.' : rtrim($prefix, '/');
        foreach (explode('/', $norm) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $current .= '/' . $segment;
            if (is_dir($current)) {
                continue;
            }
            // !is_dir guard: a parallel request may have created it between the
            // check above and our mkdir — that is success, not a failure.
            if (!@mkdir($current, $mode) && !is_dir($current)) {
                return false;
            }
        }

        return is_dir($current);
    }

    /**
     * Read entire ASCII file contents
     *
     * @param string $path File path (relative or absolute)
     * @return string File contents, or empty string if file doesn't exist
     */
    public static function readAsciiFile(string $path): string {
        if ($path === '') {
            return '';
        }

        try {
            $mappedPath = Server::mapPath($path);

            if (!file_exists($mappedPath)) {
                return '';
            }

            $contents = file_get_contents($mappedPath);

            return $contents !== false ? $contents : '';

        } catch (\Exception $e) {
            error_log("File::readAsciiFile() exception: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Create (overwrite) ASCII file with content
     *
     * @param string $path File path (relative or absolute)
     * @param string $content File content to write
     * @return bool True if file was created successfully, false otherwise
     */
    public static function createAsciiFile(string $path, string $content): bool {
        if ($path === '') {
            return false;
        }

        try {
            $mappedPath = Server::mapPath($path);

            // Create directory if it doesn't exist
            $dir = dirname($mappedPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Write content to file (overwrites existing file)
            // Note: VBScript WriteLine adds newline, we add it here for compatibility
            $result = file_put_contents($mappedPath, $content . PHP_EOL);

            if ($result === false) {
                // If write failed, delete the file if it was created
                if (file_exists($mappedPath)) {
                    unlink($mappedPath);
                }
                return false;
            }

            return file_exists($mappedPath);

        } catch (\Exception $e) {
            error_log("File::createAsciiFile() exception: " . $e->getMessage());
            return false;
        }
    }
}
