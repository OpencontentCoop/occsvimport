<?php

use Google\Service\Sheets\Sheet;
use Opencontent\Google\GoogleSheet;

class OCGoogleSpreadsheetHandler
{
    /**
     * @var GoogleSheet
     */
    private $worksheetFeed;

    private $worksheetId;

    private $import_options;

    public static function instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl)
    {
        return self::instanceFromPublicSpreadsheetId(self::getSpreadsheetIdFromUri($googleSpreadsheetUrl));
    }

    public static function getSpreadsheetIdFromUri($googleSpreadsheetUrl)
    {
        //https://docs.google.com/spreadsheets/d/14Cwv4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk/edit#gid=0 -> 14Cwv4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk
        $googleSpreadsheetTemp = explode('/',
            str_replace('https://docs.google.com/spreadsheets/d/', '', $googleSpreadsheetUrl));
        return array_shift($googleSpreadsheetTemp);
    }

    public static function instanceFromPublicSpreadsheetId($googleSpreadsheetId)
    {
        $sheet = new GoogleSheet($googleSpreadsheetId);

        $handler = new OCGoogleSpreadsheetHandler;
        $handler->worksheetId = $googleSpreadsheetId;
        $handler->worksheetFeed = $sheet;

        return $handler;
    }

    /**
     * @return GoogleSheet
     */
    public function getWorksheetFeed()
    {
        return $this->worksheetFeed;
    }

    /**
     * @return mixed
     */
    public function getWorksheetId()
    {
        return $this->worksheetId;
    }

    public function setImportOption($key, $value)
    {
        $this->import_options[$key] = $value;

        return $this->import_options;
    }

    public function addImport()
    {
        $this->setImportOption('google_spreadsheet_id', $this->getWorksheetId());

        if (isset($this->import_options['class_identifier'])) {

            $handler = 'googlespreadsheetimporthandler';

            $pendingImport = new SQLIImportItem(array(
                'handler' => $handler,
                'user_id' => eZUser::currentUserID()
            ));
            $pendingImport->setAttribute('options', new SQLIImportHandlerOptions($this->import_options));
            $pendingImport->store();
        }
    }

    /**
     * @param string $spreadSheetId
     * @param string $sheetTitle
     * @param eZContentClass|null $contentClass
     * @param array $mapper
     * @return OCGoogleSpreadsheetSQLICSVDoc
     */
    public static function getWorksheetAsSQLICSVDoc($spreadSheetId, $sheetTitle, eZContentClass $contentClass = null, $mapper = array())
    {
        $sheet = new GoogleSheet($spreadSheetId);
        $dataArray = $sheet->getSheetDataArray($sheetTitle);

        $headers = array_shift($dataArray);
        $headers = self::mapHeaders($headers, $mapper);
        $cleanHeaders = OCGoogleSpreadsheetSQLICSVRowSet::doCleanHeaders($headers);
        array_walk($dataArray, function (&$a) use ($cleanHeaders) {
            $countHeaders = count($cleanHeaders);
            $countA = count($a);
            if ($countHeaders > $countA){
                $a = array_pad($a, $countHeaders, '');
            }
            $a = array_combine($cleanHeaders, $a);
        });

        return new OCGoogleSpreadsheetSQLICSVDoc(
            OCGoogleSpreadsheetSQLICSVRowSet::instance($headers, $dataArray)
        );
    }

    private static function mapHeaders($headers, $mapper)
    {
        $mapper = array_flip($mapper);
        $filteredHeaders = array();
        foreach ($headers as $header) {
            if (isset($mapper[$header]) && !empty($mapper[$header])){
                $filteredHeaders[] = $mapper[$header];
            }else{
                $filteredHeaders[] = $header;
            }
        }
        return $filteredHeaders;
    }
}

class OCGoogleSpreadsheetSQLICSVRowSet extends SQLICSVRowSet
{
    public static function instance($headers, $rows)
    {
        $rowSet = new OCGoogleSpreadsheetSQLICSVRowSet;
        $rowSet->setRowHeaders($headers);
        foreach ($rows as $index => $row) {
            try {
                if (!empty($row)) {
                    $rowSet->rows[] = new SQLICSVRow($row);
                }
            }catch (Exception $e){
            }
        }
        $rowSet->initIterator();

        return $rowSet;
    }

    public static function doCleanHeaders($headers)
    {
        $cleanHeaders = array();
        $rowSet = new OCGoogleSpreadsheetSQLICSVRowSet;

        foreach ($headers as $header) {
            $cleanHeaders[] = $rowSet->cleanHeader($header);
        }

        return $cleanHeaders;
    }
}

class OCGoogleSpreadsheetSQLICSVDoc extends SQLICSVDoc
{
    /**
     * OCGoogleSpreadsheetSQLICSVDoc constructor.
     *
     * @param OCGoogleSpreadsheetSQLICSVRowSet $rows
     */
    public function __construct(OCGoogleSpreadsheetSQLICSVRowSet $rows)
    {
        $this->rows = $rows;
    }

    public function parse()
    {

    }
}
