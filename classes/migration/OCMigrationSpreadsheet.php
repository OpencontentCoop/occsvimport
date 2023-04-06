<?php

use Opencontent\Google\GoogleSheet;
use Opencontent\Google\GoogleSheetClient;

class OCMigrationSpreadsheet
{
    /**
     * @var OCMigrationSpreadsheet
     */
    private static $instance;

    /**
     * @var Google_Service_Sheets
     */
    private $googleSheetService;

    /**
     * @var string|null
     */
    private $spreadsheetId;

    /**
     * @var GoogleSheet
     */
    private $spreadsheet;

    /**
     * @var GoogleSheet
     */
    private static $masterSpreadsheet;

    /**
     * @var string
     */
    private static $masterSpreadsheetId;

    private static $masterSpreadsheetUrl;

    private $dataHash = [];

    private $headers = [];

    private static $dataStartAtRow = 4;

    private static $nullAction = [
        'timestamp' => false,
        'action' => 'unknown',
        'status' => 'unknown',
        'options' => [],
    ];

    private static $googleSheetClient;

    public static function getConnectedSpreadSheet()
    {
        $siteData = eZSiteData::fetchByName('migration_spreadsheet');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('migration_spreadsheet', false);
        }

        return $siteData->attribute('value');
    }

    public static function getConnectedSpreadSheetTitle()
    {
        $id = self::getConnectedSpreadSheet();
        $title = false;
        if ($id) {
            $spreadsheet = self::instanceGoogleSheet($id);
            $title = $spreadsheet->getTitle();
        }

        return $title;
    }

    public static function setConnectedSpreadSheet($spreadsheet)
    {
        $checkAccessSpreadsheet = self::instanceGoogleSheet($spreadsheet);
        $siteData = eZSiteData::fetchByName('migration_spreadsheet');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('migration_spreadsheet', false);
        }
        $siteData->setAttribute('value', $spreadsheet);
        $siteData->store();

        self::resetCurrentStatus();
    }

    public static function removeConnectedSpreadSheet()
    {
        $siteData = eZSiteData::fetchByName('migration_spreadsheet');
        if ($siteData instanceof eZSiteData) {
            $siteData->remove();
        }
        OCMigration::createPayloadTableIfNeeded(null, true);
        OCMigration::createTableIfNeeded(null, true);
        self::resetCurrentStatus();
    }

    public static function resetCurrentStatus(): void
    {
        $siteData = eZSiteData::fetchByName('migration_status');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('migration_status', json_encode(self::$nullAction));
        }
        $siteData->setAttribute('value', json_encode(self::$nullAction));
        $siteData->store();
    }

    public static function getCurrentStatus(string $action = null): array
    {
        $siteData = eZSiteData::fetchByName('migration_status');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('migration_status', json_encode(self::$nullAction));
        }

        $status = json_decode($siteData->attribute('value'), true);
        if ($action) {
            return $status['action'] == $action ? $status : self::$nullAction;
        }
        return $status;
    }

    public static function setCurrentStatus(string $action, string $status, array $options, $message = null): void
    {
        $siteData = eZSiteData::fetchByName('migration_status');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('migration_status', false);
        } elseif ($message === null) {
            $current = json_decode($siteData->attribute('value'), true);
            $message = $current['message'] ?? '';
        }
        $siteData->setAttribute(
            'value',
            json_encode([
                'timestamp' => date('c'),
                'action' => $action,
                'status' => $status,
                'options' => $options,
                'message' => $message,
                'hostname' => gethostname(),
                'pid' => getmypid(),
            ])
        );
