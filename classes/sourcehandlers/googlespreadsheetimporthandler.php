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
                return SQLIContentUtils::getRemoteFile($rowData);
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
}
