<?php

use Google\Spreadsheet\CellEntry;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\SpreadsheetService;
use Google\Spreadsheet\Worksheet;
use Google\Spreadsheet\WorksheetFeed;

class OCGoogleSpreadsheetHandler
{
    /**
     * @var WorksheetFeed
     */
    private $worksheetFeed;

    private $worksheetId;

    private $import_options;

    public static $parseFromCsvUrl = false;

    public static function instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl)
    {
        //https://docs.google.com/spreadsheets/d/14Cwv4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk/edit#gid=0 -> 14Cwv4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk
        $googleSpreadsheetTemp = explode('/',
            str_replace('https://docs.google.com/spreadsheets/d/', '', $googleSpreadsheetUrl));
        $googleSpreadsheetId = array_shift($googleSpreadsheetTemp);

        return self::instanceFromPublicSpreadsheetId($googleSpreadsheetId);
    }

    public static function instanceFromPublicSpreadsheetId($googleSpreadsheetId)
    {
        $serviceRequest = new DefaultServiceRequest("");
        ServiceRequestFactory::setInstance($serviceRequest);
        $spreadsheetService = new SpreadsheetService();

        $handler = new OCGoogleSpreadsheetHandler;
        $handler->worksheetId = $googleSpreadsheetId;
        $handler->worksheetFeed = $spreadsheetService->getPublicSpreadsheet($googleSpreadsheetId);

        return $handler;
    }

    /**
     * @return WorksheetFeed
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
     * @param $worksheet
     * @param eZContentClass|null $contentClass
     * @param array $mapper
     * @return OCGoogleSpreadsheetSQLICSVDoc
     */
    public static function getWorksheetAsSQLICSVDoc($worksheet, eZContentClass $contentClass = null, $mapper = array())
    {
        $serviceRequest = new DefaultServiceRequest("");
        ServiceRequestFactory::setInstance($serviceRequest);
        $dataArray = self::parse($worksheet);
        $headers = array_shift($dataArray);
        $headers = self::mapHeaders($headers, $mapper);
        $cleanHeaders = OCGoogleSpreadsheetSQLICSVRowSet::doCleanHeaders($headers);
        array_walk($dataArray, function (&$a) use ($cleanHeaders) {
            $a = array_combine($cleanHeaders, $a);
        });
        return new OCGoogleSpreadsheetSQLICSVDoc(
            OCGoogleSpreadsheetSQLICSVRowSet::instance($headers, $dataArray)
        );
    }

    private static function parse(Worksheet $worksheet, $skip_empty_lines = true, $trim_fields = false)
    {
        if (self::$parseFromCsvUrl) {
            $csv_string = $worksheet->getCsv();
            $csv = self::parseFromCsvUrl($csv_string, ",", $skip_empty_lines, $trim_fields);
        }else{
            $csv = self::parseFromCellFeed($worksheet,$skip_empty_lines, $trim_fields);
        }

        return $csv;
    }

    private static function parseFromCsvUrl($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true)
    {
        return array_map(
            function ($line) use ($delimiter, $trim_fields) {
                return array_map(
                    function ($field) {
                        return str_replace('!!Q!!', '"', utf8_decode(urldecode($field)));
                    },
                    $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line)
                );
            },
            preg_split(
                $skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s',
                preg_replace_callback(
                    '/"(.*?)"/s',
                    function ($field) {
                        return urlencode(utf8_encode($field[1]));
                    },
                    $enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string)
                )
            )
        );
    }

    private static function parseFromCellFeed(Worksheet $worksheet, $skip_empty_lines, $trim_fields)
    {
        $cellFeed = $worksheet->getCellFeed();
        $rowCount = $worksheet->getRowCount();
        $colCount = $worksheet->getColCount();
        $realColCount = 1;
        for ($col = 1; $col <= $colCount; $col++) {
            $cell = $cellFeed->getCell(1, $col);
            if ($cell instanceof CellEntry && !empty($cell->getContent())) {
                $realColCount = $col;
            }
        }

        $csv = [];
        for ($row = 1; $row <= $rowCount; $row++) {
            $line = [];
            for ($col = 1; $col <= $realColCount; $col++) {
                $cell = $cellFeed->getCell($row, $col);
                if ($cell instanceof CellEntry) {
                    $content = $cell->getContent();
                    if ($trim_fields) {
                        $content = trim($content);
                    }
                    $line[$col] = $content;
                } else {
                    $line[$col] = '';
                }
            }
            if (trim(implode('', $line)) != '' || !$skip_empty_lines) {
                $csv[$row] = $line;
            }
        }

        return $csv;
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
        foreach ($rows as $row) {
            $rowSet->rows[] = new SQLICSVRow($row);
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
