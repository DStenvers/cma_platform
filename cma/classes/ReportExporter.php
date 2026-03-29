<?php

namespace Cma;

use App\Library\Response;

/**
 * Report Exporter
 *
 * Exports report data to various formats: CSV, Excel (CSV-based), and HTML table.
 * PDF export is deferred (requires mPDF library).
 */
class ReportExporter
{
    /**
     * Export formats
     */
    public const FORMAT_CSV = 'csv';
    public const FORMAT_EXCEL = 'excel';
    public const FORMAT_HTML = 'html';
    public const FORMAT_JSON = 'json';
    public const FORMAT_PDF = 'pdf';

    /**
     * Export data to CSV format
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers (optional - uses data keys if empty)
     * @param string $delimiter Field delimiter (default: semicolon for Excel NL compatibility)
     * @param string $enclosure Field enclosure
     * @return string CSV content
     */
    public static function toCSV(array $data, array $headers = [], string $delimiter = ';', string $enclosure = '"'): string
    {
        $output = fopen('php://temp', 'r+');

        // Add BOM for UTF-8 Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Determine headers
        if (empty($headers) && !empty($data)) {
            $headers = array_keys($data[0]);
        }

        // Write header row
        // Note: PHP 8.4+ requires explicit escape parameter (5th param)
        $escape = '\\';
        if (!empty($headers)) {
            fputcsv($output, $headers, $delimiter, $enclosure, $escape);
        }

        // Write data rows
        foreach ($data as $row) {
            // Ensure row values are in header order
            $values = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                // Convert booleans
                if (is_bool($value)) {
                    $value = $value ? 'Ja' : 'Nee';
                }
                // Handle null
                if ($value === null) {
                    $value = '';
                }
                $values[] = $value;
            }
            fputcsv($output, $values, $delimiter, $enclosure, $escape);
        }

        // Get content
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Export data to Excel format (CSV with Excel-compatible headers)
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @return string Excel-compatible content
     */
    public static function toExcel(array $data, array $headers = []): string
    {
        // Excel opens CSV files with semicolon delimiter in Dutch locale
        return self::toCSV($data, $headers, ';', '"');
    }

