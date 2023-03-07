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

$client = OCMigrationSpreadsheet::instanceGoogleSheetClient();
$googleSheetService = $client->getGoogleSheetService();
$spreadsheetId = OCMigrationSpreadsheet::getConnectedSpreadSheet();
$spreadsheet = new GoogleSheet($spreadsheetId);

foreach (OCMigration::getAvailableClasses($options['only'] ? explode(',', $options['only']) : []) as $className) {
    try {
        $row = $spreadsheet->getSheetDataHash($className::getSpreadsheetTitle())[0];
        unset($row['IGNORA']);
        $sheetHeaders = array_keys($row);

        /** @var ocm_interface $test */
        $test = new $className;
        $mappedHeaders = array_keys($test->toSpreadsheet());
        $diff = array_diff(
            $sheetHeaders,
            $mappedHeaders
        );
        if (!empty($diff)){
            $cli->warning($className);
            foreach ($diff as $head){
                $cli->warning(' - ' . $head);
            }
        }else{
            $cli->output($className . ' ok');
        }

        if ($options['verbose']){
            var_dump($sheetHeaders);
            var_dump($mappedHeaders);
        }

    }catch (Exception $e){
        $cli->error($className . ' ' . $e->getMessage());
    }
}

$script->shutdown();
