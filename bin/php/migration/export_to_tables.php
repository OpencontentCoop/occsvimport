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
    "[only:][update][truncate][reset]",
    "", [
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

if ($options['reset']){
    OCMigrationSpreadsheet::resetCurrentStatus();
}

if ($options['truncate']){
    OCMigration::createTableIfNeeded($cli, true);
}

$opt = [
    'class_filter' => $options['only'] ? explode(',', $options['only']) : [],
    'update' => !!$options['update']
];

try {
    OCMigrationSpreadsheet::export($options['verbose'] ? $cli : null, $opt);
}catch (Throwable $e){
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

$script->shutdown();