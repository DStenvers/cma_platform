<?php

namespace App\Library;

/**
 * HttpClient - cURL-based HTTP client for making HTTP requests
 * Replaces MSXML2.ServerXMLHTTP COM object from classic ASP
 */
class HttpClient
{
    private $ch;
    private $status = 0;
    private $statusText = '';
    private $responseText = '';
    private $responseBody = '';
    private $headers = [];
    private $timeout = 30000; // milliseconds
    private $connectTimeout = 30000;
    private $error = '';
    private $errorCode = 0;

    public function __construct()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
    }

    /**
     * Set timeouts (in milliseconds, like MSXML2.ServerXMLHTTP)
     * @param int $resolve Resolve timeout (unused in cURL, kept for compatibility)
     * @param int $connect Connection timeout
     * @param int $send Send timeout (unused in cURL, kept for compatibility)
     * @param int $receive Receive timeout
     */
    public function setTimeouts($resolve, $connect, $send, $receive)
    {
        $this->connectTimeout = $connect;
        $this->timeout = $receive;
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT_MS, $connect);
        curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $receive);
    }

    /**
     * Open a connection (mimics ServerXMLHTTP.open)
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url URL to request
     * @param bool $async Async mode (ignored in PHP - always synchronous)
     */
    public function open($method, $url, $async = false)
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (strtoupper($method) === 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
        }
    }

    /**
     * Set a request header
     * @param string $name Header name
     * @param string $value Header value
     */
    public function setRequestHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * Send the request
     * @param string|null $body Request body (for POST requests)
     */
    public function send($body = null)
    {
        // Apply headers
        $headerArray = [];
        foreach ($this->headers as $name => $value) {
            $headerArray[] = "$name: $value";
        }
        if (!empty($headerArray)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        // Set body for POST/PUT requests
        if ($body !== null) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
        }

        // Capture response headers
        $responseHeaders = [];
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) return $len;
            $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });

        // Execute request
        $this->responseText = curl_exec($this->ch);
        $this->responseBody = $this->responseText;
        $this->status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->errorCode = curl_errno($this->ch);
        $this->error = curl_error($this->ch);

        // Set status text based on status code
        $this->statusText = $this->getStatusTextForCode($this->status);
    }

    /**
     * Get HTTP status code
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get HTTP status text
     * @return string
     */
    public function getStatusText()
    {
        return $this->statusText;
    }

    /**
     * Get response text
     * @return string
     */
    public function getResponseText()
    {
        return $this->responseText;
    }

    /**
     * Get response body (binary)
     * @return string
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Get error message if any
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get error code if any
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Check if request was successful
     * @return bool
     */
    public function isSuccess()
    {
        return $this->errorCode === 0 && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Close the connection
     */
    public function close()
    {
        if ($this->ch) {
            curl_close($this->ch);
            $this->ch = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Static helper for simple GET requests
     * @param string $url URL to fetch
     * @param array $headers Optional headers
     * @param int $timeout Timeout in milliseconds
     * @return HttpClient
     */
    public static function get($url, $headers = [], $timeout = 30000)
    {
        $client = new self();
        $client->setTimeouts($timeout, $timeout, $timeout, $timeout);
        $client->open('GET', $url);
        foreach ($headers as $name => $value) {
            $client->setRequestHeader($name, $value);
        }
        $client->send();
        return $client;
    }

    /**
     * Static helper for simple POST requests
     * @param string $url URL to post to
     * @param string $body Request body
     * @param array $headers Optional headers
     * @param int $timeout Timeout in milliseconds
     * @return HttpClient
     */
    public static function post($url, $body = '', $headers = [], $timeout = 30000)
    {
        $client = new self();
        $client->setTimeouts($timeout, $timeout, $timeout, $timeout);
        $client->open('POST', $url);
        foreach ($headers as $name => $value) {
            $client->setRequestHeader($name, $value);
        }
        $client->send($body);
        return $client;
    }

    /**
     * Download a file (binary content) to a local path
     * @param string $url URL to download from
     * @param string $localPath Local file path to save to
     * @param int $timeout Timeout in milliseconds
     * @return bool True on success
     */
    public static function downloadFile($url, $localPath, $timeout = 60000)
    {
        $client = new self();
        $client->setTimeouts($timeout, $timeout, $timeout, $timeout);
        $client->open('GET', $url);
        $client->send();

        if ($client->getStatus() == 200) {
            $dir = dirname($localPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            return file_put_contents($localPath, $client->getResponseBody()) !== false;
        }
        return false;
    }

    /**
     * Get status text for HTTP status code
     */
    private function getStatusTextForCode($code)
    {
        $texts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
        return $texts[$code] ?? 'Unknown';
    }
}
