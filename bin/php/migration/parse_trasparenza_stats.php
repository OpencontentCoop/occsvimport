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
    "[data:][filter:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();

$dir = $options['data'] ?? 'extension/occsvimport/data';
$classes = [];
$files = eZDir::findSubitems($dir, 'f');
foreach ($files as $file){
    if (strpos($file, 'stats_') === 0){
        $data = json_decode(file_get_contents($dir . '/' . $file), true);
        foreach ($data['classes'] as $class => $count){
            if (!isset($classes[$class])){
                $classes[$class] = 0;
            }
            if ($options['filter'] && $class === $options['filter']){
                $cli->output($file);
            }
            $classes[$class] += $count;
        }
    }
}

ksort($classes);
file_put_contents($dir . '/all_classes.json', json_encode($classes));

$script->shutdown();