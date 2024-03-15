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
    "[sync][fields:][sheet:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$user = eZUser::fetchByName( 'admin' );
eZUser::setCurrentlyLoggedInUser( $user , $user->attribute( 'contentobject_id' ) );

$spreadsheetId = $options['sheet'];
OCTrasparenzaSpreadsheet::setConnectedSpreadSheet($spreadsheetId);

if ($options['sync']) {
    $fields = $options['fields'] ? explode(',', $options['fields']) : [];
    /** @var OCTrasparenzaSpreadsheet[] $items */
    $items = OCTrasparenzaSpreadsheet::fetchObjectsWithCheck();
    foreach ($items as $item){
        $cli->output('Sync ' . $item->attribute('tree'));
        $item->syncContentObject($fields);
    }
}

$script->shutdown();