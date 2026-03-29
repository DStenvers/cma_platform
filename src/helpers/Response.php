<?php

namespace App\Library;

/**
 * Response Helper Class
 *
 * Provides safe output and HTTP response management with proper header handling.
 *
 * Prevents "Headers already sent" errors with fallback mechanisms
 * and provides consistent API for output, redirects, and content-type management.
 *
 * Usage:
 *   Response::write($content);                    // Output content
 *   Response::write($content, true);              // Output with HTML encoding (XSS protection)
 *   Response::redirect('/page.php');              // Redirect with fallback
 *   Response::json(['status' => 'ok']);           // JSON response
 *   Response::setContentType('text/html');        // Set content type
 */
class Response
{
    /**
     * Output content with optional HTML encoding
     *
     * @param string $content The content to output
     * @param bool $encode Whether to HTML encode (default: false)
     * @return void
     */
    public static function write(string $content, bool $encode = false): void
    {
        if ($encode) {
            echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        } else {
            echo $content;
        }
    }

    /**
     * Output content with HTML encoding (XSS protection)
     *
     * Alias for write($content, true)
     *
     * @param string $content The content to output
     * @return void
     */
    public static function writeEncoded(string $content): void
    {
        self::write($content, true);
    }

    /**
     * Redirect to another URL
     *
     * Handles "headers already sent" error with JavaScript fallback
     *
     * @param string $url The URL to redirect to
     * @param int $statusCode HTTP status code (default: 302 temporary redirect)
     * @return void
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        if (!headers_sent()) {
            // Use HTTP header redirect
            header("Location: $url", true, $statusCode);
            exit;
        } else {
            // Fallback to JavaScript redirect if headers already sent
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            echo "<script>window.location.href='" . $safeUrl . "';</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
            exit;
        }
    }

    /**
     * Permanent redirect (301)
     *
     * @param string $url The URL to redirect to
     * @return void
     */
    public static function redirectPermanent(string $url): void
    {
        self::redirect($url, 301);
    }

    /**
     * Set HTTP header safely
     *
     * Only sets header if headers not already sent
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return bool True if header was set, false if headers already sent
     */
    public static function setHeader(string $name, string $value): bool
    {
        if (!headers_sent()) {
            header("$name: $value");
            return true;
        }
        return false;
    }

    /**
     * Set content type header
     *
     * @param string $type Content type (e.g., 'text/html', 'application/json')
     * @param string $charset Character encoding (default: UTF-8)
     * @return bool True if header was set, false if headers already sent
     */
    public static function setContentType(string $type, string $charset = 'UTF-8'): bool
    {
        return self::setHeader('Content-Type', "$type; charset=$charset");
    }

    /**
     * Set HTTP status code
     *
     * @param int $code HTTP status code (e.g., 200, 404, 500)
     * @return bool True if status was set, false if headers already sent
     */
    public static function setStatus(int $code): bool
    {
        if (!headers_sent()) {
            http_response_code($code);
            return true;
        }
        return false;
    }

    /**
     * Output JSON response
     *
     * Sets content type to application/json and outputs encoded data
     *
     * @param mixed $data Data to encode as JSON
     * @param int $statusCode HTTP status code (default: 200)
     * @param int $options JSON encoding options (default: pretty print + unescaped unicode/slashes)
     * @return void
     */
    public static function json($data, int $statusCode = 200, int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): void
    {
        self::setContentType('application/json');
        self::setStatus($statusCode);
        echo json_encode($data, $options);
    }

    /**
     * Output XML response
     *
     * Sets content type to application/xml
     *
     * @param string $xml XML content
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     */
    public static function xml(string $xml, int $statusCode = 200): void
    {
        self::setContentType('application/xml');
        self::setStatus($statusCode);
        echo $xml;
    }

    /**
     * Send file download
     *
     * Forces browser to download file
     *
     * @param string $filePath Path to file
     * @param string|null $downloadName Optional download filename (defaults to original filename)
     * @param string|null $mimeType Optional MIME type (auto-detected if not provided)
     * @return void
     */
    public static function download(string $filePath, ?string $downloadName = null, ?string $mimeType = null): void
    {
        if (!file_exists($filePath)) {
            self::setStatus(404);
            echo "File not found";
            return;
        }

        $downloadName = $downloadName ?? basename($filePath);
        $mimeType = $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream';

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
        }