//        eZDebug::writeError($siteData->attribute('value'), __METHOD__);
        $siteData->store();
    }

    public static function appendMessageToCurrentStatus($message = null): void
    {
        if ($message) {
            $siteData = eZSiteData::fetchByName('migration_status');
            if ($siteData instanceof eZSiteData) {
                $status = json_decode($siteData->attribute('value'), true);
                $status['message'] = array_merge((array)$status['message'], (array)$message);
                unset($status['message'][0]); //@todo
                //eZCLI::instance()->output(var_export($status['message'], 1));
                $status['timestamp'] = date('c');
                $siteData->setAttribute(
                    'value',
                    json_encode($status)
                );
                $siteData->store();
//                eZDebug::writeError($siteData->attribute('value'), __METHOD__);
            }
        }
    }

    public static function runAction(string $action, array $options): array
    {
        if (!in_array($action, [
            'export',
            'push',
            'pull',
            'import',
            'reset',
        ])) {
            throw new Exception("Action $action not found or not yet available");
        }

        $only = '';
        if (isset($options['class_filter'])) {
            $only = '--only=' . implode(',', $options['class_filter']);
        }

        $update = '';
        if (isset($options['update'])) {
            $update = $options['update'] == 'update' ? '--update' : '';
        }

        $validate = '';
        if (isset($options['validate'])) {
            $update = $options['validate'] !== '' ? '--validate' : '';
        }

        if ($action === 'reset') {
            self::setCurrentStatus('', '', [], []);
        } else {

            $useSqlImport = true;

            if ($useSqlImport) {
                $importOptions = [
                    'action' => $action,
                    'only' => isset($options['class_filter']) ? implode(',', $options['class_filter']) : '',
                    'update' => $options['update'],
                    'validate' => $options['validate'],
                ];
                $pendingImport = new SQLIImportItem([
                    'handler' => 'ocmimporthandler',
                    'user_id' => eZUser::currentUserID(),
                ]);
                $pendingImport->setAttribute('options', new SQLIImportHandlerOptions($importOptions));
                $pendingImport->store();

                self::setCurrentStatus($action, 'pending', $options, []);
                $message = "Schedulata azione: $action...";

                $command = 'php runcronjobs.php -q -s' . eZSiteAccess::current()['name'] . ' sqliimport_run > /dev/null &';
                eZDebug::writeError($command);
                exec($command);
            }else {
                $command = 'bash extension/occsvimport/bin/bash/migration/run.sh '
                    . eZSiteAccess::current()['name'] . ' '
                    . $action . ' '
                    . $only . ' '
                    . $update . ' '
                    . $validate;

                eZDebug::writeError($command);
                exec($command);
                sleep(2);
                $message = "In attesa di eseguire l'azione: $action...";
            }

            $executionInfo = [];
            foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
                $canMethod = 'can' . ucfirst($action);
                if (method_exists($className, $canMethod) && $className::$canMethod()) {
                    $executionInfo[$className] = [
                        'status' => 'pending',
                        'action' => $action,
                        'update' => $message,
                        'sheet' => '',
                        'range' => '',
                    ];
                } else {
                    $executionInfo[$className] = [
                        'status' => 'success',
                        'action' => $action,
                        'update' => "Azione non eseguita per configurazione del sistema",
                        'sheet' => '',
                        'range' => '',
                    ];
                }
            }
            self::appendMessageToCurrentStatus($executionInfo);
        }

        return self::getCurrentStatus($action);
    }

    public function __construct()
    {
        $client = self::instanceGoogleSheetClient();
        $this->googleSheetService = $client->getGoogleSheetService();
        $this->spreadsheetId = self::getConnectedSpreadSheet();
        $this->spreadsheet = self::instanceGoogleSheet($this->spreadsheetId);
        //$sheets = $spreadsheet->getSheetTitleList();
    }

    private static function instanceGoogleSheet($id): GoogleSheet
    {
        return new GoogleSheet($id, self::instanceGoogleSheetClient());
    }

    public static function instanceGoogleSheetClient(): GoogleSheetClient
    {
        if (self::$googleSheetClient === null){
            self::$googleSheetClient = new OCMGoogleSheetClient();
        }

        return self::$googleSheetClient;
    }

    public static function getMasterSpreadsheet($spreadsheetUrl = null): ?GoogleSheet
    {
        if (self::$masterSpreadsheet === null) {
            $context = OCMigration::discoverContext();
            if ($context) {
                if (!$spreadsheetUrl) {
                    $shortUrl = 'https://link.opencontent.it/new-kit-' . $context;
                    $ch = curl_init();
                    $timeout = 0;
                    curl_setopt($ch, CURLOPT_URL, $shortUrl);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                    curl_setopt($ch, CURLOPT_HEADER, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    // Getting binary data
                    curl_exec($ch);
                    $info = curl_getinfo($ch);
                    $spreadsheetUrl = $info['redirect_url'];
                }
                self::$masterSpreadsheetId = OCGoogleSpreadsheetHandler::getSpreadsheetIdFromUri($spreadsheetUrl);
                self::$masterSpreadsheet = self::instanceGoogleSheet(self::$masterSpreadsheetId);
                self::$masterSpreadsheetUrl = $spreadsheetUrl;
            }
        }

        return self::$masterSpreadsheet;
    }

    public static function getMasterSpreadsheetHelpTexts($sheetTitle, $master = null): array
    {
        $sheet = self::getMasterSpreadsheet($master)->getByTitle($sheetTitle);

        return self::getHelpTexts($sheetTitle, $sheet, self::$masterSpreadsheetId);
    }

    private static function getHelpTexts($sheetTitle, $sheet, $id): array
    {
        $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
        $range = "{$sheetTitle}!R1C1:R2C{$colCount}";
        $client = self::instanceGoogleSheetClient();
        $firstRows = $client->getGoogleSheetService()->spreadsheets_values->get(
            $id,
            $range
        )->getValues();

        $helper = [];
        foreach ($firstRows[0] as $index => $header) {
            $helper[$header] = $firstRows[1][$index] ?? '';
        }

        return $helper;
    }

    public function updateGuide(): ?int
    {
        self::getMasterSpreadsheet();
        $url = str_replace('copy', 'edit', self::$masterSpreadsheetUrl);
        $data = '=IMPORTRANGE("' . $url . '";"Istruzioni!A1:E50")';
        $sheetTitle = 'Istruzioni';
        $sheet = $this->spreadsheet->getByTitle($sheetTitle);
        $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();
        $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
        $range = "$sheetTitle!R1C1:R{$rowCount}C{$colCount}";
        $clear = new Google_Service_Sheets_ClearValuesRequest();
        $this->googleSheetService->spreadsheets_values->clear($this->spreadsheetId, $range, $clear);
        $body = new Google_Service_Sheets_ValueRange([
            'values' => [[$data]],
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];
        $range = "$sheetTitle!R1C1:R1C2";
        $updateRows = (int)$this->googleSheetService->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            $params
        )->getUpdatedRows();
        return $updateRows;
    }

    public function updateVocabolaries(): ?int
    {
        $sheetTitle = 'Vocabolari controllati';

        $masterSheet = self::getMasterSpreadsheet()->getByTitle($sheetTitle);
        $masterRowCount = $masterSheet->getProperties()->getGridProperties()->getRowCount();
        $masterColCount = $masterSheet->getProperties()->getGridProperties()->getColumnCount();
        $masterRange = "$sheetTitle!R1C1:R{$masterRowCount}C{$masterColCount}";
        $client = self::instanceGoogleSheetClient();
        $data = $client->getGoogleSheetService()->spreadsheets_values->get(
            self::$masterSpreadsheetId,
            $masterRange
        )->getValues();

        $sheet = $this->spreadsheet->getByTitle($sheetTitle);
        $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();
        $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
        $range = "$sheetTitle!R1C1:R{$rowCount}C{$colCount}";
        $clear = new Google_Service_Sheets_ClearValuesRequest();
        $this->googleSheetService->spreadsheets_values->clear($this->spreadsheetId, $range, $clear);

        $rowCount = count($data);
        $colCount = count($data[0]);
        $range = "$sheetTitle!R1C1:R{$rowCount}C{$colCount}";
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $data,
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];
        $updateRows = (int)$this->googleSheetService->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            $params
        )->getUpdatedRows();
        return $updateRows;
    }

    public function updateHelper($className, $master = null, $dryRun = false, $verbose = false): ?int
    {
        $sheetTitle = $className::getSpreadsheetTitle();
        $helper = self::getMasterSpreadsheetHelpTexts($sheetTitle, $master);

        $currentHelper = self::getHelpTexts($sheetTitle,
            self::instanceGoogleSheet(self::getConnectedSpreadSheet())->getByTitle($sheetTitle),
            self::getConnectedSpreadSheet()
        );

        $value = [];
        $cli = eZCLI::instance();
        if ($verbose) $cli->output();
        foreach($currentHelper as $header => $text){
            $newText = $helper[$header] ?? '';
            $value[$header] = trim($newText);
            if (!empty($text) && !isset($helper[$header])){
                if ($verbose) $cli->error('missing ' . $header);
                $value[$header] = $text;
            }
            if (trim($newText) !== trim($text)){
                if ($verbose) $cli->output($header);
                if ($verbose) $cli->output(' < ' . $text);
                if ($verbose) $cli->warning(' > ' . $value[$header]);
            }
        }

        if ($dryRun){
            return 0;
        }

        $values = [array_values($value)];
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values,
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        $colCount = count($currentHelper);
        $range = "$sheetTitle!R2C1:R2C$colCount";
        $updateRows = (int)$this->googleSheetService->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            $params
        )->getUpdatedRows();

