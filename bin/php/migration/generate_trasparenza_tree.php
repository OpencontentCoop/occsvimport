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
    "[root:][file]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

try {
    $rootId = $options['root'] ?? '5399ef12f98766b90f1804e5d52afd75';
    $object = eZContentObject::fetchByRemoteID($rootId);
    $data = new ArrayObject();
    $locations = new ArrayObject();
    $walker = new OCMWalker(function(eZContentObjectTreeNode $node, eZContentObjectTreeNode $parent) use ($data, $locations){
        $data[$parent->object()->remoteID()][] = $node->object()->remoteID();
        $locationIdList = [];
        foreach ($node->object()->assignedNodes() as $node){
            $locationIdList[] = $node->fetchParent()->object()->remoteID();
        }
        $locations[$node->object()->remoteID()] = $locationIdList;
    });
    if (!$object instanceof eZContentObject) {
        throw new Exception("Object $rootId not found");
    }
    if ($object->attribute('class_identifier') != 'trasparenza'){
        throw new Exception("Object is not trasparenza");
    }
    $walker->walk($object->mainNode());
    $stats = $walker->getStats();
    print_r($walker->getStats());
    if ($options['file']){
        eZDir::mkdir('migration');
        file_put_contents( 'migration/'. OpenPABase::getCurrentSiteaccessIdentifier() . '.ocm_t.json', json_encode($data->getArrayCopy()));
        file_put_contents( 'migration/stats_'. OpenPABase::getCurrentSiteaccessIdentifier() . '.ocm_t.json', json_encode($stats));
        file_put_contents( 'migration/locations_'. OpenPABase::getCurrentSiteaccessIdentifier() . '.ocm_t.json', json_encode($locations->getArrayCopy()));
    }
//    eZSiteData::create('ocm_trasparenza', json_encode([
//        'data' => $data->getArrayCopy(),
//        'stats' => $stats,
//        'locations' => $locations->getArrayCopy(),
//    ]))->store();


} catch (Throwable $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();


