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
    "[only:][reset][truncate][validate]",
    "", [
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);
$only = $options['only'] ? ['class_filter' => explode(',', $options['only'])] : [];
$only['validate'] = $options['validate'];

if ($options['reset']){
    OCMigrationSpreadsheet::resetCurrentStatus();
}

if ($options['truncate']){
    OCMigration::createTableIfNeeded($cli, true);
    OCMigration::createPayloadTableIfNeeded($cli, true);
}

try {
    $executionInfo = OCMigrationSpreadsheet::instance()->pull($options['verbose'] ? $cli: null, $only);
}catch (Throwable $e){
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

$executionInfo['errors_count'] = count($executionInfo['errors']);
unset($executionInfo['errors']);
print_r($executionInfo);

$script->shutdown();