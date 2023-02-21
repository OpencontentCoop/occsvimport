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
    "[only:][sleep:]",
    "",
    [
        'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$classFilter = $options['only'] ? explode(',', $options['only']) : [];
$sleep = $options['sleep'] ?? false;
foreach (OCMigration::getAvailableClasses($classFilter) as $className) {
    $cli->output('Configure ' . $className::getSpreadsheetTitle() . '... ', false);
    try {
        $result = OCMigrationSpreadsheet::instance()->configureSheet($className);
        if ($result['errors'] > 0) {
            $cli->error($result['errors'] . ' error(s)');
        } else {
            $cli->output('ok');
        }
        if ($sleep) {
            $cli->output('(sleep ' . (int)$sleep . ')');
            sleep((int)$sleep);
        }
    } catch (Throwable $e) {
        $cli->error($e->getMessage());
    }
}


$script->shutdown();