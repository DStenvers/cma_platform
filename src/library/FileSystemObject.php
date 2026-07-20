<?php

namespace App\Library;

/**
 * FileSystemObject — PHP port of VBScript's Scripting.FileSystemObject.
 *
 * Converted ASP code does `Set fso = CreateObject("Scripting.FileSystemObject")`
 * then `fso.FileExists(p)`, `fso.CreateTextFile(p)`, `fso.OpenTextFile(p, mode)`,
 * `fso.GetFolder(p).Files`, etc. This class maps that API onto native PHP file
 * functions so the converted pages keep working unchanged.
 *
 * A global-namespace alias `FileSystemObject` is registered in
 * config/global_functions.php so the converter's bare `new FileSystemObject()`
 * resolves. Methods are case-insensitive (VBScript semantics) via __call.
 *
 * NOTE on paths: this class operates on paths AS GIVEN (raw file functions), it
 * does NOT delegate to App\Library\File. VBScript FSO never resolves virtual
 * paths — the ASP code calls Server.MapPath() explicitly BEFORE handing an
 * absolute path to the FSO. App\Library\File re-runs Server::mapPath() on every
 * call, which double-maps (and mangles absolute Windows paths, since mapPath
 * treats a `C:/...` path as relative), so File is the wrong backend for FSO.
 */
class FileSystemObject
{
    // VBScript IOMode constants.
    const ForReading = 1;
    const ForWriting = 2;
    const ForAppending = 8;

    public function FileExists($path)   { return $path !== null && $path !== '' && is_file($path); }
    public function FolderExists($path) { return $path !== null && $path !== '' && is_dir($path); }

    public function DeleteFile($path, $force = false) { return @unlink($path); }
    public function DeleteFolder($path, $force = false) { return @rmdir($path); }

    public function CreateFolder($path) { @mkdir($path, 0777, true); return new FsoFolder($path); }
    public function MoveFile($src, $dst) { return @rename($src, $dst); }
    public function CopyFile($src, $dst, $overwrite = true) { return @copy($src, $dst); }

    /**
     * CreateTextFile(path, overwrite=true) — open a new file for writing.
     */
    public function CreateTextFile($path, $overwrite = true, $unicode = false)
    {
        if (!$overwrite && is_file($path)) {
            throw new \RuntimeException('File already exists: ' . $path);
        }
        return new TextStream($path, 'w');
    }

    /**
     * OpenTextFile(path, iomode=1, create=false) — 1 read, 2 write, 8 append.
     */
    public function OpenTextFile($path, $iomode = self::ForReading, $create = false, $format = 0)
    {
        switch ((int)$iomode) {
            case self::ForWriting:   $mode = 'w'; break;
            case self::ForAppending: $mode = 'a'; break;
            case self::ForReading:
            default:                 $mode = 'r'; break;
        }
        if ($mode === 'r' && !is_file($path) && $create) {
            @touch($path);
        }
        return new TextStream($path, $mode);
    }

    public function GetFolder($path) { return new FsoFolder($path); }
    public function GetFile($path)   { return new FsoFile($path); }

    public function GetExtensionName($path)     { return pathinfo($path, PATHINFO_EXTENSION); }
    public function GetBaseName($path)          { return pathinfo($path, PATHINFO_FILENAME); }
    public function GetFileName($path)          { return basename($path); }
    public function GetParentFolderName($path)  { return dirname($path); }
    public function GetTempName()               { return 'tmp' . substr(md5((string)mt_rand()), 0, 8) . '.tmp'; }
    public function BuildPath($a, $b)           { return rtrim($a, '/\\') . DIRECTORY_SEPARATOR . ltrim($b, '/\\'); }

    /** VBScript method calls are case-insensitive; route unknown casings. */
    public function __call($name, $args)
    {
        foreach (get_class_methods($this) as $m) {
            if (strcasecmp($m, $name) === 0 && $m !== '__call') {
                return $this->$m(...$args);
            }
        }
        throw new \BadMethodCallException('FileSystemObject::' . $name . '() is not defined');
    }
}

/**
 * TextStream — VBScript file handle returned by CreateTextFile/OpenTextFile.
 */
class TextStream
{
    private $fh;

