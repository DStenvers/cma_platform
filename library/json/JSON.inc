<?php
/**
 * PHP wrapper for legacy aspJSON class compatibility.
 * Uses native PHP json_encode/json_decode functions.
 *
 * Note: If bootstrap.inc is loaded first, it provides AspJSON via class_alias.
 * This file is kept for backward compatibility with files that include it directly.
 */
use App\Library\Arr;

// Skip if class already exists (e.g., from bootstrap.inc class_alias)
if (class_exists('aspJSON', false) || class_exists('AspJSON', false) || class_exists('ASPJSON', false)) {
    // VBS: return;
}

if (class_exists('aspJSON')) { return; }
class aspJSON
{
    public $data = [];

    public function __construct()
    {
        $this->data = [];
    }

    /**
     * Load and parse JSON string
     * @param string $inputsource JSON string or URL
     */
    public function loadJSON($inputsource)
    {
        $inputsource = trim($inputsource);

        if (strlen($inputsource) == 0) {
            throw new Exception("loadJSON Error: No data to load.");
        }

        // Check if it's a URL (doesn't start with { or [)
        $firstChar = substr($inputsource, 0, 1);
        if ($firstChar !== '{' && $firstChar !== '[') {
            // Fetch from URL
            $inputsource = file_get_contents($inputsource);
            if ($inputsource === false) {
                throw new Exception("loadJSON Error: Could not fetch URL.");
            }
        }

        // Decode JSON to associative array
        $this->data = json_decode($inputsource, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("loadJSON Error: " . json_last_error_msg());
        }

        if ($this->data === null) {
            $this->data = [];
        }
    }

    /**
     * Output JSON string
     * @return string JSON encoded data
     */
    public function JSONoutput()
    {
        return json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create a new collection (array)
     * @return array
     */
    public function Collection()
    {
        return [];
    }

    /**
     * Add item to collection
     * @param array $dictobj
     * @return array Reference to new item
     */
    public function AddToCollection(&$dictobj)
    {
        if (!Arr::isArray($dictobj)) {
            throw new Exception("AddToCollection Error: Not a collection.");
        }
        $newIndex = count($dictobj);
        $dictobj[$newIndex] = [];
        return $dictobj[$newIndex];
    }
}
?>
