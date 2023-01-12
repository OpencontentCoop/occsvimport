<?php

$module = $Params['Module'];
$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();

$tpl->setVariable('ezxform_token', ezxFormToken::getToken());
$tpl->setVariable('error_spreadsheet', false);
$tpl->setVariable('context', OCMigration::discoverContext());
$tpl->setVariable('migration_spreadsheet', OCMigrationSpreadsheet::getConnectedSpreadSheet());

$classes = OCMigration::getAvailableClasses();
$classHash = [];
foreach ($classes as $class) {
    $classHash[$class] = $class::getSpreadsheetTitle();
}
$tpl->setVariable('class_hash', $classHash);

if ($http->hasPostVariable('migration_spreadsheet')) {
    try {
        $spreadsheetId = OCGoogleSpreadsheetHandler::getSpreadsheetIdFromUri(
            $http->postVariable('migration_spreadsheet')
        );
        OCMigrationSpreadsheet::setConnectedSpreadSheet($spreadsheetId);
        $module->redirectTo('/migration/dashboard');
        return;
    } catch (Exception $e) {
        $tpl->setVariable('error_spreadsheet', $e->getMessage());
    }
}

if ($http->hasPostVariable('remove_migration_spreadsheet')) {
    OCMigrationSpreadsheet::removeConnectedSpreadSheet();
    $module->redirectTo('/migration/dashboard');
    return;
}

if ($http->hasGetVariable('datatable')) {
    $class = $http->getVariable('datatable');
    $rows = [];
    $rowCount = 0;
    $length = 100;//@todo $http->getVariable('length', 10);
    $start = 0; //@todo $http->getVariable('start', 0);
    if (in_array($class, $classes)) {
        /** @var eZPersistentObject $class */
        $rowCount =(int)$class::count($class::definition());
        $rows = $class::fetchObjectList($class::definition(), null, null, ['_id' => 'asc'], ['limit' => $length, 'offset' => $start], false);
    }
    $data = [
        'draw' => isset($_GET['draw']) ? ++$_GET['draw'] : 0,
        'recordsTotal' => $rowCount,
        'recordsFiltered' => $rowCount,
        'data' => $rows,
        'params' => [],
    ];
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        echo json_encode($data);
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
    eZExecution::cleanExit();
}

if ($http->hasGetVariable('fields')) {
    $class = $http->getVariable('fields');
    $data = [];
    if (in_array($class, $classes)) {
        foreach ($class::$fields as $field) {
            $data[] = [
                'data' => $field,
                'title' => str_replace('_', ' ', $field),
                'name' => $field,
                'searchable' => false,
                'sortable' => false,
            ];
        }
        $data[] = [
            'data' => '_id',
            'title' => 'ID',
            'name' => '_id',
            'searchable' => false,
            'sortable' => false,
        ];
    }
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    echo json_encode($data);
    eZExecution::cleanExit();
}


if ($http->hasGetVariable('status')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    echo json_encode(OCMigrationSpreadsheet::getCurrentStatus());
    eZExecution::cleanExit();
}

if ($http->hasGetVariable('action')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        echo json_encode(
            OCMigrationSpreadsheet::runAction(
                $http->getVariable('action'),
                (array)$http->getVariable('options')
            )
        );
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'options' => [],
        ]);
    }
    eZExecution::cleanExit();
}


echo $tpl->fetch('design:migration/dashboard.tpl');

//eZDisplayDebug();
eZExecution::cleanExit();