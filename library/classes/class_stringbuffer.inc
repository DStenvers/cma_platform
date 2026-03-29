<?php
use App\Library\File;

// Set buf = CreateObject("System.IO.StringWriter")

define("STRINGBUFFER_SWITCHMETHODSIZE", 32);
if (class_exists('StringBuffer')) { return; }
class StringBuffer
{

    private $buf;

    private $useStream;

    private $nSize;

    private $str;

    /**

    *   Construct

    */

    public function __construct()
    {

        $nSize = 0;

        $str = '';

        $useStream = false;

        SwitchStreamOn();

    }

    /**

    * Switchstreamon

    */

    public function SwitchStreamOn()
    {

        if (false) {

            $useStream = true;

            $buf = CreateObject('ADODB.Stream');

            $buf->Type = $adTypeText;

            if ('UTF-8'!= '') {

                $buf->Charset = 'UTF-8';

            }

            if ($str != '') {

                $buf->WriteText($str);

                $str = '';

            }

        }

    }

    /**

    *   Destruct

    */

    public function __destruct()
    {

        if ($useStream) {

            $buf = null;

        }

    }

    /**

    * Append

    *

    */

    public function Append($strValue)
    {

        $nSize = $nSize + (is_null($strValue) ? 0 : strlen($strValue));

        if ($useStream) {

            $buf->WriteText($strValue);

        } else {

            $str = $str . $strValue;

            if ($nSize > 1024 * STRINGBUFFER_SWITCHMETHODSIZE) {

                SwitchStreamOn();

            }

        }

    }

    /**

    * Appendline

    *

    */

    public function AppendLine($strValue)
    {

        Append($strValue . PHP_EOL);

    }

    /**

    * Clear

    */

    public function Clear()
    {

        if ($useStream) {

            $buf = null;

            $useStream = false;

        }

        $str = '';

    }

    /**

    * Tostring

    *

    */

    public function ToString()
    {

        if ($useStream) {

            $buf->position = 0;

            return $buf->readText();

        } else {

            return $str;

        }

    }

    /**

    * Getsize

    *

    */

    public function GetSize()
    {

        return $nSize;

    }

    /**

    * Savetofile

    *

    */

    public function SaveToFile($sFile)
    {

        if ($useStream) {

            $buf->SaveToFile($sFile, 2);

        } else {

            File::createAsciiFile($sFile, $str);

        }

    }

}

?>
