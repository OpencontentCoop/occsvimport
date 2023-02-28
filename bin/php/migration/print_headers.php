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
$options = $script->getOptions(
    "[only:]",
    "", [
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);

$client = new GoogleSheetClient();
$googleSheetService = $client->getGoogleSheetService();
$spreadsheetId = OCMigrationSpreadsheet::getConnectedSpreadSheet();
$spreadsheet = new GoogleSheet($spreadsheetId);

foreach (OCMigration::getAvailableClasses($options['only'] ? explode(',', $options['only']) : []) as $className) {
    $cli->warning($className);
    try {
        $row = $spreadsheet->getSheetDataHash($className::getSpreadsheetTitle())[0];
        /** @var ocm_interface $test */
        $test = new $className;
        print_r(
            array_diff(
                array_keys($row),
                array_keys($test->toSpreadsheet())
            )
        );
    }catch (Exception $e){
        $cli->error($e->getMessage());
    }
}

$script->shutdown();