    /**
     * Export data to JSON format
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers (optional metadata)
     * @param bool $pretty Pretty print JSON (default: true for readability)
     * @return string JSON content
     */
    public static function toJSON(array $data, array $headers = [], bool $pretty = true): string
    {
        // Clean data to ensure JSON encoding works
        $cleanData = self::cleanDataForJson($data);

        $output = [
            'meta' => [
                'generated' => date('c'),
                'rowCount' => count($cleanData),
                'columns' => $headers ?: (!empty($cleanData) ? array_keys($cleanData[0]) : [])
            ],
            'data' => $cleanData
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $result = json_encode($output, $flags);

        // Check for encoding failure
        if ($result === false) {
            $error = json_last_error_msg();
            // Return error as JSON
            return json_encode([
                'meta' => [
                    'generated' => date('c'),
                    'error' => 'JSON encoding failed: ' . $error
                ],
                'data' => []
            ], $flags);
        }

        return $result;
    }

    /**
     * Clean data for JSON encoding (fix encoding issues)
     *
     * @param array $data
     * @return array
     */
    private static function cleanDataForJson(array $data): array
    {
        $cleaned = [];
        foreach ($data as $row) {
            $cleanRow = [];
            foreach ($row as $key => $value) {
                // Handle different value types
                if (is_string($value)) {
                    // Fix invalid UTF-8 sequences
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    // Remove null bytes
                    $value = str_replace("\0", '', $value);
                } elseif (is_resource($value)) {
                    // Convert resources to string representation
                    $value = '[resource]';
                } elseif (is_object($value) && !($value instanceof \JsonSerializable)) {
                    // Convert non-serializable objects to string
                    $value = method_exists($value, '__toString') ? (string)$value : '[object]';
                }
                $cleanRow[$key] = $value;
            }
            $cleaned[] = $cleanRow;
        }
        return $cleaned;
    }

    /**
     * Export data to HTML table format
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @param string $title Optional table title
     * @return string HTML content
     */
    public static function toHTML(array $data, array $headers = [], string $title = ''): string
    {
        // Determine headers
        if (empty($headers) && !empty($data)) {
            $headers = array_keys($data[0]);
        }

        $html = '<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title ?: 'Rapport') . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: var(--font-size-sm); }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
        h1 { font-size: var(--font-size-xl); margin-bottom: 10px; }
        .meta { color: #666; font-size: var(--font-size-xs); margin-bottom: 20px; }
    </style>
</head>
<body>';

        if (!empty($title)) {
            $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        }

        $html .= '<div class="meta">Gegenereerd: ' . date('d-m-Y H:i:s') . ' | ' . count($data) . ' rij(en)</div>';

        $html .= '<table>
<thead>
<tr>';

        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }

        $html .= '</tr>
</thead>
<tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                // Convert booleans
                if (is_bool($value)) {
                    $value = $value ? 'Ja' : 'Nee';
                }
                // Handle null
                if ($value === null) {
                    $value = '';
                }
                $html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
</table>
</body>
</html>';

        return $html;
    }

    /**
     * Send CSV download to browser
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Download filename (without extension)
     */
    public static function downloadCSV(array $data, array $headers = [], string $filename = 'rapport'): void
    {
        $content = self::toCSV($data, $headers);
        self::validateExportContent($content, 'CSV');
        $safeFilename = self::sanitizeFilename($filename);

        Response::noCache();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '.csv"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    /**
     * Send Excel download to browser
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Download filename (without extension)
     */
    public static function downloadExcel(array $data, array $headers = [], string $filename = 'rapport'): void
    {
        $content = self::toExcel($data, $headers);
        self::validateExportContent($content, 'Excel');
        $safeFilename = self::sanitizeFilename($filename);

        Response::noCache();
        // Use CSV with Excel MIME type - Excel will open it correctly
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '.xls"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    /**
     * Send HTML download to browser (for Word import)
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Download filename (without extension)
     * @param string $title Report title
     */
    public static function downloadHTML(array $data, array $headers = [], string $filename = 'rapport', string $title = ''): void
    {
        $content = self::toHTML($data, $headers, $title);
        self::validateExportContent($content, 'HTML');
        $safeFilename = self::sanitizeFilename($filename);

        Response::noCache();
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '.doc"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    /**
     * Send JSON download to browser
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $headers Column headers
     * @param string $filename Download filename (without extension)
     */
    public static function downloadJSON(array $data, array $headers = [], string $filename = 'rapport'): void
    {
        $content = self::toJSON($data, $headers);
        self::validateExportContent($content, 'JSON');
        $safeFilename = self::sanitizeFilename($filename);

        Response::noCache();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '.json"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    /**
     * Get available export formats
     *
     * @return array Array of format info
     */
    public static function getAvailableFormats(): array
    {
        return [
            [
                'value' => self::FORMAT_CSV,
                'label' => 'CSV',
                'extension' => 'csv',
                'mimeType' => 'text/csv',
                'available' => true
            ],
            [
                'value' => self::FORMAT_EXCEL,
                'label' => 'Excel',
                'extension' => 'xls',
                'mimeType' => 'application/vnd.ms-excel',
                'available' => true
            ],
            [
                'value' => self::FORMAT_JSON,
                'label' => 'JSON',
                'extension' => 'json',
                'mimeType' => 'application/json',
                'available' => true
            ],
            [
                'value' => self::FORMAT_HTML,
                'label' => 'Word',
                'extension' => 'doc',
                'mimeType' => 'application/msword',
                'available' => true
            ],
            [
                'value' => self::FORMAT_PDF,
                'label' => 'PDF',
                'extension' => 'pdf',
                'mimeType' => 'application/pdf',
                'available' => false,
                'disabledReason' => 'Komt binnenkort beschikbaar'
            ]
        ];
    }

    /**
     * Export using QueryBuilder result
     *
     * @param QueryBuilder $builder Query builder with definition and connection set
     * @param string $format Export format
     * @param string $filename Download filename
     * @param string $title Report title (for HTML)
     */
    public static function exportFromBuilder(QueryBuilder $builder, string $format, string $filename = 'rapport', string $title = ''): void
    {
        // Execute query without limit for export
        $result = $builder->executePreview(0);

        if (!$result['success']) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Fout bij uitvoeren query: ' . ($result['error'] ?? 'Onbekende fout');
            exit;
        }

        $data = $result['data'];
        $headers = $result['columns'];

        // Check if data is empty
        if (empty($data)) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Geen gegevens gevonden om te exporteren';
            exit;
        }

        switch ($format) {
            case self::FORMAT_CSV:
                self::downloadCSV($data, $headers, $filename);
                break;

            case self::FORMAT_EXCEL:
                self::downloadExcel($data, $headers, $filename);
                break;

            case self::FORMAT_JSON:
                self::downloadJSON($data, $headers, $filename);
                break;

            case self::FORMAT_HTML:
                self::downloadHTML($data, $headers, $filename, $title);
                break;

            case self::FORMAT_PDF:
                // PDF not implemented yet
                Response::noCache();
                header('Content-Type: text/plain; charset=utf-8');
                echo 'PDF export is nog niet beschikbaar';
                exit;

            default:
                // Default to CSV
                self::downloadCSV($data, $headers, $filename);
        }
    }

    /**
     * Sanitize filename for download
     *
     * @param string $filename
     * @return string
     */
    private static function sanitizeFilename(string $filename): string
    {
        // Remove or replace unsafe characters
        $safe = preg_replace('/[^a-zA-Z0-9\-_\. ]/', '', $filename);
        // Replace spaces with underscores
        $safe = str_replace(' ', '_', $safe);
        // Limit length
        if (strlen($safe) > 100) {
            $safe = substr($safe, 0, 100);
        }
        // Default if empty
        if (empty($safe)) {
            $safe = 'rapport';
        }
        return $safe;
    }

    /**
     * Validate export content is not empty
     *
     * @param string $content Generated file content
     * @param string $format Format name for error message
     */
    private static function validateExportContent(string $content, string $format): void
    {
        // Check for completely empty content
        if (empty($content)) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo "Fout: {$format} bestand is leeg";
            exit;
        }

        // Check minimum content length (BOM + header row minimum)
        // BOM is 3 bytes, so anything under 10 bytes is likely just BOM or whitespace
        $minLength = 10;
        if (strlen($content) < $minLength) {
            Response::noCache();
            header('Content-Type: text/plain; charset=utf-8');
            echo "Fout: {$format} bestand bevat geen gegevens";
            exit;
        }
    }

    /**
     * Apply aggregations to data (sum, avg, count, min, max)
     *
     * @param array $data Data rows
     * @param array $totals Totals configuration from report definition
     * @return array ['data' => array, 'grandTotal' => array|null]
     */
    public static function applyTotals(array $data, array $totals): array
    {
        if (empty($totals) || empty($data)) {
            return ['data' => $data, 'grandTotal' => null];
        }

        $showGrandTotal = $totals['showGrandTotal'] ?? false;
        $fields = $totals['fields'] ?? [];

        if (!$showGrandTotal || empty($fields)) {
            return ['data' => $data, 'grandTotal' => null];
        }

        // Calculate grand totals
        $grandTotal = [];
        foreach ($fields as $fieldConfig) {
            $field = $fieldConfig['field'] ?? '';
            $aggregation = $fieldConfig['aggregation'] ?? 'sum';

            if (empty($field)) {
                continue;
            }

            $values = array_column($data, $field);
            $numericValues = array_filter($values, 'is_numeric');

            switch ($aggregation) {
                case 'sum':
                    $grandTotal[$field] = array_sum($numericValues);
                    break;

                case 'avg':
                    $count = count($numericValues);
                    $grandTotal[$field] = $count > 0 ? array_sum($numericValues) / $count : 0;
                    break;

                case 'count':
                    $grandTotal[$field] = count($values);
                    break;

                case 'min':
                    $grandTotal[$field] = !empty($numericValues) ? min($numericValues) : 0;
                    break;

                case 'max':
                    $grandTotal[$field] = !empty($numericValues) ? max($numericValues) : 0;
                    break;
            }
        }

        return [
            'data' => $data,
            'grandTotal' => $grandTotal
        ];
    }
}
