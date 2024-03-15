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
    "[sync][fields:][sheet:][remotes:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$spreadsheetId = $options['sheet'];
OCTrasparenzaSpreadsheet::setConnectedSpreadSheet($spreadsheetId);

if ($options['sync']) {
    $fields = $options['fields'] ? explode(',', $options['fields']) : [];
    $remotes = $options['remotes'] ? explode(',', $options['remotes']) : [];
    if (count($remotes)) {
        OCTrasparenzaSpreadsheet::syncItems($remotes, $fields);
    } else {
        OCTrasparenzaSpreadsheet::syncAll($fields);
    }
}

$script->shutdown();