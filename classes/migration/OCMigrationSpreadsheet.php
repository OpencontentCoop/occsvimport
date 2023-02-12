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

    private $dataHash;

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
//        $response = $this->googleSheetService->spreadsheets->get($this->spreadsheetId, ['fields' => 'sheets(properties(title,sheetId),conditionalFormats)'])->toSimpleObject();
//        foreach ($response->sheets as $sheetData){
//            if ($sheetData->properties->sheetId === $sheet->getProperties()->getSheetId()){
//                $currentRules = $sheetData;
//                break;
//            }
//        }

        $responses = [];

        $addConditionalFormatRulesRequests = [];
        if ($addConditionalFormatRules) {
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

            if (!empty($addConditionalFormatRulesRequests)) {
                try {
                    $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => $addConditionalFormatRulesRequests
                    ]);
                    $responses[] = $this->googleSheetService->spreadsheets->batchUpdate(
                        $this->spreadsheetId,
                        $batchUpdateRequest
                    );
//                    )->toSimpleObject();
                } catch (Exception $e) {
                    $responses[] = $e->getMessage();
                }
            }

        }

        $setDataValidationRequests = [];
        if ($addDateValidations) {
            $dateValidationHeaders = $className::getDateValidationHeaders();
            if (!empty($dateValidationHeaders)) {
                $dateRanges = [];
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
        }

        if (!empty($setDataValidationRequests)) {
            try {
                $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                    'requests' => $setDataValidationRequests
                ]);
                $responses[] = $this->googleSheetService->spreadsheets->batchUpdate(
                    $this->spreadsheetId,
                    $batchUpdateRequest
                );
//                )->toSimpleObject();
            } catch (Exception $e) {
                $responses[] = json_decode($e->getMessage());
            }
        }

        return [
            'args' => func_get_args(),
            'reponses' => $responses,
            'requests' => [
                'conditional' => $addConditionalFormatRulesRequests,
                'validation' => $setDataValidationRequests,
            ],
            'format' => $addConditionalFormatRulesRequests,
            'validations' =>$setDataValidationRequests,
        ];
    }

    private function getHeaders($sheetTitle): array
    {
        $sheet = $this->spreadsheet->getByTitle($sheetTitle);
        $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
        $range = "{$sheetTitle}!R1C1:R1C{$colCount}";
        $firstRow = $this->googleSheetService->spreadsheets_values->get($this->spreadsheetId, $range)->getValues();
        return $firstRow[0];
    }

    private function getColumnRange(array $ref): ?string
    {
        $sheet = $ref['sheet'];
        $column = $ref['column'];
        $startAt = $ref['start'];

        $headers = $this->getHeaders($sheet);
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

        if ($letter){
            return '=\'' . $sheet . '\'!$' . $letter . '$' . $startAt . ':$' . $letter . '$1000';
        }

        return false;
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
            $colCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
            $range = "$sheetTitle!R1C1:R1C$colCount";
            $firstRow = $this->googleSheetService->spreadsheets_values->get($this->spreadsheetId, $range)->getValues();
            $headers = $firstRow[0];
            $colCount = count($headers);
            $startCleanAtRow = self::$dataStartAtRow;
            $range = "$sheetTitle!R{$startCleanAtRow}C1:R{$rowCount}C$colCount";

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
                $data = $item->toSpreadsheet();
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
                    // cancellare valori non formule
                    $clear = new Google_Service_Sheets_ClearValuesRequest();
                    $this->googleSheetService->spreadsheets_values->clear($this->spreadsheetId, $range, $clear);

                    $updateRows = $this->googleSheetService->spreadsheets_values->update(
                        $this->spreadsheetId,
                        $range,
                        $body,
                        $params
                    )->getUpdatedRows();
                } else {
                    $startAtRow = $this->getLastRowIndex($sheetTitle);
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
                $cli->output('Written ' . $updateRows . ' rows in sheet ' . $sheetTitle);
            }

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
        if ($this->dataHash === null) {
            $this->dataHash = $this->spreadsheet->getSheetDataHash($sheetTitle);
            // @todo ciclo for su $dataStartAtRow - 2
            array_shift($this->dataHash); // help text
            array_shift($this->dataHash); // example
        }
        return $this->dataHash;
    }

    private function getLastRowIndex($sheetTitle): int
    {
        return count($this->getDataHash($sheetTitle)) + self::$dataStartAtRow;
    }
}