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
$columns = explode(',', $options['column']);

$domain = parse_url($url, PHP_URL_HOST);

$list = [];
foreach ($sourceList as $index => $source) {
    foreach ($columns as $column) {
        $endpoint = "https://$domain/api/ocm/v1/$class/$source/$column?format=text";
        $data = file_get_contents($endpoint);
        $cli->output($endpoint);
        $cli->output(' -> ' . $data);
        $list[$index][$column] = $data;
    }
    $cli->output();
}

$csvFileName = $domain . '_' . $class . '_' . implode('-', $columns) . '.csv';

if (file_exists($csvFileName)) {
    unlink($csvFileName);
}
if (count($list) > 0) {
    $cli->output("[info] Generate file $csvFileName");
    $fp = fopen($csvFileName, 'w');
    fputcsv($fp, array_values($columns));
    foreach ($list as $row) {
        fputcsv($fp, array_values($row));
    }
}

$script->shutdown();