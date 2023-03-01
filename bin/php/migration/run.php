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
    "[action:][only:][update][reset]",
    "", [
        'action' => 'Select from:'  . PHP_EOL . ' '  . implode(PHP_EOL . ' ', ['export', 'push', 'pull', 'import']),
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

$opt = [
    'class_filter' => $options['only'] ? explode(',', $options['only']) : [],
    'update' => !!$options['update']
];

$action = $options['action'];
$runWithCli = $options['verbose'] ? $cli : null;
try {
    switch ($action){
        case 'export':
            OCMigrationSpreadsheet::export($runWithCli, $opt);
            break;
        case 'import':
            OCMigrationSpreadsheet::instance()->import($runWithCli, $opt);
            break;
        case 'pull':
            OCMigrationSpreadsheet::instance()->pull($runWithCli, $opt);
            break;
        case 'push':
            OCMigrationSpreadsheet::instance()->push($runWithCli, $opt);
            break;
    }

}catch (Throwable $e){
    OCMigrationSpreadsheet::setCurrentStatus($action, 'error', $opt, $e->getMessage());
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

$script->shutdown();