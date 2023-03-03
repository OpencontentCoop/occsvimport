<?php
/** @var eZModule $module */
$module = $Params['Module'];
$requestAction = $Params['Action'];
$requestId = $Params['ID'];

$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();
$context = OCMigration::discoverContext();

$tpl->setVariable('instance', OpenPABase::getCurrentSiteaccessIdentifier());
$tpl->setVariable('version', OCMigration::version());
$tpl->setVariable('db_name', eZDB::instance()->DB);
$tpl->setVariable('ezxform_token', ezxFormToken::getToken());
$tpl->setVariable('error_spreadsheet', false);
$tpl->setVariable('context', $context);
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

function jsonEncodeError(Throwable $e)
{
    return json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
//        'trace' => $e->getTraceAsString(),
    ]);
}

if (!$context){
    $sort = [];
    /** @var ocm_interface $class */
    foreach (array_keys($classHash) as $class){
        $sort[$class::getImportPriority()][$class] = $classHash[$class];
    }
    ksort($sort);
    $sortedClasses = [];
    foreach ($sort as $i => $_classes){
        foreach ($_classes as $name => $value){
            $sortedClasses[$name] = $value;
        }
    }
    $classHash = $sortedClasses;
}

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

# /migration/dashboard/payload/ID
# legge il payload salvato
if (!$context && $requestAction === 'payload' && !empty($requestId)) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        echo OCMPayload::fetch($requestId)->attribute('payload');
    } catch (Throwable $e) {
        echo jsonEncodeError($e);
    }
    eZExecution::cleanExit();
}

# /migration/dashboard/import/ID
# importa il payload salvato in ez
if (!$context && $requestAction === 'import' && !empty($requestId)) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        echo json_encode(OCMPayload::fetch($requestId)->createOrUpdateContent());
    } catch (Throwable $e) {
        echo jsonEncodeError($e);
    }
    eZExecution::cleanExit();
}

# /migration/dashboard/store_payload/ID?type=TYPE
# converte l'elemento TYPE con id ID e salva il payload
if ($requestAction === 'store_payload' && !empty($requestId) && $http->hasVariable('type')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        $class = $http->variable('type');
        if (in_array($class, $classes)) {
            /** @var OCMPersistentObject[] $items */
            $items = $class::fetchByField('_id', $requestId);
            if (isset($items[0])) {
                $items[0]->storePayload();
                echo json_encode(
                    $items[0]->generatePayload()
                );
            }
        }else{
            throw new Exception("$class type not found");
        }
    } catch (Throwable $e) {
        echo jsonEncodeError($e);
    }
    eZExecution::cleanExit();
}

# /migration/dashboard/create/ID?type=TYPE
# crea l'elemento TYPE a partire dal contenuto ez con remote_id == ID
if ($context && $requestAction === 'create' && !empty($requestId) && $http->hasVariable('type')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        $class = $http->variable('type');
        if (in_array($class, $classes)) {
            $object = eZContentObject::fetchByRemoteID($requestId);
            if (!$object instanceof eZContentObject){
                throw new Exception('Content not found');
            }
            $node = $object->mainNode();
            if (!$node instanceof eZContentObjectTreeNode){
                throw new Exception('Node not found');
            }
            echo json_encode(OCMigration::factory($context)->createFromNode($node, new $class, ['is_update' => false]));
        }else{
            throw new Exception("$class type not found");
        }
    } catch (Throwable $e) {
        echo jsonEncodeError($e);
    }
    eZExecution::cleanExit();
}

# /migration/dashboard/TYPE/ID
# visualizza l'elemento TYPE con id ID
if (in_array($requestAction, $classes) && !empty($requestId)) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    try {
        $class = $requestAction;
        if (in_array($class, $classes)) {
            /** @var ocm_interface[] $items */
            $items = $class::fetchByField('_id', $requestId);
            if (isset($items[0])) {
                echo json_encode(
                    $items[0]->toSpreadsheet()
                );
            }else{
                throw new Exception("$class $requestId type not found");
            }
        }else{
            throw new Exception("$class type not found");
        }
    } catch (Throwable $e) {
        echo jsonEncodeError($e);
    }
    eZExecution::cleanExit();
}

if ($requestAction === 'datatable') {
    $class = $requestId;
    $rows = [];
    $rowCount = 0;
    $length = $context ? 100 : 5000;//@todo $http->getVariable('length', 10);
    $start = 0; //@todo $http->getVariable('start', 0);

    if (in_array($class, $classes)) {
        if ($context) {
            /** @var eZPersistentObject|ocm_interface $class */
            $rowCount = (int)$class::count($class::definition());
            $rows = $class::fetchObjectList(
                $class::definition(),
                null,
                null,
                [$class::getSortField() => 'asc'],
                ['limit' => $length, 'offset' => $start],
                false
            );
        }else{
            $rowCount = (int)OCMPayload::count(OCMPayload::definition(), ['type' => $class, 'error' => ['!=', '']]);
            $rows = OCMPayload::fetchObjectList(
                OCMPayload::definition(),
                ['id', 'modified_at', 'executed_at', 'error'],
                ['type' => $class, 'error' => ['!=', '']],
                ['executed_at' => 'asc'],
                ['limit' => $length, 'offset' => $start],
                false
            );
            foreach ($rows as $index => $row){
                $rows[$index]['modified_at'] = empty($row['modified_at']) ? '' : date('D, d M Y H:i:s', $row['modified_at']);
                $rows[$index]['executed_at'] = empty($row['executed_at']) ? '' : date('D, d M Y H:i:s', $row['executed_at']);
            }
        }
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

if ($requestAction === 'fields') {
    $data = [];
    if ($context) {
        $class = $requestId;
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
    }else {
        foreach (['id', 'modified_at', 'executed_at', 'error'] as $field) {
            $data[] = [
                'data' => $field,
                'title' => trim(str_replace('_', ' ', $field)),
                'name' => $field,
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

if ($requestAction === 'status') {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    echo json_encode(OCMigrationSpreadsheet::getCurrentStatus());
    eZExecution::cleanExit();
}

if ($requestAction === 'reset') {
    OCMigrationSpreadsheet::resetCurrentStatus();
    $module->redirectModule($module, 'dashboard');
    return;
}

if ($requestAction === 'run') {
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

if ($requestAction === 'configure') {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    $className = $requestId;
    $result = false;
    if (in_array($className, $classes)) {
        $addConditionalFormatRules = $http->getVariable('configuration') === 'format';
        $addDateValidations = $http->getVariable('configuration') === 'date-validation';
        $addRangeValidations = $http->getVariable('configuration') === 'range-validation';
        //$result = var_export([$className, $addConditionalFormatRules, $addDateValidations, $addRangeValidations], true);
        $result = OCMigrationSpreadsheet::instance()->configureSheet(
            $className,
            $addConditionalFormatRules,
            $addDateValidations,
            $addRangeValidations
        );
    }
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