<?php

$module = $Params['Module'];
$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();

$tpl->setVariable('version', OCMigration::version());
$tpl->setVariable('db_name', eZDB::instance()->DB);
$tpl->setVariable('ezxform_token', ezxFormToken::getToken());
$tpl->setVariable('error_spreadsheet', false);
$tpl->setVariable('context', OCMigration::discoverContext());
$tpl->setVariable('migration_spreadsheet', OCMigrationSpreadsheet::getConnectedSpreadSheet());
$tpl->setVariable('migration_spreadsheet_title', OCMigrationSpreadsheet::getConnectedSpreadSheetTitle());
$tpl->setVariable('google_user', 'phpsheet@norse-fiber-323812.iam.gserviceaccount.com'); //@todo

$classes = OCMigration::getAvailableClasses();
$classHash = [];
foreach ($classes as $class) {
    $add = true;
    if (!OCMigration::discoverContext()){
        $add = $class::canImport() || $class::canPull();
    }else{
        $add = $class::canExport() || $class::canPush();
    }
    if ($add) {
        $classHash[$class] = $class::getSpreadsheetTitle();
    }
}
$classHash = array_flip($classHash);
ksort($classHash);
$classHash = array_flip($classHash);
$tpl->setVariable('class_hash', $classHash);

if ($http->hasPostVariable('migration_spreadsheet') && $http->postVariable('migration_spreadsheet') !== "") {
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

if ($http->hasVariable('datatable')) {
    $class = $http->variable('datatable');
    $rows = [];
    $rowCount = 0;
    $length = 100;//@todo $http->getVariable('length', 10);
    $start = 0; //@todo $http->getVariable('start', 0);
    if (in_array($class, $classes)) {
        /** @var eZPersistentObject|ocm_interface $class */
        $rowCount =(int)$class::count($class::definition());
        $rows = $class::fetchObjectList($class::definition(), null, null, [$class::getSortField() => 'asc'], ['limit' => $length, 'offset' => $start], false);
    }
    $data = [
        'draw' => $http->hasVariable('draw') ? ($http->variable('draw') + 1) : 0,
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
        foreach ($class::definition()['fields'] as $field) {
            $data[] = [
                'data' => $field['name'],
                'title' => trim(str_replace('_', ' ', $field['name'])),
                'name' => $field['name'],
                'searchable' => false,
                'sortable' => false,
            ];
        }
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

if ($http->hasGetVariable('configure')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    $className = $http->getVariable('configure');
    $addConditionalFormatRules = $http->getVariable('configuration') === 'format';
    $addDateValidations = $http->getVariable('configuration') === 'date-validation';
    $addRangeValidations = $http->getVariable('configuration') === 'range-validation';
    //$result = var_export([$className, $addConditionalFormatRules, $addDateValidations, $addRangeValidations], true);
    $result = OCMigrationSpreadsheet::instance()->configureSheet($className, $addConditionalFormatRules, $addDateValidations, $addRangeValidations);
    echo json_encode($result);
    eZExecution::cleanExit();
}

$Result = [];
$Result['content'] = $tpl->fetch('design:migration/dashboard.tpl');
$Result['path'] = [];
$contentInfoArray = [
    'node_id' => null,
    'class_identifier' => null,
];
$contentInfoArray['persistent_variable'] = [
    'show_path' => false,
];
if (is_array($tpl->variable('persistent_variable'))) {
    $contentInfoArray['persistent_variable'] = array_merge(
        $contentInfoArray['persistent_variable'],
        $tpl->variable('persistent_variable')
    );
}
$Result['content_info'] = $contentInfoArray;
$Result['pagelayout'] = false;