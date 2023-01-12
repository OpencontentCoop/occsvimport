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
$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);
OCMigration::createTableIfNeeded($cli);

$script->shutdown();