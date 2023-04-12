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
    "[only:][cleanup]",
    "", [
    'only' => 'Csv values from:' . PHP_EOL . ' ' . implode(PHP_EOL . ' ', OCMigration::getAvailableClasses()),
]);
$script->initialize();
$script->setUseDebugAccumulators(true);

if (OCMigration::discoverContext() !== false) {
    throw new Exception('Wrong context');
}

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$only = $options['only'] ? explode(',', $options['only']) : [];
$cleanup = $options['cleanup'];

function delete(eZContentObject $object, $moveToTrash = false)
{
    foreach ($object->assignedNodes() as $node) {
        $deleteIDArray[] = $node->attribute('node_id');
    }
    if (!empty($deleteIDArray)){
        if (eZOperationHandler::operationIsAvailable('content_delete')) {
            eZOperationHandler::execute('content',
                'delete',
                array(
                    'node_id_list' => $deleteIDArray,
                    'move_to_trash' => $moveToTrash
                ),
                null, true);
        } else {
            eZContentOperationCollection::deleteObject($deleteIDArray, $moveToTrash);
        }
    }
}

foreach (OCMigration::getAvailableClasses($only) as $className) {
    $cli->warning($className);
    /** @var OCMPersistentObject $className */
    /** @var OCMPersistentObject[] $items */
    $items = $className::fetchObjectList(
        $className::definition(),
        null,
        null,
        [$className::getSortField() => 'asc']
    );
    $itemCount = count($items);
    foreach ($items as $i => $item){
        $index = $i + 1;
        $remoteId = $item->id();
        $cli->output("$index/$itemCount #$remoteId ", false);
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if ($object instanceof eZContentObject){
            delete($object);
            $cli->output('ok');
            if ($cleanup) {
                $item->remove();
                try {
                    OCMPayload::fetch($remoteId)->remove();
                } catch (Exception $e) {
                }
            }
        }else{
            $cli->error('ko');
        }
    }
    $cli->output();
}

$script->shutdown();

