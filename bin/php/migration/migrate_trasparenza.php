<?php

require 'autoload.php';

use League\HTMLToMarkdown\HtmlConverter;

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();
$options = $script->getOptions(
    "[root:][file:][base_url:][u:][p:][dry-run][skip-all][update;]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$baseUrl = rtrim($options['base_url'], '/');
$treePath = $options['file'];
$tree = [];
if (file_exists($treePath)) {
    $tree = json_decode(file_get_contents($treePath), true);
} elseif ($baseUrl) {
    $tree = json_decode(file_get_contents($baseUrl . '/api/ocm/v1/trasparenza'), true);
}
if (!$tree) {
    $cli->error('Missing tree data');
    $tree = [];
}

$dryRun = $options['dry-run'];


$settings = [];
if ($options['skip-all']){
    $settings = [
        'skip-classes' => ['*']
    ];
}
if ($options['update']){
    $settings = [
        'update-content' => is_string($options['update']) ? 'interactive' : true
    ];
}
try {
    $importer = new OCMImporter($baseUrl, $options['u'], $options['p'], $settings);
    if ($options['dry-run']) {
        $importer->setAsDryRun();
    }
    $importer->walkTree($tree);
    print_r($importer->getStats());
} catch (Throwable $e) {
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

$script->shutdown();
