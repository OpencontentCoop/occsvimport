<?php

require 'autoload.php';

use Opencontent\Google\GoogleSheet;
use Opencontent\Google\GoogleSheetClient;


$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();
$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);

$client = new GoogleSheetClient();
$googleSheetService = $client->getGoogleSheetService();
$spreadsheetId = OCMigrationSpreadsheet::getConnectedSpreadSheet();
$spreadsheet = new GoogleSheet($spreadsheetId);

foreach (OCMigration::getAvailableClasses() as $className) {
    $cli->warning($className);
    $row = $spreadsheet->getSheetDataHash($className::getSpreadsheetTitle())[0];
    /** @var ocm_interface $test */
    $test = new $className;
    print_r(array_diff(
        array_keys($row),
        array_keys($test->toSpreadsheet())
    ));
}

$script->shutdown();
