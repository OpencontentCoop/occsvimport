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
    "",
    [
        'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$classFilter = $options['only'] ? explode(',', $options['only']) : [];

if (empty($classFilter)) {
    $cli->output('Update vocabolari');
    OCMigrationSpreadsheet::instance()->updateVocabolaries();

    $cli->output('Update istruzioni');
    OCMigrationSpreadsheet::instance()->updateGuide();
}

foreach (OCMigration::getAvailableClasses($classFilter) as $className) {
    $cli->output($className);
    try {
        OCMigrationSpreadsheet::instance()->updateHelper($className);
    } catch (Throwable $e) {
        $cli->error($e->getMessage());
        if ($options['verbose']) $cli->error($e->getTraceAsString());
    }
}

$script->shutdown();