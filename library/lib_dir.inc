<?php
use App\Library\File;

// 2do: optimize this by reusing the FSO object, see if not setting it to nothing is a problem..
/**

* Lib Fileexists

*

*/

function lib_FileExists($strLocalPath)
{

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        return $fso->FileExists($strLocalPath);

        $fso = null;

    } else {

        return false;

    }

}

/**

* Lib Filedelete

*

*/

function lib_FileDelete($strLocalPath)
{

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        $fso->DeleteFile($strLocalPath);

    }

}

/**

* Lib Folderexists

*

*/

function lib_FolderExists($strLocalPath)
{

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        return $fso->folderexists($strLocalPath);

        $fso = null;

    } else {

        return false;

    }

}

/**

* Lib Filecopy

*

*/

function lib_FileCopy($strLocalPathOrig, $strLocalPathCopyTo)
{

    if (File::exists($strLocalPathOrig)) {

        $fso = new FileSystemObject();

        $fso->CopyFile($strLocalPathOrig, $strLocalPathCopyTo);

        return File::exists($strLocalPathCopyTo);

        $fso = null;

    } else {

        return false;

    }

}

/**

* Lib Createfolder

*

*/

function lib_CreateFolder($strLocalPath)
{

    $fso = null;

    $f = null;

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        if ($fso->folderexists($strLocalPath)) {

            return true;

        } else {

            // ON ERROR RESUME NEXT - Errors handled by ErrorHandler.php

            $f = $fso->CreateFolder($strLocalPath);

            return $f->Path != '';

            $f = null;

        }

        $fso = null;

    } else {

        return false;

    }

}

/**

* Lib Readasciifile

*

*/

function lib_ReadAsciiFile($strLocalPath)
{

    $fso = null;

    $f = null;

    $sFileContent = '';

    // "[" & strLocalPath & " niet gevonden]"

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        if ($fso->FileExists($strLocalPath) == true) {

            $f = $fso->OpenTextFile($strLocalPath, 1);

            if (is_object($f)) {

                $sFileContent = $f->ReadAll();

            }



        }

        $fso = null;

    }

    return $sFileContent;

}

/**

* Lib Createasciifile

*

*/

function lib_CreateAsciiFile($strLocalPath, $sFileContent)
{

    $fso = null;

    $f = null;

    // 2 is for writing!

    if ($strLocalPath != '') {

        $fso = new FileSystemObject();

        // ON ERROR RESUME NEXT - Errors handled by ErrorHandler.php

        $f = $fso->OpenTextFile($strLocalPath, 2, true);

        if ($f) {

            $f->WriteLine($sFileContent);

            if ($php_error_number > 0) {

                $f->DeleteFile($strLocalPath);

            }

            // ON ERROR GOTO 0 - Error handling via ErrorHandler.php



        }

        return $fso->fileExists($strLocalPath);

        $fso = null;

    } else {

        return false;

    }

}

?>
