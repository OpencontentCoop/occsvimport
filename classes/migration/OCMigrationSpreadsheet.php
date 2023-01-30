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
        }
        $siteData->setAttribute(
            'value',
            json_encode([
                'timestamp' => date('c'),
                'action' => $action,
                'status' => $status,
                'options' => $options,
                'message' => $message,
            ])
        );
        $siteData->store();
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
            self::setCurrentStatus('unknown', 'unknown', []);
        } else {
            $command = 'bash extension/occsvimport/bin/bash/migration/run.sh ' . eZSiteAccess::current()['name'] . ' ' . $action . ' ' . $only . ' ' . $update;
            eZDebug::writeError($command);
            exec($command);
            sleep(2);
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

    /**
     * @throws Exception
     */
    public function push(eZCLI $cli = null, array $options = []): array
    {
        if (self::getCurrentStatus('push')['status'] == 'running'){
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
        }

        self::setCurrentStatus('push', 'done', $options, $executionInfo);

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
                'message' => '',
                'class' => $className,
            ];

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

            // cancellare valori non formule
//            $clear = new Google_Service_Sheets_ClearValuesRequest();
//            $this->googleSheetService->spreadsheets_values->clear($this->spreadsheetId, $range, $clear);

            $customCond = null;
            if (!$override) {
                $ignoreRows = array_column($this->getDataHash($sheetTitle), $className::getIdColumnLabel());
                if (!empty($ignoreRows)) {
                    $customCond = ' WHERE _id NOT IN (\'' . implode('\',\'', $ignoreRows) . '\')';
                }
            }

            $items = $className::fetchObjectList(
                $className::definition(),
                null,
                null,
                ['_id' => 'asc'],
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

            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values,
            ]);
            $params = [
                'valueInputOption' => 'USER_ENTERED',
//                'valueInputOption' => 'RAW',
            ];

            if ($override) {
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
                $updateRows = $this->googleSheetService->spreadsheets_values->append(
                    $this->spreadsheetId,
                    $range,
                    $body,
                    $params
                )->getUpdates()->getUpdatedRows();
            }

            if ($cli) {
                $cli->output('Pushed ' . $updateRows . ' rows in sheet ' . $sheetTitle);
            }

            $executionInfo[$className] = [
                'status' => 'success',
                'update' => $updateRows,
                'sheet' => $sheetTitle,
                'range' => $range,
            ];
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'sheet' => $sheetTitle,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        return $executionInfo;
    }

    public function pull(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('pull')['status'] == 'running'){
            return [];
        }

        if (OCMigration::discoverContext() !== false) {
            throw new Exception('Wrong context');
        }

        self::setCurrentStatus('pull', 'running', $options);

        $executionInfo = [];
        foreach (OCMigration::getAvailableClasses($options['class_filter'] ?? []) as $className) {
            $executionInfo = array_merge($executionInfo, $this->pullByType($className, $cli));
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
                'update' => $count,
                'sheet' => $sheetTitle,
            ];
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'sheet' => $sheetTitle,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

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

        self::setCurrentStatus('import', 'running', $options);

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
                            'trace' => $e->getTraceAsString(),
                            'payload' => $payload ?? null,
                        ];
                }
            }

            $executionInfo[$className] = [
                'status' => $count != $itemCount ? 'warning' : 'success',
                'process' => $itemCount,
                'create' => $created,
                'update' => $updated,
                'class' => $className,
            ];
        } catch (Throwable $e) {
            $executionInfo[$className] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'class' => $className,
            ];
            if ($cli) {
                $cli->error($e->getMessage());
            }
        }

        return $executionInfo;
    }

    public static function export(eZCLI $cli = null, array $options = [])
    {
        if (self::getCurrentStatus('export')['status'] == 'running'){
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

        self::setCurrentStatus('export', 'running', $options);

        OCMigration::factory()->fillData(
            $options['class_filter'] ?? [],
            $options['update']
        );

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