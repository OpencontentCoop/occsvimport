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
    "[url:][column:][class:][source:]"
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$sourceList = explode(PHP_EOL, file_get_contents($options['source']));
$url = rtrim($options['url'], '/');
$class = $options['class'];
$column = $options['column'];

$domain = parse_url($url, PHP_URL_HOST);

$list = [];
foreach ($sourceList as $source) {
    $endpoint = "https://$domain/api/ocm/v1/$class/$source/$column?format=text";
    $data = file_get_contents($endpoint);
    $cli->output($endpoint);
    $cli->output(' -> ' . $data);
    $list[] = $data;
    $cli->output();
}

$filename = $domain . '_' . $class . '_' . $column;
file_put_contents($filename, implode(PHP_EOL, $list));
$script->shutdown();