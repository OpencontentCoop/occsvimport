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


foreach (OCMigration::getAvailableClasses() as $className) {
    $cli->warning($className);
    try {
        $sample = new $className;
        $sampleData = $sample->toSpreadsheet();
        $cli->output(implode("\t", array_keys($sampleData)));
//        print_r(array_keys($sampleData));
    }catch (Exception $e){
        $cli->error($e->getMessage());
    }
}

$script->shutdown();
