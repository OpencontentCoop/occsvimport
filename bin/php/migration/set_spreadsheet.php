<?php

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();
$options = $script->getOptions(
    "[master:][name:][force]",
    "",
    [
        'master' => 'Master url',
        'name' => 'Name prefix',
        'force' => 'Force reconnect',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$connected = OCMigrationSpreadsheet::getConnectedSpreadSheet();
if ($connected && !$options['force']){
    $cli->output("Spreadsheet already connected " . $connected);
    $script->shutdown(0);
}

$siteName = eZINI::instance()->variable('SiteSettings', 'SiteName');
$siteName = str_ireplace('Comune di ', '', $siteName);
$name = isset($options['name']) ? $options['name'] : '';
$name .= ' - ' . $siteName;

$client = new OCMGoogleSheetClient();
$user = isset($client->getCredentials()['client_email']) ? $client->getCredentials()['client_email'] : '?';
$cli->warning('User ' . $user);

if (!$options['master']){
    die('Missing master');
}
$spreadsheetUrl = $options['master'];
$masterSpreadsheetId = OCGoogleSpreadsheetHandler::getSpreadsheetIdFromUri($spreadsheetUrl);
$masterSpreadsheet = new \Opencontent\Google\GoogleSheet($masterSpreadsheetId, $client);

$googleClient = $client->getGoogleClient();
$googleClient->setScopes([Google_Service_Sheets::SPREADSHEETS, Google_Service_Drive::DRIVE]);
$serviceDrive = new Google_Service_Drive($googleClient);
$file = new Google_Service_Drive_DriveFile();
$file->setName($name);
try {
    $spreadsheet = $serviceDrive->files->copy($masterSpreadsheetId, $file);
    $cli->warning('Sheet: ' . $spreadsheet->getId() . ' ' . $spreadsheet->getName());
    OCMigrationSpreadsheet::setConnectedSpreadSheet($spreadsheet->getId());
} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();