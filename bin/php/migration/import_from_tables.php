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
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);
$only = $options['only'] ? ['class_filter' => explode(',', $options['only'])] : [];

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

try {
    $executionInfo = OCMigrationSpreadsheet::instance()->import($options['verbose'] ? $cli: null, $only);
}catch (Throwable $e){
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

print_r($executionInfo);

$script->shutdown();