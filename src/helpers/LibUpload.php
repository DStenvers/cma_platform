<?php
namespace App\Library;

/**
 * LibUpload - File upload handler (replacement for ASP libUpload class)
 *
 * Handles file uploads using PHP's native $_FILES functionality.
 * Provides compatibility layer for converted ASP code.
 */
class LibUpload
{
    /** @var bool Whether to generate random filename */
    public $Random = false;

    /** @var string Upload path relative to document root */
    public $Path = '';

    /** @var string Form field name containing the file */
    public $Fieldname = 'file';

    /** @var string Original or processed filename */
    public $Filename = '';

    /** @var string Full local filesystem path */
    public $FullLocalPath = '';

    /** @var array Form field values (for ASP objUpload("fieldname") compatibility) */
    private $formFields = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Populate form fields from POST data
        foreach ($_POST as $key => $value) {
            $this->formFields[$key] = (object)['value' => $value];
        }
    }

    /**
     * Magic method to access form fields like objUpload("fieldname")
     * In converted code: $objUpload->objUpload("hid_path")->value
     *
     * @param string $name Field name
     * @return object|null Field object with value property
     */
    public function objUpload($name)
    {
        if (isset($this->formFields[$name])) {
            return $this->formFields[$name];
        }
        // Return empty object if field not found
        return (object)['value' => ''];
    }

    /**
     * Save the uploaded file
     *
     * @return bool True on success, false on failure
     */
    public function Save()
    {
        $fieldName = $this->Fieldname;

        // Check if file was uploaded
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $uploadedFile = $_FILES[$fieldName];

        // Get original filename
        $originalFilename = basename($uploadedFile['name']);

        // Generate random filename if requested
        if ($this->Random) {
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $this->Filename = uniqid('upload_', true) . '.' . $extension;
        } else {
            $this->Filename = $originalFilename;
        }

        // Calculate full local path
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $uploadPath = rtrim($this->Path, '/\\');

        // Ensure path starts with /
        if ($uploadPath && $uploadPath[0] !== '/') {
            $uploadPath = '/' . $uploadPath;
        }

        $this->FullLocalPath = $uploadPath . '/';
        $fullPath = $documentRoot . $uploadPath;

        // Create directory if it doesn't exist
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Full file path
        $destinationPath = $fullPath . '/' . $this->Filename;

        // Move uploaded file
        if (move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
            return true;
        }

        return false;
    }

    /**
     * Get uploaded file info
     *
     * @return array File information
     */
    public function getFileInfo()
    {
        return [
            'filename' => $this->Filename,
            'path' => $this->Path,
            'fullPath' => $this->FullLocalPath . $this->Filename,
            'random' => $this->Random
        ];
    }

    /**
     * Magic method to handle VBScript property-as-method calls
     * In VBScript, you can call objUpload.Filename or objUpload.Filename()
     * This allows converted code like $objUpload->Filename() to work
     *
     * @param string $name Method name (which is actually a property name)
     * @param array $arguments Arguments passed (usually empty for property access)
     * @return mixed Property value
     */
    public function __call($name, $arguments)
    {
        // Check if this is actually a public property
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \BadMethodCallException("Call to undefined method " . __CLASS__ . "::$name()");
    }
}
