<?php
use App\Library\Application;
use App\Library\Error;
use App\Library\Server;
use App\Library\File;

if (class_exists('MarketingUrl')) { return; }
class MarketingUrl
{
    private $MarketUrlDir;
    private $MarketUrlOldDir;
    private $MarketUrlFileName;
    private $MarketUrlPage;
    private $MarketUrlRedirect;
    private $MarketUrlPageContent;
    private $MarketUrlFso;
    private $MarketURLBasePath;
    /**
    * Create
    *
    */
    public function Create()
    {
        $this->CleanFolder();
        // always recreate file!
        if (File::createFolder(Server::mapPath(Application::get('base_path', '') . $this->MarketURLBasePath . $this->MarketUrlDir))) {
            $this->CreateFile();
        }
    }
    /**
    * Cleanfolder
    *
    */
    public function CleanFolder()
    {
        // remove old file and directory
        $sFullSpec = null;
        $MyFile = null;
        if ($this->MarketUrlOldDir != '') {
            $sFullSpec = Application::get('base_path', '') . $this->MarketURLBasePath . $this->MarketUrlOldDir . '\\' . $this->MarketUrlFileName;
            if ($this->MarketUrlFso->FileExists(Server::mapPath($sFullSpec))) {
                $MyFile = $this->MarketUrlFso->GetFile(Server::mapPath($sFullSpec));
                $MyFile->delete(true);
                $MyFile = null;
            }
            if ($this->MarketUrlFso->FolderExists(Server::mapPath(Application::get('base_path', '') . $this->MarketURLBasePath . $this->MarketUrlOldDir))) {
                $MyFile = $this->MarketUrlFso->GetFolder(Server::mapPath(Application::get('base_path', '') . $this->MarketURLBasePath . $this->MarketUrlOldDir));
                $MyFile->delete(true);
                $MyFile = null;
            }
        }
    }
    /**
    * Createfile
    *
    */
    public function CreateFile()
    {
        $MyFile = null;
        $sFilename = null;
        $sFilename = Server::mapPath(Application::get('base_path', '') . $this->MarketURLBasePath . $this->MarketUrlDir . '\\' . $this->MarketUrlFileName);
        $MyFile = $this->MarketUrlFso->CreateTextFile($sFilename, true);
        if (!is_object($MyFile)) {
            Error::show("Class_marketUrl: kan '" . $sFilename . ' niet aanmaken !');
            exit();
        } else {
            if ($this->MarketUrlRedirect) {
                $MyFile->WriteLine('<% response.redirect "' . $this->MarketUrlPage . '" %>');
            } else {
                $MyFile->WriteLine($this->MarketUrlPageContent);
            }
            $MyFile = null;
        }
    }
    /**
    *   Construct
    */
    public function __construct()
    {
        $this->MarketUrlFileName = 'default.php';
        $this->MarketUrlFso = new FileSystemObject();
        $this->MarketURLBasePath = '';
        $this->MarketUrlRedirect = true;
        $this->MarketUrlOldDir = '';
    }
    /**
    *   Destruct
    */
    public function __destruct()
    {
        $this->MarketUrlFso = null;
    }
}
?>
