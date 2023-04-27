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
    $walker = new OCMWalker(function(eZContentObjectTreeNode $node, eZContentObjectTreeNode $parent) use ($data){
        $data[$parent->object()->remoteID()][] = $node->object()->remoteID();
    });
    if (!$object instanceof eZContentObject) {
        throw new Exception("Object $rootId not found");
    }
    if ($object->attribute('class_identifier') != 'trasparenza'){
        throw new Exception("Object is not trasparenza");
    }
    $walker->walk($object->mainNode());

    print_r($walker->getStats());
    $value = json_encode($data->getArrayCopy());
    if ($options['file']){
        file_put_contents( OpenPABase::getCurrentSiteaccessIdentifier() . '.ocm_t.json', $value);
    }
//    $cli->output($value);
    eZSiteData::create('ocm_trasparenza', $value)->store();

} catch (Throwable $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();