    public function __construct($path, $mode)
    {
        $this->fh = @fopen($path, $mode);
        if ($this->fh === false) {
            throw new \RuntimeException('Cannot open file: ' . $path);
        }
    }

    public function Write($str)     { if ($this->fh) { fwrite($this->fh, (string)$str); } }
    public function WriteLine($str = '') { if ($this->fh) { fwrite($this->fh, (string)$str . "\r\n"); } }
    public function WriteBlankLines($n = 1) { if ($this->fh) { fwrite($this->fh, str_repeat("\r\n", (int)$n)); } }

    public function ReadLine()
    {
        if (!$this->fh) { return ''; }
        $line = fgets($this->fh);
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    public function Read($n)  { return $this->fh ? (string)fread($this->fh, (int)$n) : ''; }
    public function ReadAll() { return $this->fh ? (string)stream_get_contents($this->fh) : ''; }

    public function SkipLine() { if ($this->fh) { fgets($this->fh); } }
    public function Close()    { if ($this->fh) { fclose($this->fh); $this->fh = null; } }

    /** AtEndOfStream / AtEndOfLine as read-only properties. */
    public function __get($name)
    {
        $u = strtoupper((string)$name);
        if ($u === 'ATENDOFSTREAM' || $u === 'ATENDOFLINE') {
            return !$this->fh || feof($this->fh);
        }
        return null;
    }

    public function __call($name, $args)
    {
        $map = ['atendofstream' => 'AtEndOfStream'];
        if (isset($map[strtolower($name)])) { return $this->__get($map[strtolower($name)]); }
        throw new \BadMethodCallException('TextStream::' . $name . '() is not defined');
    }
}

/**
 * FsoFolder — VBScript Folder object (subset), from FileSystemObject::GetFolder().
 */
class FsoFolder
{
    private $path;
    public function __construct($path) { $this->path = rtrim($path, '/\\'); }

    /** Files — array of FsoFile for each file directly in the folder. */
    public function Files()
    {
        $out = [];
        foreach (@scandir($this->path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $full = $this->path . DIRECTORY_SEPARATOR . $entry;
            if (is_file($full)) { $out[] = new FsoFile($full); }
        }
        return $out;
    }

    /** SubFolders — array of FsoFolder for each subdirectory. */
    public function SubFolders()
    {
        $out = [];
        foreach (@scandir($this->path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $full = $this->path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) { $out[] = new FsoFolder($full); }
        }
        return $out;
    }

    public function name() { return basename($this->path); }
    public function path() { return $this->path; }

    public function __get($name)
    {
        $u = strtoupper((string)$name);
        if ($u === 'NAME') { return basename($this->path); }
        if ($u === 'PATH') { return $this->path; }
        return null;
    }
}

/**
 * FsoFile — VBScript File object (subset), from Folder::Files() / GetFile().
 * Name/Path/Size/DateLastModified work as both method calls and properties;
 * DateLastModified returns a lexicographically-comparable "Y-m-d H:i:s" string
 * (matching how converted code compares it against date strings).
 */
class FsoFile
{
    private $path;
    public function __construct($path) { $this->path = $path; }

    public function name() { return basename($this->path); }
    public function path() { return $this->path; }
    public function size() { return is_file($this->path) ? (int)filesize($this->path) : 0; }
    public function dateLastModified()
    {
        return is_file($this->path) ? date('Y-m-d H:i:s', filemtime($this->path)) : '';
    }
    public function Delete($force = false) { return @unlink($this->path); }

    public function __get($name)
    {
        switch (strtoupper((string)$name)) {
            case 'NAME': return basename($this->path);
            case 'PATH': return $this->path;
            case 'SIZE': return $this->size();
            case 'DATELASTMODIFIED': return $this->dateLastModified();
            default: return null;
        }
    }

    public function __call($name, $args)
    {
        switch (strtolower($name)) {
            case 'name': return basename($this->path);
            case 'path': return $this->path;
            case 'size': return $this->size();
            case 'datelastmodified': return $this->dateLastModified();
            case 'delete': return $this->Delete(...$args);
        }
        throw new \BadMethodCallException('FsoFile::' . $name . '() is not defined');
    }
}
