<?php
use App\Library\Request;
use App\Library\Str;

define("FORM_UNIQUE_CODE_FLDNAME", 'UNIQUE_CODE');
define("FORM_UNIQUE_CODE_TABLE", 'tblFormUniekeCodes');

require_once __DIR__ . '/lib_date.inc';
// ================================================================================================= Generic functions

/**
 * ScriptingDictionary - PHP stub for VBScript Scripting.Dictionary COM object
 * Implements ArrayAccess so $dict[$key] and $dict[$key] = $value work.
 * Implements IteratorAggregate so foreach($dict as $key) works.
 * Provides add(), remove(), RemoveAll(), item(), exists() methods.
 */
class ScriptingDictionary implements ArrayAccess, IteratorAggregate, Countable
{
    private $data = [];

    public function add($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }

    public function RemoveAll()
    {
        $this->data = [];
    }

    public function item($key)
    {
        return $this->data[$key] ?? null;
    }

    public function exists($key)
    {
        return array_key_exists($key, $this->data);
    }

    public function keys()
    {
        return array_keys($this->data);
    }

    public function items()
    {
        return array_values($this->data);
    }

    // ArrayAccess
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    // IteratorAggregate
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    // Countable
    public function count(): int
    {
        return count($this->data);
    }

    // Magic getter for VBScript-style property access (Count, Items)
    public function __get($name)
    {
        $lower = strtolower($name);
        if ($lower === 'count') {
            return count($this->data);
        }
        if ($lower === 'items') {
            return array_values($this->data);
        }
        return null;
    }
}

/**
 * CreateObject - Stub replacement for VBScript/ASP CreateObject()
 * In ASP, this created COM objects like Scripting.Dictionary, Excel.Application, etc.
 * Returns a ScriptingDictionary for Dictionary objects, or a stdClass for others.
 *
 * @param string $progId The COM ProgID (e.g. 'Scripting.Dictionary')
 * @return ScriptingDictionary|stdClass A stub object
 */
function CreateObject($progId)
{
    error_log("CreateObject() stub called for: " . $progId . " - COM objects are not supported in PHP");
    if (stripos($progId, 'Scripting.Dictionary') !== false) {
        return new ScriptingDictionary();
    }
    // For all other COM objects, return a basic stdClass to prevent null reference errors
    return new stdClass();
}

/**
* Iif
*
*/
function iif($condition, $true_val, $false_val)
{
    if ($condition) {
        return $true_val;
    } else {
        return $false_val;
    }
}
// Skipped: Max() - using PHP built-in function
// Skipped: Min() - using PHP built-in function
/**
* Lib Querystring Int
*
*/
function Lib_QueryString_Int($sParam)
{
    return Str::numbersOnly(Request::query($sParam, ''));
}
/**
* Lib Querystring Intandcomma
*
*/
function Lib_QueryString_IntAndComma($sParam)
{
    return Str::numbersOnlyAndComma(Request::query($sParam, ''));
}
// Zoals hierboven, maar staat ook Guids toe.
/**
* Lib Querystring Intandguid
*
*/
function Lib_QueryString_IntAndGuid($sParam)
{
    $sTmp = Request::query($sParam, '');
    return (substr($sTmp, 0, max(0, min(1, strlen($sTmp)))) == '{' ? $sTmp : Str::numbersOnly($sTmp));
}
/**
* Lib Cleanuserinput
*
*/
function Lib_CleanUserInput($sInput)
{
    // Completely remove all scripts
    return preg_replace('/<script(.|\n)+?<\/script>/is', '', $sInput);
}
/**
* Lib Random
*
*/
function lib_random($minimum, $maximum)
{
    global $Rnd;
    mt_srand();
    return Max(Min(round($maximum * (mt_rand() / mt_getrandmax()), 0), $maximum), $minimum);
}
/**
* Lib Createguid
*
*/
function lib_CreateGUID()
{
    // PHP replacement for COM Scriptlet.TypeLib GUID generation
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
// Create a sleep/wait function using WScript.Shell
/**
* Lib Sleep
*
*/
function lib_Sleep($intMilliseconds)
{
    $objShell = new ShellObject();
    $WScript->Sleep($intMilliseconds);
    $objShell = null;
}
?>
