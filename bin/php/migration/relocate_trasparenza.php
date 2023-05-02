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
    "[root:][file:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$treePath = $options['file'];
$tree = [];
if (file_exists($treePath)) {
    $tree = json_decode(file_get_contents($treePath), true);
}else{
    $cli->error('Missing data');
}

try {
    foreach ($tree as $objectId => $locations){
        $cli->output($objectId);
        $object = eZContentObject::fetchByRemoteID($objectId);
        if ($object instanceof eZContentObject){
            if (count($locations) === 1){
                $parentId = $locations[0];
                $uniqueLocation = false;
                foreach ($object->assignedNodes() as $node){
                    if ($node->fetchParent()->object()->remoteID() === $parentId){
                        $uniqueLocation = $node->fetchParent()->attribute('remote_id');
                    }
                }
                if ($uniqueLocation){
                    $cli->output($uniqueLocation);
                    $contentRepository = new \Opencontent\Opendata\Api\ContentRepository();
                    $contentRepository->move($objectId, $uniqueLocation, true);
                    die();
                }else{
                    $cli->error(' - location not found');
                }
            }else{
                $cli->output(' - has multiple location');
            }
        }else{
            $cli->error(' - object not found');
        }
    }
} catch (Throwable $e) {
    $cli->error($e->getMessage());
    $cli->error($e->getTraceAsString());
}

$script->shutdown();
