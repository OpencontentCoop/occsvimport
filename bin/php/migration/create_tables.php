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
    "[truncate][drop]",
    "",
    [
        'truncate' => 'truncate tables',
        'drop' => 'drop tables',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);
OCMigration::createTableIfNeeded($cli, $options['truncate'], $options['drop']);

$script->shutdown();