        readfile($filePath);
    }

    /**
     * Send file for inline viewing (e.g., PDF in browser)
     *
     * @param string $filePath Path to file
     * @param string|null $filename Optional filename
     * @param string|null $mimeType Optional MIME type
     * @return void
     */
    public static function inline(string $filePath, ?string $filename = null, ?string $mimeType = null): void
    {
        if (!file_exists($filePath)) {
            self::setStatus(404);
            echo "File not found";
            return;
        }

        $filename = $filename ?? basename($filePath);
        $mimeType = $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream';

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filePath));
        }

        readfile($filePath);
    }

    /**
     * Clear all output buffers
     *
     * Useful for cleaning output before sending headers or redirects
     *
     * @return void
     */
    public static function clearBuffers(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * Check if headers have been sent
     *
     * @param string|null &$file Optional reference to receive filename where headers were sent
     * @param int|null &$line Optional reference to receive line number where headers were sent
     * @return bool True if headers have been sent
     */
    public static function headersSent(?string &$file = null, ?int &$line = null): bool
    {
        return headers_sent($file, $line);
    }

    /**
     * Set cache control headers
     *
     * @param int $maxAge Cache max age in seconds (0 = no cache)
     * @return bool True if headers were set
     */
    public static function cacheControl(int $maxAge = 0): bool
    {
        if ($maxAge > 0) {
            $expires = gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT';
            $success = self::setHeader('Cache-Control', "public, max-age=$maxAge");
            $success = self::setHeader('Expires', $expires) && $success;
            return $success;
        } else {
            // No cache
            $success = self::setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $success = self::setHeader('Pragma', 'no-cache') && $success;
            $success = self::setHeader('Expires', '0') && $success;
            return $success;
        }
    }

    /**
     * Disable caching completely
     *
     * Sets headers to prevent browser and proxy caching.
     * Used for dynamic content that should never be cached.
     *
     * Equivalent to ASP's Response.Expires = -1 or Response.Expires = 0
     *
     * @return bool True if headers were set
     */
    public static function noCache(): bool
    {
        $success = self::setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $success = self::setHeader('Pragma', 'no-cache') && $success;
        $success = self::setHeader('Expires', '0') && $success;
        return $success;
    }

    /**
     * Set cache expiration in minutes
     *
     * Sets both Cache-Control and Expires headers for browser caching.
     * Equivalent to ASP's Response.Expires = minutes
     *
     * @param int|float $minutes Number of minutes until content expires
     * @return bool True if headers were set
     */
    public static function cacheExpires(int|float $minutes): bool
    {
        $seconds = (int)($minutes * 60);
        $expires = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';
        $success = self::setHeader('Cache-Control', "public, max-age=$seconds");
        $success = self::setHeader('Expires', $expires) && $success;
        return $success;
    }

    /**
     * Enable CORS (Cross-Origin Resource Sharing)
     *
     * @param string $origin Allowed origin (default: *)
     * @param string $methods Allowed methods (default: GET, POST, PUT, DELETE, OPTIONS)
     * @param string $headers Allowed headers (default: Content-Type, Authorization)
     * @return bool True if headers were set
     */
    public static function enableCors(
        string $origin = '*',
        string $methods = 'GET, POST, PUT, DELETE, OPTIONS',
        string $headers = 'Content-Type, Authorization'
    ): bool {
        $success = self::setHeader('Access-Control-Allow-Origin', $origin);
        $success = self::setHeader('Access-Control-Allow-Methods', $methods) && $success;
        $success = self::setHeader('Access-Control-Allow-Headers', $headers) && $success;
        return $success;
    }

    /**
     * Send 404 Not Found response
     *
     * @param string $message Optional error message
     * @return void
     */
    public static function notFound(string $message = 'Not Found'): void
    {
        self::setStatus(404);
        self::setContentType('text/html');
        echo "<h1>404 Not Found</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }

    /**
     * Send 500 Internal Server Error response
     *
     * @param string $message Optional error message
     * @return void
     */
    public static function serverError(string $message = 'Internal Server Error'): void
    {
        self::setStatus(500);
        self::setContentType('text/html');
        echo "<h1>500 Internal Server Error</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }

    /**
     * Send 403 Forbidden response
     *
     * @param string $message Optional error message
     * @return void
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::setStatus(403);
        self::setContentType('text/html');
        echo "<h1>403 Forbidden</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }

    /**
     * Send 401 Unauthorized response
     *
     * @param string $message Optional error message
     * @param string $realm Optional authentication realm
     * @return void
     */
    public static function unauthorized(string $message = 'Unauthorized', string $realm = 'Restricted'): void
    {
        self::setStatus(401);
        self::setHeader('WWW-Authenticate', 'Basic realm="' . $realm . '"');
        self::setContentType('text/html');
        echo "<h1>401 Unauthorized</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }
}
