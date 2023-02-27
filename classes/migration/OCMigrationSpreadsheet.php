<?php

use Google_Service_Sheets_ClearValuesRequest as Google_Service_Sheets_ClearValuesRequestAlias;
use Opencontent\Google\GoogleSheet;
use Opencontent\Google\GoogleSheetClient;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\EnvironmentLoader;

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
        if ($id){
            $spreadsheet = new GoogleSheet($id);
            $title = $spreadsheet->getTitle();
        }

        return $title;
    }

    public static function setConnectedSpreadSheet($spreadsheet)
    {
        $checkAccessSpreadsheet = new GoogleSheet($spreadsheet);
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
        }elseif ($message === null){
            $current = json_decode($siteData->attribute('value'), true);
            $message = $current['message'];
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
//            'pull',
//            'import',
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

        if ($action === 'reset') {
            self::setCurrentStatus('', '', [], []);
        } else {
            $command = 'bash extension/occsvimport/bin/bash/migration/run.sh ' . eZSiteAccess::current()['name'] . ' ' . $action . ' ' . $only . ' ' . $update;
            eZDebug::writeError($command);
            exec($command);
            sleep(2);

            $executionInfo = [];
            foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
                $canMethod = 'can' . ucfirst($action);
                if (method_exists($className, $canMethod) && $className::$canMethod()) {
                    $executionInfo[$className] = [
                        'status' => 'pending',
                        'action' => $action,
                        'update' => "In attesa di eseguire l'azione: $action...",
                        'sheet' => '',
                        'range' => '',
                    ];
                }else{
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
        $client = new GoogleSheetClient();
        $this->googleSheetService = $client->getGoogleSheetService();
        $this->spreadsheetId = self::getConnectedSpreadSheet();
        $this->spreadsheet = new GoogleSheet($this->spreadsheetId);
        //$sheets = $spreadsheet->getSheetTitleList();
    }

    public static function getMasterSpreadsheet(): ?GoogleSheet
    {
        if (self::$masterSpreadsheet === null){
            $context = OCMigration::discoverContext();
            if ($context) {
                $shortUrl = 'https://link.opencontent.it/new-kit-' . $context;
                $ch = curl_init();
                $timeout = 0;
                curl_setopt ($ch, CURLOPT_URL, $shortUrl);
                curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_HEADER, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // Getting binary data
                curl_exec($ch);
                $info = curl_getinfo($ch);
                $spreadsheetUrl = $info['redirect_url'];
                self::$masterSpreadsheetId = OCGoogleSpreadsheetHandler::getSpreadsheetIdFromUri($spreadsheetUrl);
                self::$masterSpreadsheet = new GoogleSheet(self::$masterSpreadsheetId);
                self::$masterSpreadsheetUrl = $spreadsheetUrl;
            }
        }

        return self::$masterSpreadsheet;
    }

    public static function getMasterSpreadsheetHelpTexts($sheetTitle): array
    {
        $sheet = self::getMasterSpreadsheet()->getByTitle($sheetTitle);
        $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
        $range = "{$sheetTitle}!R1C1:R2C{$colCount}";
        $client = new GoogleSheetClient();
        $firstRows = $client->getGoogleSheetService()->spreadsheets_values->get(self::$masterSpreadsheetId, $range)->getValues();

        $helper = [];
        foreach ($firstRows[0] as $index => $header){
            $helper[$header] = $firstRows[1][$index] ?? '';
        }

        return $helper;
    }

    public function updateGuide(): ?int
    {
        self::getMasterSpreadsheet();
        $url = str_replace('copy', 'edit', self::$masterSpreadsheetUrl);
        $data = '=IMPORTRANGE("'.$url.'";"Istruzioni!A1:E50")';
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
        $client = new GoogleSheetClient();
        $data = $client->getGoogleSheetService()->spreadsheets_values->get(self::$masterSpreadsheetId, $masterRange)->getValues();

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


    public function updateHelper($className): ?int
    {
        $sheetTitle = $className::getSpreadsheetTitle();
        $helper = self::getMasterSpreadsheetHelpTexts($sheetTitle);
        $headers = $this->getHeaders($sheetTitle);
        $value = [];
        foreach ($headers as $header) {
            $value[$header] = $helper[$header] ?? '';
        }
        $values = [array_values($value)];

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values,
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        $colCount = count($headers);
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

    public function configureSheet($className, $addConditionalFormatRules = true, $addDateValidations = true, $addRangeValidations = true)
    {
        if (strpos($className, 'ocm_') === false){
            return false;
        }
        $sheetTitle = $className::getSpreadsheetTitle();
        $sheet = $this->spreadsheet->getByTitle($sheetTitle);
        $headers = $this->getHeaders($sheetTitle);
        $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();

        $currentRules = [];
        $response = $this->googleSheetService->spreadsheets->get($this->spreadsheetId, ['fields' => 'sheets(properties(title,sheetId),conditionalFormats)'])->toSimpleObject();
        foreach ($response->sheets as $sheetData){
            if ($sheetData->properties->sheetId === $sheet->getProperties()->getSheetId()){
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
                            'index' => $index
                        ]
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
                                    'backgroundColorStyle' => ['rgbColor' => ['red' => 1]]
                                ]
                            ]
                        ],
                        'index' => 0
                    ]
                ]);
            }

            $internalLinkConditionalFormatHeaders = $className::getInternalLinkConditionalFormatHeaders();
            if (!empty($internalLinkConditionalFormatHeaders)){
                $internalLinkRanges = [];
                foreach ($headers as $index => $header) {
                    if (in_array($header, $internalLinkConditionalFormatHeaders)){
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
                                        ]
                                    ],
                                    'format' => [
                                        'backgroundColorStyle' => ['rgbColor' => [
                                            'red' => 1,
                                            'green' => 0.549,
                                            'blue' => 0
                                        ]]
                                    ]
                                ]
                            ],
                            'index' => 0
                        ]
                    ]);
                }
            }

            $max160CharConditionalFormatHeaders = $className::getMax160CharConditionalFormatHeaders();
            if (!empty($max160CharConditionalFormatHeaders)){
                foreach ($headers as $index => $header) {
                    if (in_array($header, $max160CharConditionalFormatHeaders)){
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
                                        ]
                                    ],
                                    'booleanRule' => [
                                        'condition' => [
                                            'type' => 'CUSTOM_FORMULA',
                                            'values' => [
                                                ['userEnteredValue' => '=LEN(REGEXREPLACE('. $this->getColumnLetter($sheetTitle, $header) .'4;"</?\S+[^<>]*>";""))>255'],
                                            ]
                                        ],
                                        'format' => [
                                            'backgroundColorStyle' => ['rgbColor' => [
                                                'red' => 1,
                                                'green' => 0.549,
                                                'blue' => 0
                                            ]]
                                        ]
                                    ]
                                ],
                                'index' => 0
                            ]
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
                                        'values' => []
                                    ],
                                ],
                            ]
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
                                                ['userEnteredValue' => $userEnteredValue]
                                            ]
                                        ],
                                    ],
                                ]
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
                                        'values' => []
                                    ],
                                ],
                            ]
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
                    'requests' => $requests
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
        if ($letter){
            return '=\'' . $sheet . '\'!$' . $letter . '$' . $startAt . ':$' . $letter . '$' . $rowCount;
        }

        return false;
    }

    private function getColumnLetter($sheetTitle, $column): string
    {
        $headers = $this->getHeaders($sheetTitle);
        $alphabeth = range('A', 'Z');
        $letter = false;
        foreach ($headers as $index => $header){
            $prefix = '';
            if ($index > 25){
                $index = $index - 25;
                $prefix = 'A';
            }
            if ($column == $header){
                $letter = $prefix.$alphabeth[$index];
            }
        }

        return $letter;
    }

    /**
     * @throws Exception
     */
    public function push(eZCLI $cli = null, array $options = []): array
    {
        if (self::getCurrentStatus('push')['status'] == 'running'){
            if ($cli) $cli->output('Already running');
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
            $cli->warning("Run in override mode");
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

            OCMigrationSpreadsheet::appendMessageToCurrentStatus([$className => [
                'status' => 'running',
                'update' => 'Preparazione dei contenuti...',
            ]]);

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
            $message =  ($e instanceof \Google\Service\Exception) ? json_decode($e->getMessage())->error->message : $e->getMessage();
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
        foreach ($data as $key => $value){
            if (mb_strlen($value) > 49999){
                $data[$key] = "[Il valore di questo campo supera il limite di caratteri ammessi per una cella.\nSe vuoi che venga importato direttamente dal sito originale non rimuovere né modificare questa cella]\n#" . $item->attribute('_id');
            }
        }

        return $data;
    }

    public function pull(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('pull')['status'] == 'running'){
            if ($cli) $cli->output('Already running');
            return [];
        }

        if (OCMigration::discoverContext() !== false) {
            throw new Exception('Wrong context');
        }

        self::setCurrentStatus('pull', 'running', $options);

        $executionInfo = [];
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $executionInfo = array_merge($executionInfo, $this->pullByType($className, $cli));
            self::appendMessageToCurrentStatus($executionInfo);
        }

        self::setCurrentStatus('pull', 'done', $options, $executionInfo);

        return $executionInfo;
    }

    private function pullByType($className, eZCLI $cli = null): array
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
            foreach ($values as $index => $value) {
                $item = $className::fromSpreadsheet($value);
                if ($item instanceof eZPersistentObject) {
                    $item->store();
                    $count++;
                }
            }

            $executionInfo[$className] = [
                'status' => 'success',
                'action' => 'pull',
                'update' => $count,
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

    public function import(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('import')['status'] == 'running'){
            return [];
        }

        if (OCMigration::discoverContext() !== false) {
            throw new Exception('Wrong context');
        }

        $executionInfo = [];
        $sortedClasses = [];

        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $sortedClasses[$className::getImportPriority()] = $className;
        }
        ksort($sortedClasses);

        self::setCurrentStatus('import', 'running', $options, []);

        foreach ($sortedClasses as $className) {
            $executionInfo = array_merge($executionInfo, $this->importByType($className, $cli));
        }

        self::setCurrentStatus('import', 'done', $options, $executionInfo);

        return $executionInfo;
    }

    private function importByType($className, eZCLI $cli = null): array
    {
        $executionInfo = [];

        if (!$className::canImport()) {
            $executionInfo[$className] = [
                'status' => 'skipped',
                'action' => 'pull',
                'message' => '',
                'class' => $className,
            ];
            OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
            return $executionInfo;
        }

        /** @var ocm_interface[] $items */
        $items = $className::fetchObjectList(
            $className::definition(),
            null,
            null,
            ['_id' => 'asc'],
            null,
            true
        );
        $itemCount = count($items);

        try {
            if ($cli) {
                $cli->output("Import $itemCount $className items");
            }
            $count = $updated = $created = 0;

            foreach ($items as $index => $item) {
                if ($cli) {
                    $countIndex = $index + 1;
                    $cli->output(" - $countIndex/$itemCount " . $item->attribute('_id'));
                }
                try {
                    $payload = $item->generatePayload();
                    if (empty($payload)) {
                        throw new Exception('Empty payload');
                    }
                    $repository = new ContentRepository();
                    $environment = EnvironmentLoader::loadPreset('content');
                    $repository->setCurrentEnvironmentSettings($environment);

                    $result = $repository->createUpdate($payload, true);
                    if ($result['message'] == 'success') {
                        if ($result['method'] == 'create') {
                            $created++;
                        } else {
                            $updated++;
                        }
                        $count++;
                    }
                } catch (Throwable $e) {
                    if ($cli) {
                        $cli->error("   " . $e->getMessage());
                    }
                    $executionInfo['errors'][$className][$item->attribute('_id')] =
                        [
                            'message' => $e->getMessage(),
                            'action' => 'pull',
                            'trace' => $e->getTraceAsString(),
                            'payload' => $payload ?? null,
                        ];
                }
            }

            $executionInfo[$className] = [
                'status' => $count != $itemCount ? 'warning' : 'success',
                'action' => 'pull',
                'process' => $itemCount,
                'create' => $created,
                'update' => $updated,
                'class' => $className,
            ];
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'action' => 'pull',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'class' => $className,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        OCMigrationSpreadsheet::appendMessageToCurrentStatus($executionInfo);
        return $executionInfo;
    }

    public static function export(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('export')['status'] == 'running'){
            if ($cli) $cli->output('Already running');
            return [];
        }

        if (OCMigration::discoverContext() === false) {
            throw new Exception('Wrong context');
        }

        OCMigration::createTableIfNeeded($cli);

        $override = !($options['update'] === true);
        if ($override && $cli) {
            $cli->warning("Run in override mode");
        }

        self::setCurrentStatus('export', 'running', $options, []);

        try {
            OCMigration::factory()->fillData(
                $options['class_filter'] ?? [],
                $options['update']
            );
        }catch (Throwable $e){
            self::setCurrentStatus('import', 'error', $options, $e->getMessage());
            return ['status' => 'error']; //@todo
        }

        $options['info'] = [];
        self::setCurrentStatus('export', 'done', $options);

        return ['status' => 'success']; //@todo
    }

    private function getDataHash($sheetTitle)
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