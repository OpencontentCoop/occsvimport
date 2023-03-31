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
    "[only:][master:][dry-run]",
    "",
    [
        'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
        'master' => 'Custom master spreadsheet',
        'dry-run' => '',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$classFilter = $options['only'] ? explode(',', $options['only']) : [];
$master = $options['master'];

if (empty($classFilter) && !$master) {
    $cli->output('######### Update vocabolari #########');
    OCMigrationSpreadsheet::instance()->updateVocabolaries();
    $cli->output('######### Update istruzioni #########');
    OCMigrationSpreadsheet::instance()->updateGuide();
}
$classes = OCMigration::getAvailableClasses($classFilter);
foreach ($classes as $className) {
    if ($options['verbose']) $cli->output();
    if ($options['verbose']) $cli->output('##########################################################################');
    if ($options['verbose']) $cli->output('##########################################################################');
    $cli->output($className);
    if ($options['verbose']) $cli->output('##########################################################################');
    try {
        OCMigrationSpreadsheet::instance()->updateHelper($className, $master, $options['dry-run'], $options['verbose']);
    } catch (Throwable $e) {
        $cli->error($e->getMessage());
        if ($options['verbose']) $cli->error($e->getTraceAsString());
    }
    if ($options['verbose']) $cli->output();
    if ($options['verbose']) $cli->output();
    sleep(3);
}

$script->shutdown();