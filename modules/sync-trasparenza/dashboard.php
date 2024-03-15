<?php

/** @var eZModule $module */
$module = $Params['Module'];
$requestAction = $Params['Action'];
$requestId = $Params['ID'];

$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();

$tpl->setVariable('error_spreadsheet', false);

function jsonEncodeError(Throwable $e)
{
    return json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => explode(PHP_EOL, $e->getTraceAsString()),
    ]);
}

if ($http->hasPostVariable('trasparenza_spreadsheet') && $http->postVariable('trasparenza_spreadsheet') !== "") {
    try {
        $spreadsheetId = OCGoogleSpreadsheetHandler::getSpreadsheetIdFromUri(
            $http->postVariable('trasparenza_spreadsheet')
        );
        OCTrasparenzaSpreadsheet::setConnectedSpreadSheet($spreadsheetId);
        $module->redirectTo('/sync-trasparenza/dashboard');
        return;
    } catch (Exception $e) {
        $tpl->setVariable('error_spreadsheet', $e->getMessage());
    }
}

if (!$requestAction && $http->hasPostVariable('remove_trasparenza_spreadsheet')) {
    OCTrasparenzaSpreadsheet::removeConnectedSpreadSheet();
    $module->redirectTo('/sync-trasparenza/dashboard');
    return;
}

if (!$requestAction && $http->hasPostVariable('refresh_trasparenza_spreadsheet')) {
    OCTrasparenzaSpreadsheet::refresh();
    $module->redirectTo('/sync-trasparenza/dashboard');
    return;
}

if (!$requestAction && $http->hasPostVariable('sync_trasparenza_spreadsheet')) {
    OCTrasparenzaSpreadsheet::syncItems((array)$http->postVariable('Select'));
    $module->redirectTo('/sync-trasparenza/dashboard');
    return;
}

if ($requestAction === 'credentials' && $http->hasPostVariable('store_google_credentials')) {
    try {
        $data = $http->postVariable('store_google_credentials');
        OCMGoogleSheetClient::setGoogleCredentials(trim($data));
        $module->redirectTo('/sync-trasparenza/dashboard');
        return;
    } catch (Exception $e) {
        $tpl->setVariable('error_spreadsheet', $e->getMessage());
    }
}

if ($requestAction === 'diff' && $requestId) {
    if ($http->hasPostVariable('Sync')){
        $field = $http->postVariable('Field');
        if ($field) {
            OCTrasparenzaSpreadsheet::syncItems([$requestId], [$field]);
            $module->redirectTo('/sync-trasparenza/dashboard/diff/' . $requestId);
            return;
        }
    }
    $diff = OCTrasparenzaSpreadsheet::diff($requestId);
    $tpl->setVariable('diff', $diff);
    $tpl->setVariable('raw', isset($_GET['raw']));
    $tpl->setVariable('item', OCTrasparenzaSpreadsheet::fetchByRemoteId($requestId));
}


$tpl->setVariable('ezxform_token', ezxFormToken::getToken());
try {
    $tpl->setVariable('trasparenza_spreadsheet', OCTrasparenzaSpreadsheet::getConnectedSpreadSheet());
    $tpl->setVariable('trasparenza_spreadsheet_title', OCTrasparenzaSpreadsheet::getConnectedSpreadSheetTitle());
} catch (Exception $e) {
    $tpl->setVariable('error_spreadsheet', $e->getMessage());
    $tpl->setVariable('trasparenza_spreadsheet_title', '');
}

$sheetClient = new OCMGoogleSheetClient();
$credentials = $sheetClient->getCredentials();
$user = false;
if ($credentials) {
    $user = $credentials['client_email'];
}
$tpl->setVariable('google_user', $user);
$tpl->setVariable('google_credentials', $credentials);

if (!$requestAction) {
    try {
        if (OCTrasparenzaSpreadsheet::count(OCTrasparenzaSpreadsheet::definition())) {
            $tpl->setVariable('fields', OCTrasparenzaSpreadsheet::getDataFields());
            $tpl->setVariable('need_fix', count(OCTrasparenzaSpreadsheet::fetchObjectsWithCheck()));
            $tpl->setVariable(
                'data',
                OCTrasparenzaSpreadsheet::fetchObjectList(
                    OCTrasparenzaSpreadsheet::definition(), null, null, ['index' => 'asc']
                )
            );
        }
    } catch (Exception $e) {
        $tpl->setVariable('error_spreadsheet', $e->getMessage());
    }
}

$Result = [];
if ($requestAction === 'credentials') {
    $Result['content'] = $tpl->fetch('design:sync-trasparenza/credentials.tpl');
} elseif ($requestAction === 'diff') {
    $Result['content'] = $tpl->fetch('design:sync-trasparenza/diff.tpl');
} else {
    $Result['content'] = $tpl->fetch('design:sync-trasparenza/dashboard.tpl');
}
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