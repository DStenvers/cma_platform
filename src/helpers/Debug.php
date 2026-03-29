<?php

namespace App\Library;

/**
 * Debug Helper Class
 *
 * Provides debugging utilities for development and testing.
 * Based on VBScript lib_debug.inc functionality.
 */
class Debug
{
    private static $enabled = false;
    private static $toFile = false;
    private static $toScreen = false;
    private static $jsonMode = false;
    private static $fileName = '';
    private static $includeFile = true;

    /**
     * Initialize debug settings based on environment
     */
    public static function init()
    {
        $env = Application::get('omgeving', '');
        static::$enabled = in_array(strtoupper($env), ['L', 'O', 'T']); // Local, Ontwikkel, Test
        static::$fileName = __DIR__ . '/../../debug.log';
    }

    /**
     * Enable or disable debugging
     */
    public static function setActive(bool $enabled)
    {
        static::$enabled = $enabled;
    }

    /**
     * Check if debugging is enabled
     */
    public static function getActive(): bool
    {
        return static::$enabled;
    }

    /**
     * Enable or disable file logging
     */
    public static function setToFile(bool $enabled)
    {
        static::$toFile = $enabled;
    }

    /**
     * Enable or disable screen output
     */
    public static function setToScreen(bool $enabled)
    {
        static::$toScreen = $enabled;
    }

    /**
     * Enable or disable JSON mode (suppresses HTML output to avoid breaking JSON responses)
     */
    public static function setJsonMode(bool $enabled)
    {
        static::$jsonMode = $enabled;
    }

    /**
     * Write a debug message
     */
    public static function write(string $message)
    {
        if (!static::$enabled) {
            return;
        }

        $output = static::$includeFile ? static::getCurrentUrl() . ': ' . $message : $message;

        // In JSON mode, write to file to avoid breaking JSON response
        if (static::$jsonMode) {
            static::writeToFile($output);
            return;
        }

        if (static::$toFile) {
            static::writeToFile($output);
        } else {
            // Check if response content type is HTML before outputting script/HTML tags
            $isHtml = true;
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                    $isHtml = false;
                    break;
                }
            }

            if (!$isHtml) {
                // Non-HTML response (JSON, plain text, etc.) - write to file instead
                static::writeToFile($output);
            } elseif (static::$toScreen) {
                echo "\n" . htmlspecialchars($output) . "<br>\n";
            } else {
                echo "\n<script>console.log(" . json_encode($output) . ");</script>\n";
            }
        }
    }

    /**
     * Write to debug file
     */
    private static function writeToFile(string $message)
    {
        try {
            $dir = dirname(static::$fileName);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(static::$fileName, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
        } catch (\Exception $e) {
            // Silently fail if can't write to file
        }
    }

    /**
     * Get current URL
     */
    private static function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . $host . $uri;
    }

    /**
     * Debug a recordset (PDOStatement result)
     */
    public static function recordset($rs)
    {
        if (!static::$enabled) {
            return;
        }

        if (!$rs || !is_object($rs)) {
            static::write('Recordset is empty or invalid');
            return;
        }

        try {
            $data = is_array($rs) ? $rs : $rs->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($data)) {
                static::write('Recordset is empty');
            } else {
                foreach ($data as $row) {
                    foreach ($row as $field => $value) {
                        static::write($field . ' => ' . $value);
                    }
                    static::write('---------------------------------- End of record');
                }
            }
        } catch (\Exception $e) {
            static::write('Error reading recordset: ' . $e->getMessage());
        }
    }

    /**
     * Debug POST data
     */
    public static function postContent(): string
    {
        $output = '<table><tr><th colspan="2" align="left">Form POST</th></tr>';
        foreach ($_POST as $name => $value) {
            $output .= '<tr><td>' . htmlspecialchars($name) . '&nbsp;</td><td>' . htmlspecialchars(print_r($value, true)) . '</td></tr>';
        }
        $output .= '</table>';
        return $output;
    }

    /**
     * Debug query string parameters
     */
    public static function paramContent(): string
    {
        $output = '<table><tr><th colspan="2" align="left">Parameters</th></tr>';
        foreach ($_GET as $name => $value) {
            $output .= '<tr><td>' . htmlspecialchars($name) . '&nbsp;</td><td>' . htmlspecialchars(print_r($value, true)) . '</td></tr>';
        }
        $output .= '</table>';
        return $output;
    }

    /**
     * Debug cookies
     */
    public static function cookies()
    {
        static::write(static::cookiesContent());
    }

    /**
     * Get cookies content as HTML table
     */
    public static function cookiesContent(): string
    {
        $output = '<table><tr><th colspan="2" align="left">Cookies</th></tr>';
        foreach ($_COOKIE as $name => $value) {
            $output .= '<tr><td>' . htmlspecialchars($name) . '&nbsp;</td><td>' . htmlspecialchars($value) . '</td></tr>';
        }
        $output .= '</table>';
        return $output;
    }

    /**
     * Debug an array
     */
    public static function array($array)
    {
        if (!static::$enabled) {
            return;
        }

        echo '<hr>';
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    echo htmlspecialchars(print_r($value, true)) . '<br/>';
                } else {
                    echo htmlspecialchars($value) . '<br/>';
                }
            }
        } else {
            echo 'Debug: This is not an array<br/>';
        }
    }

    /**
     * Debug a multi-dimensional array
     */
    public static function multiArray($array)
    {
        if (!static::$enabled) {
            return;
        }

        echo '<hr>Debug MultiArray:<br/>';
        if (is_array($array)) {
            foreach ($array as $row) {
                if (is_array($row)) {
                    echo htmlspecialchars(implode(' - ', $row)) . '<br/>';
                } else {
                    echo htmlspecialchars($row) . '<br/>';
                }
            }
        } else {
            echo 'Debug: This is not an array<br/>';
        }
    }

    /**
     * Debug a collection (associative array)
     */
    public static function collection($collection)
    {
        if (!static::$enabled) {
            return;
        }

        echo "\n<!-- Collection dump\n";
        foreach ($collection as $key => $value) {
            echo htmlspecialchars($key) . ' = ' . htmlspecialchars($value) . "\n";
        }
        echo "-->\n";
    }

    /**
     * Debug full recordset as HTML table
     */
    public static function fullRecordset($rs)
    {
        if (!static::$enabled) {
            return;
        }

        if (!$rs || !is_object($rs)) {
            echo 'Empty recordset';
            return;
        }

        try {
            $data = is_array($rs) ? $rs : $rs->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($data)) {
                echo 'Empty recordset';
                return;
            }

            echo '<table border="1">';

            // Header row
            echo '<tr>';
            foreach (array_keys($data[0]) as $field) {
                echo '<th>' . htmlspecialchars($field) . '</th>';
            }
            echo '</tr>';

            // Data rows
            foreach ($data as $row) {
                echo '<tr valign="top">';
                foreach ($row as $value) {
                    echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
                }
                echo '</tr>';
            }

            echo '</table>';
        } catch (\Exception $e) {
            echo 'Error reading recordset: ' . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Write debug settings to screen
     */
    public static function settingsWrite()
    {
        if (static::$enabled) {
            echo 'Debug: ON ';
            if (static::$toFile) {
                echo 'to file ' . static::$fileName;
            } else {
                if (static::$toScreen) {
                    echo 'to screen';
                } else {
                    echo 'to console';
                }
            }
        } else {
            echo 'Debug: OFF';
        }
        echo '<br>';
    }
}

// Auto-initialize on first load
Debug::init();
