<?php

class OCGoogleSpreadsheetImportHandler extends CSVImportHandler implements ISQLIImportHandler
{
    protected $worksheetFeed;

    protected $worksheet;

    public function initialize()
    {
        $this->worksheetFeed = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetId($this->options['google_spreadsheet_id'])->getWorksheetFeed();
        $this->worksheet = $this->worksheetFeed->getByTitle($this->options['sheet']);

        $this->csvIni = eZINI::instance('csvimport.ini');
        $this->classIdentifier = $this->options->attribute('class_identifier');
        $this->contentClass = eZContentClass::fetchByIdentifier($this->classIdentifier);

        if (!$this->contentClass) {
            $this->cli->error("La class $this->classIdentifier non esiste.");
            die();
        }

        $mapper = $this->options->hasAttribute('fields_map') ? json_decode($this->options->attribute('fields_map'), 1) : array();

        $this->doc = OCGoogleSpreadsheetHandler::getWorksheetAsSQLICSVDoc($this->worksheet, $this->contentClass, $mapper);
        $this->dataSource = $this->doc->rows;
    }

    public function getHandlerName()
    {
        return $this->options->attribute('name');
    }

    public function getHandlerIdentifier()
    {
        return 'csvimportahandler';
    }

    public function getProgressionNotes()
    {
        return 'Current: ' . $this->currentGUID;
    }

    public function cleanup()
    {
        return;
    }

    protected function cleanFileName($fileName)
    {
        return $fileName;
    }

    protected function getImage($rowData)
    {
        if (!empty($rowData)) {
            if ($this->options->hasAttribute('file_dir')){
                return parent::getImage($rowData);
            }else{
                return SQLIContentUtils::getRemoteFile($rowData);
            }            
        }

        return null;
    }

    protected function getFile($rowData)
    {
        if (!empty($rowData)) {
            if ($this->options->hasAttribute('file_dir')){
                return parent::getFile($rowData);
            }else{
                return $this->getNamedRemoteFile($rowData);
            }            
        }

        return null;
    }

    protected function getTimestamp($string)
    {
        if (empty($string)){
            return null;
        }

        if ($this->options->hasAttribute('date_format')){
            $date = DateTime::createFromFormat($this->options->attribute('date_format'), $string);
            if ($date instanceof DateTime){
                return $date->format('U');
            }
        }
        
        return parent::getTimestamp($string);
    }

    protected function getNamedRemoteFile($url)
    {
        $filename = basename($url);
        $suffix = eZFile::suffix($filename);
        if ($suffix == $filename){
            $newFileName = $this->getFileNameFromContentDisposition($url);
            $localPathName = SQLIContentUtils::getRemoteFile($url);
            $localPath = dirname($localPathName);
            $newLocalPathName = $localPath . '/' . $newFileName;
            if ($newLocalPathName != $localPathName) {
                eZFile::rename($localPathName, $newLocalPathName);
            }
            return $newLocalPathName;
        }else{
            return SQLIContentUtils::getRemoteFile($url);
        }
    }

    private function getFileNameFromContentDisposition($url)
    {
        $filename = basename($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)eZINI::instance('sqliimport.ini')->variable('ImportSettings', 'StreamTimeout'));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = curl_exec($ch);
        $parsedHeaders = array_map(function ($x) {
            return array_map("trim", explode(":", $x, 2));
        }, array_filter(array_map("trim", explode("\n", $headers))));
        foreach ($parsedHeaders as $line) {
            if (trim($line[0]) == 'Content-Disposition') {
                $parts = explode('filename=', $line[1]);
                $filename = trim(array_pop($parts), '"');
            }
        }

        return $filename;
    }
}
