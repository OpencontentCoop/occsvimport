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
    "[only:][master:]",
    "",
    [
        'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
        'master' => 'Custom master spreadsheet'
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$classFilter = $options['only'] ? explode(',', $options['only']) : [];
$master = $options['master'];

if (empty($classFilter) && !$master) {
    $cli->output('Update vocabolari');
    OCMigrationSpreadsheet::instance()->updateVocabolaries();

    $cli->output('Update istruzioni');
    OCMigrationSpreadsheet::instance()->updateGuide();
}

foreach (OCMigration::getAvailableClasses($classFilter) as $className) {
    $cli->output($className);
    try {
        OCMigrationSpreadsheet::instance()->updateHelper($className, $master);
    } catch (Throwable $e) {
        $cli->error($e->getMessage());
        if ($options['verbose']) $cli->error($e->getTraceAsString());
    }
}

$script->shutdown();