//        print_r([$values, $headers, $updateRows, $range]);die();
        return $updateRows;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function configureSheet(
        $className,
        $addConditionalFormatRules = true,
        $addDateValidations = true,
        $addRangeValidations = true
    ) {
        if (strpos($className, 'ocm_') === false) {
            return false;
        }
        $sheetTitle = $className::getSpreadsheetTitle();
        $sheet = $this->spreadsheet->getByTitle($sheetTitle);
        $headers = $this->getHeaders($sheetTitle);
        $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();

        $currentRules = [];
        $response = $this->googleSheetService->spreadsheets->get(
            $this->spreadsheetId,
            ['fields' => 'sheets(properties(title,sheetId),conditionalFormats)']
        )->toSimpleObject();
        foreach ($response->sheets as $sheetData) {
            if ($sheetData->properties->sheetId === $sheet->getProperties()->getSheetId()) {
                $currentRules = $sheetData;
                break;
            }
        }

        $responses = [];
        $errors = [];

        $addConditionalFormatRulesRequests = [];
        if ($addConditionalFormatRules) {
            $sheetId = $currentRules->properties->sheetId;
            if (isset($currentRules->conditionalFormats)) {
                foreach (array_reverse(array_keys($currentRules->conditionalFormats)) as $index) {
                    $addConditionalFormatRulesRequests[] = new Google_Service_Sheets_Request([
                        'deleteConditionalFormatRule' => [
                            'sheetId' => $sheetId,
                            'index' => $index,
                        ],
                    ]);
                }
            }

            $requiredRanges = [];
            foreach ($headers as $index => $header) {
                if (strpos($header, '*') !== false) {
                    $requiredRanges[] = [
                        'sheetId' => $sheet->getProperties()->getSheetId(),
                        'startColumnIndex' => $index,
                        'endColumnIndex' => $index + 1,
                        'startRowIndex' => (self::$dataStartAtRow - 1),
                        'endRowIndex' => $rowCount,
                    ];
                }
            }
            if (!empty($requiredRanges)) {
                $addConditionalFormatRulesRequests[] = new Google_Service_Sheets_Request([
                    'addConditionalFormatRule' => [
                        'rule' => [
                            'ranges' => $requiredRanges,
                            'booleanRule' => [
                                'condition' => [
                                    'type' => 'BLANK',
                                ],
                                'format' => [
                                    'backgroundColorStyle' => ['rgbColor' => ['red' => 1]],
                                ],
                            ],
                        ],
                        'index' => 0,
                    ],
                ]);
            }

            $internalLinkConditionalFormatHeaders = $className::getInternalLinkConditionalFormatHeaders();
            if (!empty($internalLinkConditionalFormatHeaders)) {
                $internalLinkRanges = [];
                foreach ($headers as $index => $header) {
                    if (in_array($header, $internalLinkConditionalFormatHeaders)) {
                        $internalLinkRanges[] = [
                            'sheetId' => $sheet->getProperties()->getSheetId(),
                            'startColumnIndex' => $index,
                            'endColumnIndex' => $index + 1,
                            'startRowIndex' => (self::$dataStartAtRow - 1),
                            'endRowIndex' => $rowCount,
                        ];
                    }
                }
                if (!empty($internalLinkRanges)) {
                    $addConditionalFormatRulesRequests[] = new Google_Service_Sheets_Request([
                        'addConditionalFormatRule' => [
                            'rule' => [
                                'ranges' => $internalLinkRanges,
                                'booleanRule' => [
                                    'condition' => [
                                        'type' => 'TEXT_CONTAINS',
                                        'values' => [
                                            ['userEnteredValue' => '<a href="/'],
                                        ],
                                    ],
                                    'format' => [
                                        'backgroundColorStyle' => [
                                            'rgbColor' => [
                                                'red' => 1,
                                                'green' => 0.549,
                                                'blue' => 0,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'index' => 0,
                        ],
                    ]);
                }
            }

            $max160CharConditionalFormatHeaders = $className::getMax160CharConditionalFormatHeaders();
            if (!empty($max160CharConditionalFormatHeaders)) {
                foreach ($headers as $index => $header) {
                    if (in_array($header, $max160CharConditionalFormatHeaders)) {
                        $addConditionalFormatRulesRequests[] = new Google_Service_Sheets_Request([
                            'addConditionalFormatRule' => [
                                'rule' => [
                                    'ranges' => [
                                        [
                                            'sheetId' => $sheet->getProperties()->getSheetId(),
                                            'startColumnIndex' => $index,
                                            'endColumnIndex' => $index + 1,
                                            'startRowIndex' => (self::$dataStartAtRow - 1),
                                            'endRowIndex' => $rowCount,
                                        ],
                                    ],
                                    'booleanRule' => [
                                        'condition' => [
                                            'type' => 'CUSTOM_FORMULA',
                                            'values' => [
                                                [
                                                    'userEnteredValue' => '=LEN(REGEXREPLACE(' . $this->getColumnLetter(
                                                            $sheetTitle,
                                                            $header
                                                        ) . '4;"</?\S+[^<>]*>";""))>255',
                                                ],
                                            ],
                                        ],
                                        'format' => [
                                            'backgroundColorStyle' => [
                                                'rgbColor' => [
                                                    'red' => 1,
                                                    'green' => 0.549,
                                                    'blue' => 0,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'index' => 0,
                            ],
                        ]);
                    }
                }
            }
        }

        $setDataValidationRequests = [];
        if ($addDateValidations) {
            $dateValidationHeaders = $className::getDateValidationHeaders();
            if (!empty($dateValidationHeaders)) {
                foreach ($headers as $index => $header) {
                    if (in_array($header, $dateValidationHeaders)) {
                        $setDataValidationRequests[] = new Google_Service_Sheets_Request([
                            'setDataValidation' => [
                                'range' => [
                                    'sheetId' => $sheet->getProperties()->getSheetId(),
                                    'startColumnIndex' => $index,
                                    'endColumnIndex' => $index + 1,
                                    'startRowIndex' => (self::$dataStartAtRow - 1),
                                    'endRowIndex' => $rowCount,
                                ],
                                'rule' => [
                                    'strict' => true,
                                    'condition' => [
                                        'type' => 'DATE_IS_VALID',
                                        'values' => [],
                                    ],
                                ],
                            ],
                        ]);
                    }
                }
            }
        }
        if ($addRangeValidations) {
            $rangeValidationHash = $className::getRangeValidationHash();
            if (!empty($rangeValidationHash)) {
                foreach ($headers as $index => $header) {
                    if (isset($rangeValidationHash[$header])) {
                        $userEnteredValue = $this->getColumnRange($rangeValidationHash[$header]['ref']);
                        if ($userEnteredValue) {
                            $setDataValidationRequests[] = new Google_Service_Sheets_Request([
                                'setDataValidation' => [
                                    'range' => [
                                        'sheetId' => $sheet->getProperties()->getSheetId(),
                                        'startColumnIndex' => $index,
                                        'endColumnIndex' => $index + 1,
                                        'startRowIndex' => (self::$dataStartAtRow - 1),
                                        'endRowIndex' => $rowCount,
                                    ],
                                    'rule' => [
                                        'strict' => $rangeValidationHash[$header]['strict'],
                                        'showCustomUi' => true,
                                        'condition' => [
                                            'type' => 'ONE_OF_RANGE',
                                            'values' => [
                                                ['userEnteredValue' => $userEnteredValue],
                                            ],
                                        ],
                                    ],
                                ],
                            ]);
                        }
                    }
                }
            }

            $urlValidationHeaders = $className::getUrlValidationHeaders();
            if (!empty($urlValidationHeaders)) {
                foreach ($headers as $index => $header) {
                    if (in_array($header, $urlValidationHeaders)) {
                        $setDataValidationRequests[] = new Google_Service_Sheets_Request([
                            'setDataValidation' => [
                                'range' => [
                                    'sheetId' => $sheet->getProperties()->getSheetId(),
                                    'startColumnIndex' => $index,
                                    'endColumnIndex' => $index + 1,
                                    'startRowIndex' => (self::$dataStartAtRow - 1),
                                    'endRowIndex' => $rowCount,
                                ],
                                'rule' => [
                                    'strict' => true,
                                    'condition' => [
                                        'type' => 'TEXT_IS_URL',
                                        'values' => [],
                                    ],
                                ],
                            ],
                        ]);
                    }
                }
            }
        }

        $requests = [];
        if (!empty($addConditionalFormatRulesRequests)) {
            $requests = array_merge($requests, $addConditionalFormatRulesRequests);
        }
        if (!empty($setDataValidationRequests)) {
            $requests = array_merge($requests, $setDataValidationRequests);
        }

        if (!empty($requests)) {
            try {
                $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                    'requests' => $requests,
                ]);
                $responses[] = $this->googleSheetService->spreadsheets->batchUpdate(
                    $this->spreadsheetId,
                    $batchUpdateRequest
                );
            } catch (Exception $e) {
                $responses[] = json_decode($e->getMessage());
                $errors[] = $e->getMessage();
            }
        }

        return [
            'errors' => count($errors),
            'args' => func_get_args(),
            'responses' => $responses,
            'conditional_requests' => $addConditionalFormatRulesRequests,
            'validation_requests' => $setDataValidationRequests,
        ];
    }

    private function getHeaders($sheetTitle): array
    {
        if (!isset($this->headers[$sheetTitle])) {
            $sheet = $this->spreadsheet->getByTitle($sheetTitle);
            $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
            $range = "{$sheetTitle}!R1C1:R1C{$colCount}";
            $firstRow = $this->googleSheetService->spreadsheets_values->get($this->spreadsheetId, $range)->getValues();
            $this->headers[$sheetTitle] = $firstRow[0];
        }

        return $this->headers[$sheetTitle];
    }

    private function getColumnRange(array $ref): ?string
    {
        $sheet = $ref['sheet'];
        $column = $ref['column'];
        $startAt = $ref['start'];

        $rowCount = $this->spreadsheet->getByTitle($sheet)->getProperties()->getGridProperties()->getRowCount();
        $letter = $this->getColumnLetter($sheet, $column);
        if ($letter) {
            return '=\'' . $sheet . '\'!$' . $letter . '$' . $startAt . ':$' . $letter . '$' . $rowCount;
        }

        return false;
    }

    private function getColumnLetter($sheetTitle, $column): string
    {
        $headers = $this->getHeaders($sheetTitle);
        $alphabeth = range('A', 'Z');
        $letter = false;
        foreach ($headers as $index => $header) {
            $prefix = '';
            if ($index > 25) {
                $index = $index - 25;
                $prefix = 'A';
            }
            if ($column == $header) {
                $letter = $prefix . $alphabeth[$index];
            }
        }

        return $letter;
    }

    private static function setAlreadyRunningStatus($action, $options)
    {
        $executionInfo = [];
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $executionInfo[$className] = [
                'status' => 'error',
                'action' => $action,
                'update' => "L'azione è già in corso",
                'sheet' => '',
                'range' => '',
            ];
        }
        self::appendMessageToCurrentStatus($executionInfo);
    }

    /**
     * @throws Exception
     */
    public function push(eZCLI $cli = null, array $options = []): array
    {
        if (self::getCurrentStatus('push')['status'] == 'running') {
            if ($cli) {
                $cli->output('Already running');
            }
            self::setAlreadyRunningStatus('push', $options);
            return [];
        }

        if (OCMigration::discoverContext() === false) {
            throw new Exception('Wrong context');
        }

        OCMigration::createTableIfNeeded($cli);

        self::setCurrentStatus('push', 'running', $options);

        $executionInfo = [];
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $executionInfo = array_merge($executionInfo, $this->pushByType($className, $cli, $options));
            self::appendMessageToCurrentStatus($executionInfo);
        }
        self::setCurrentStatus('push', 'done', $options);

        return $executionInfo;
    }

    /**
     * @param $className
     * @param eZCLI|null $cli
     * @return array
     * @throws Exception
     */
    private function pushByType($className, eZCLI $cli = null, array $options = []): array
    {
        $executionInfo = [];

        $override = !($options['update'] === true);
        if ($override && $cli) {
            $cli->output("Run in override mode");
        }

        if (!$className::canPush()) {
            $executionInfo[$className] = [
                'status' => 'skipped',
                'action' => 'push',
                'message' => '',
                'class' => $className,
            ];
            OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
            return $executionInfo;
        }

        $sheetTitle = $className::getSpreadsheetTitle();
        try {
            $sheet = $this->spreadsheet->getByTitle($sheetTitle);
            $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();
            $allColCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
            $range = "$sheetTitle!R1C1:R1C$allColCount";
            $firstRow = $this->googleSheetService->spreadsheets_values->get($this->spreadsheetId, $range)->getValues();
            $headers = $firstRow[0];
            $colCount = count($headers);
            $startCleanAtRow = self::$dataStartAtRow;
            $range = "$sheetTitle!R{$startCleanAtRow}C1:R{$rowCount}C$colCount";

            $ignoreRows = [];
            $customCond = null;
            if (!$override) {
                $ignoreRows = array_column($this->getDataHash($sheetTitle), $className::getIdColumnLabel());
                if (!empty($ignoreRows)) {
                    $customCond = ' WHERE _id NOT IN (\'' . implode('\',\'', $ignoreRows) . '\')';
                }
            }

            OCMigrationSpreadsheet::appendMessageToCurrentStatus([
                $className => [
                    'status' => 'running',
                    'update' => 'Preparazione dei contenuti...',
                ],
            ]);

            $items = $className::fetchObjectList(
                $className::definition(),
                null,
                null,
                [$className::getSortField() => 'asc'],
                null,
                true,
                false,
                null,
                null,
                $customCond
            );
            $itemCount = count($items);

            if ($cli) {
                $cli->output("Push $itemCount $className items in sheet $sheetTitle");
            }

            $values = [];
            foreach ($items as $item) {
                $data = $this->getItemToSpreadsheet($item);
                $value = [];
                foreach ($headers as $header) {
                    $value[$header] = $data[$header] ?? '';
                }
                $values[] = array_values($value);
            }

            $updateRows = 0;
            if (!empty($values)) {
                $body = new Google_Service_Sheets_ValueRange([
                    'values' => $values,
                ]);
                $params = [
                    'valueInputOption' => 'USER_ENTERED',
//                'valueInputOption' => 'RAW',
                ];

                if ($override) {
                    // cancella tutti i valori non formule di tutto il folgio (escluso header)
                    $range = "$sheetTitle!R{$startCleanAtRow}C1:R{$rowCount}C{$allColCount}";
                    $clear = new Google_Service_Sheets_ClearValuesRequest();
                    $this->googleSheetService->spreadsheets_values->clear($this->spreadsheetId, $range, $clear);

                    $updateRows = $this->googleSheetService->spreadsheets_values->update(
                        $this->spreadsheetId,
                        $range,
                        $body,
                        $params
                    )->getUpdatedRows();
                } else {
                    $startAtRow = count($ignoreRows) + self::$dataStartAtRow;
                    $endAtRow = $startAtRow + $itemCount;
                    $range = "$sheetTitle!R{$startAtRow}C1:R{$endAtRow}C$colCount";
                    $updateRows = (int)$this->googleSheetService->spreadsheets_values->append(
                        $this->spreadsheetId,
                        $range,
                        $body,
                        $params
                    )->getUpdates()->getUpdatedRows();
                }
            }

            if ($cli) {
                $cli->output('Written ' . $updateRows . ' rows in sheet ' . $range);
            }

//            self::configureSheet($className);

            $executionInfo[$className] = [
                'status' => 'success',
                'action' => 'push',
                'update' => 'Scritte ' . $updateRows . ' righe nel range ' . $range,
                'sheet' => $sheetTitle,
                'range' => $range,
            ];
        } catch (Throwable $e) {
            $message = ($e instanceof \Google\Service\Exception) ? json_decode(
                $e->getMessage()
            )->error->message : $e->getMessage();
            $executionInfo[$className] = [
                'status' => 'error',
                'action' => 'push',
                'message' => $message,
                'sheet' => $sheetTitle,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
        return $executionInfo;
    }

    private function getItemToSpreadsheet(ocm_interface $item): array
    {
        $data = $item->toSpreadsheet();
        foreach ($data as $key => $value) {
            if (mb_strlen($value) > 49999) {
                $data[$key] = "[Il valore di questo campo supera il limite di caratteri ammessi per una cella.\nSe vuoi che venga importato direttamente dal sito originale non rimuovere né modificare questa cella]\n#" . $item->attribute(
                        '_id'
                    );
            }
        }

        return $data;
    }

    public function pull(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('pull')['status'] == 'running') {
            if ($cli) {
                $cli->output('Already running');
            }
            self::setAlreadyRunningStatus('pull', $options);
            return [];
        }

        if (OCMigration::discoverContext() !== false) {
            throw new Exception('Wrong context');
        }

        OCMigration::createTableIfNeeded($cli);

        self::setCurrentStatus('pull', 'running', $options);

        $validate = isset($options['validate']) && $options['validate'];
        if ($validate && $cli){
            $cli->output('Run with validation');
        }

        $executionInfo = [];
        $remoteIdCollection = new ArrayObject();
        $skipPayloadGeneration = [];
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $executionInfo = array_merge($executionInfo, $this->pullByType($className, $cli, $options, $remoteIdCollection));
            if ($executionInfo[$className]['status'] === 'error'){
                $skipPayloadGeneration[] = $className;
            }
            self::appendMessageToCurrentStatus($executionInfo);
        }

        OCMigration::createPayloadTableIfNeeded($cli);
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            if (!in_array($className, $skipPayloadGeneration)) {
                $executionInfo = array_merge($executionInfo, $this->createPayloadByType($className, $cli, $validate));
                self::appendMessageToCurrentStatus($executionInfo);
            }
        }

        self::setCurrentStatus('pull', 'done', $options);

        return $executionInfo;
    }

    private function pullByType($className, eZCLI $cli = null, array $options, ArrayObject $remoteIdCollection): array
    {
        $executionInfo = [];

        if (!$className::canPull()) {
            $executionInfo[$className] = [
                'status' => 'skipped',
                'action' => 'pull',
                'message' => '',
                'class' => $className,
            ];

            return $executionInfo;
        }

        $sheetTitle = $className::getSpreadsheetTitle();
        try {
            if ($cli) {
                $cli->output("Pull $className items from sheet $sheetTitle");
            }

            $values = $this->getDataHash($sheetTitle);
            $count = 0;
            $nameCollection = [];
            $duplicate = [];
            foreach ($values as $value) {
                /** @var OCMPersistentObject $item */
                $item = $className::fromSpreadsheet($value);
                $ignora = isset($value['IGNORA']) && !empty($value['IGNORA']);

                if ($item instanceof eZPersistentObject && $ignora){
                    if ($cli) $cli->warning(' - Skip row by source data' . $item->id() . ' ' . $item->name());
                }

                if ($item instanceof eZPersistentObject && !$ignora) {
                    if (!$item->id()){
                        if ($cli) {
                            $cli->warning(' - Skip row ' . $item->id() . ' ' . $item->name());
                        }
                        $executionInfo['errors'][$className][$item->attribute('_id')] = [
                            'message' => 'Empty value in columns ' . $className::getIdColumnLabel(),
                            'action' => 'pull',
                        ];
                        continue;
                    }
                    try {
                        if (isset($remoteIdCollection[$item->id()])){
                            $errorMessage = "Identificativo duplicato: " . $item->id();
                            $duplicate[$item->id()] = $errorMessage;
                            throw new InvalidArgumentException($errorMessage);
                        }
                        $remoteIdCollection[$item->id()] = $item->name();

                        $item->checkRequiredColumns();

                        if ($item->avoidNameDuplication()) {
                            if (in_array($item->name(), $nameCollection)) {
                                $errorMessage = "Titolo duplicato: " . $item->name() . ' (identificativo: ' . $item->id() . ')';
                                $duplicate[trim($item->name())] = $errorMessage;
                                throw new InvalidArgumentException($errorMessage);
                            }
                            $nameCollection[] = $item->name();
                        }

                        $item->storeThis(false);
                        if ($cli) {
                            $cli->output( ' - ' . $item->id() . ' ' . $item->name());
                        }
                        $count++;
                    } catch (Throwable $e) {
                        $executionInfo['errors'][$className][$item->attribute('_id')] = [
                            'message' => $e->getMessage(),
                            'action' => 'pull',
                        ];
                    }
                }
            }

            if (count($duplicate) > 0){
                $errorMessage = implode('<br />', $duplicate);
                throw new InvalidArgumentException($errorMessage);
            }

            $executionInfo[$className] = [
                'status' => 'running',
                'action' => 'pull',
                'update' => 'Lette ' . $count . ' righe',
                'sheet' => $sheetTitle,
            ];
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'action' => 'pull',
                'message' => $e->getMessage(),
                'sheet' => $sheetTitle,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
        return $executionInfo;
    }

    /**
     * @param ocm_interface
     * @param eZCLI|null $cli
     * @param bool $validate
     * @return array
     */
    private function createPayloadByType($className, eZCLI $cli = null, bool $validate = null): array
    {
        $executionInfo = [];

        if (!$className::canPull()) {
            $executionInfo[$className] = [
                'status' => 'skipped',
                'action' => 'pull',
                'message' => '',
                'class' => $className,
            ];

            return $executionInfo;
        }

        /** @var ocm_interface[] $items */
        $items = $className::fetchObjectList(
            $className::definition(),
            null,
            null,
            [$className::getSortField() => 'asc'],
            null,
            true
        );
        $itemCount = count($items);

        try {
            if ($cli) {
                $cli->output("Create payload for $itemCount $className items");
            }
            $count = 0;

            foreach ($items as $index => $item) {
                $countIndex = $index + 1;
                if ($cli) {
                    $cli->output(" - $countIndex/$itemCount " . get_class($item) . ' ' . $item->attribute('_id'), false);
                }
                try {
                    $generatedPayloadCount = $item->storePayload();
                    if ($validate && $generatedPayloadCount > 0) {
                        $p = false;
                        try {
                            $p = OCMPayload::fetch($item->id());
                        } catch (Exception $e) {
                            $cli->output(' fail validation', false);
                        }
                        if ($p instanceof OCMPayload) {
                            $cli->output(' validation', false);
                            $p->validate();
                        }
                    }
                    $count = $count + $generatedPayloadCount;
                    if ($cli) {
                        $cli->output(' ' . $generatedPayloadCount);
                    }
                } catch (Throwable $e) {
                    if ($cli) {
                        $cli->output(' ' . $e->getMessage());
                    }
                    $executionInfo['errors'][$className][$item->attribute('_id')] = [
                        'message' => $e->getMessage(),
                        'action' => 'pull',
                    ];
                }

                if ($count > $itemCount){
                    $count = $itemCount;
                }
                $message = 'Letti ' . $itemCount . ' contenuti e preparati ' . $count . ' contenuti per l\'importazione';

                if (!$className::checkPayloadGeneration()){
                    $message = 'Letti ' . $itemCount . ' contenuti';
                }
                if ($validate){
                    $message = 'Letti ' . $itemCount . ' contenuti e validati ' . $count . ' contenuti';
                }

                $status = $countIndex >= $itemCount ? 'success' : 'running';
                if ($status == 'success' && $className::checkPayloadGeneration()){
                    $status = $count >= $itemCount ? 'success' : 'warning';
                }

                $avoidDuplication = '';
                if (!$item->avoidNameDuplication()){
                    $avoidDuplication = '<br>(controllo duplicati non eseguito per configurazione)';
                }
                $executionInfo[$className] = [
                    'status' => $status,
                    'action' => 'pull',
                    'process' => $countIndex . '/' . $itemCount,
                    'update' => $message . $avoidDuplication,
                    'class' => $className,
                ];

                if ($index % 5 === 0 || $countIndex >= $itemCount) {
                    OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
                }
            }
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'action' => 'pull',
                'message' => $e->getMessage(),
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
        return $executionInfo;
    }

    public function import(eZCLI $cli = null, array $options = []): array
    {
        if (self::getCurrentStatus('import')['status'] == 'running') {
            if ($cli) {
                $cli->output('Already running');
            }
            self::setAlreadyRunningStatus('import', $options);
            return [];
        }

        if (OCMigration::discoverContext() !== false) {
            throw new Exception('Wrong context');
        }

        self::setCurrentStatus('import', 'running', $options, []);

        $onlyCreation = !($options['update'] === true);

        $payloadsQueryConds = null;
        if (isset($options['class_filter']) && !empty($options['class_filter'])) {
            $payloadsQueryConds = [
                'type' => [$options['class_filter']],
            ];
        }

        /** @var OCMPayload[] $payloads */
        $payloads = OCMPayload::fetchObjectList(
            OCMPayload::definition(),
            null,
            $payloadsQueryConds,
            ['priority' => 'asc', 'modified_at' => 'asc'],
            null,
            true
        );
        $payloadCount = count($payloads);

        if ($cli) {
            $cli->output("Import $payloadCount payloads " . implode(', ', $options['class_filter']));
        }

        $countProcessed = 0;
        $count = [];
        $executionInfo = [];
        $stat = [];

        foreach ($payloads as $payload) {
            $className = $payload->type();
            if (!isset($count[$className])) {
                $count[$className] = 0;
            }
            if (!isset($stat[$className])){
                $stat[$className] = [
                    'c' => 0,
                    'u' => 0,
                    'f' => 0,
                ];
            }
            $count[$className]++;
        }

        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            if (!isset($count[$className]) || $count[$className] === 0){
                $executionInfo[$className] = [
                    'status' => 'success',
                    'action' => 'import',
                    'update' => '<small>Nessun contenuto da processare</small>',
                    'sheet' => '',
                    'range' => '',
                ];
            }else{
                $executionInfo[$className] = [
                    'status' => 'running',
                    'action' => 'import',
                    'update' => '<small>Contenuti processati: 0 di ' . $count[$className] . '</small>',
                    'sheet' => '',
                    'range' => '',
                ];
            }
        }
        OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);

        foreach ($payloads as $index => $payload) {
            $countProcessed++;
            $className = $payload->type();

            if (!$className::canImport()) {
                $executionInfo[$className] = [
                    'status' => 'skipped',
                    'action' => 'import',
                    'message' => '',
                    'class' => $className,
                ];
            } else {
                if ($cli) {
                    $countIndex = $index + 1;
                    $cli->output(" - $countIndex/$payloadCount " . $className . ' ' . $payload->id(), false);
                }

                try {
                    $response = $payload->createOrUpdateContent($onlyCreation);
                    if ($response == 'create') {
                        $stat[$className]['c']++;
                        if ($cli) {
                            $cli->output(' created');
                        }
                    } else {
                        $stat[$className]['u']++;
                        if ($cli) {
                            $cli->output(' updated');
                        }
                    }
                } catch (Throwable $e) {
                    $stat[$className]['f']++;
                    if ($cli) {
                        $cli->output(" error: " . $e->getMessage());
                    }
                }
//                if ($index % 10 === 0) {

                $status = 'running';
                if ($count[$className] <= array_sum($stat[$className])){
                    $status = $stat[$className]['f'] > 0 ? 'warning' : 'success';
                }

                $executionInfo[$className] = [
                    'status' => $status,
                    'action' => 'import',
                    'process' => $countProcessed . '/' . $payloadCount,
                    'update' => '<small>Contenuti processati: ' . array_sum($stat[$className]) . ' di ' . $count[$className]
                        . '<br />Creati: ' . $stat[$className]['c'] . ' contenuti'
                        . '<br />Aggiornati: ' . $stat[$className]['u'] . ' contenuti'
                        . '<br />Errori di importazione: ' . $stat[$className]['f'] . '</small>',
                    'class' => $className,
                ];
//                $cli->output($executionInfo[$className]['update']);
//                }

                if ($index % 5 === 0) {
                    OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
                }
            }
        }

        self::setCurrentStatus('import', 'done', $options, $executionInfo);

        return $executionInfo;
    }

    public static function export(eZCLI $cli = null, array $options = []): array
    {
        if (self::getCurrentStatus('export')['status'] == 'running') {
            if ($cli) {
                $cli->output('Already running');
            }
            self::setAlreadyRunningStatus('export', $options);
            return [];
        }

        if (OCMigration::discoverContext() === false) {
            throw new Exception('Wrong context');
        }

        OCMigration::createTableIfNeeded($cli);

        $override = !($options['update'] === true);
        if ($override && $cli) {
            $cli->output("Run in override mode");
        }

        self::setCurrentStatus('export', 'running', $options, []);

        try {
            OCMigration::factory()->fillData(
                $options['class_filter'] ?? [],
                $options['update']
            );
        } catch (Throwable $e) {
            self::setCurrentStatus('export', 'error', $options, $e->getMessage());
            return ['status' => 'error']; //@todo
        }

        $options['info'] = [];
        self::setCurrentStatus('export', 'done', $options);

        return ['status' => 'success']; //@todo
    }

    private function getDataHash($sheetTitle): array
    {
        if (!isset($this->dataHash[$sheetTitle])) {
            $this->dataHash[$sheetTitle] = $this->spreadsheet->getSheetDataHash($sheetTitle);
            // @todo ciclo for su $dataStartAtRow - 2
            array_shift($this->dataHash[$sheetTitle]); // help text
            array_shift($this->dataHash[$sheetTitle]); // example
        }
        return $this->dataHash[$sheetTitle];
    }
}