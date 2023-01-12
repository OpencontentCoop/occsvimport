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
    "[only:]",
    "", [
    'only' => implode(',', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);
$only = $options['only'] ? ['class_filter' => explode(',', $options['only'])] : [];

try {
    $executionInfo = OCMigrationSpreadsheet::instance()->pull($options['verbose'] ? $cli: null, $only);
}catch (Throwable $e){
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

print_r($executionInfo);

$script->shutdown();