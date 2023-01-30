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
    "[only:][update]",
    "", [
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);

$opt = [
    'class_filter' => $options['only'] ? explode(',', $options['only']) : [],
    'update' => $options['update'] ? true : false,
];

try {
    $executionInfo = OCMigrationSpreadsheet::instance()->push($options['verbose'] ? $cli: null, $opt);
}catch (Throwable $e){
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

print_r($executionInfo);

$script->shutdown();