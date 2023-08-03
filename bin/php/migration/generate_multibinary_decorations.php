<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => ("Assign section to subtree"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions(
    '[file]',
    '',
    [
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);
$cli = eZClI::instance();

$idList = [];

try {
    $user = eZUser::fetchByName('admin');
    eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

    $classAttributeList = eZContentClassAttribute::fetchFilteredList([
        OCMultiBinaryType::ALLOW_DECORATIONS_FIELD => 1,
        'data_type_string' => OCMultiBinaryType::DATA_TYPE_STRING,
    ]);

    $classAttributes = [];
    foreach ($classAttributeList as $classAttribute) {
        $classIdentifier = eZContentClass::classIdentifierByID($classAttribute->attribute('contentclass_id'));
        $cli->output(
            $classIdentifier . '/' . $classAttribute->attribute('identifier')
        );
        $classAttributes[$classIdentifier][] = $classAttribute->attribute('identifier');
    }

    foreach ($classAttributes as $classIdentifier => $attributeIdentifiers){
        $class = eZContentClass::fetchByIdentifier($classIdentifier);
        $count = 0;
        $objectList = $class->objectList();
        foreach ($objectList as $object){
            $dataMap = $object->dataMap();
            foreach ($attributeIdentifiers as $attributeIdentifier){
                if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()){
                    $filePaths = explode('|', $dataMap[$attributeIdentifier]->toString());
                    if (empty($filePaths)) {
                        continue;
                    }
                    foreach ($filePaths as $stringItem) {
                        $filePathParts = explode('##', $stringItem);
                        if (
                            isset($filePathParts[2]) && !empty($filePathParts[2])
                            || isset($filePathParts[3]) && !empty($filePathParts[3])
                        ){
                            $count++;
                            $idList[$object->attribute('remote_id')][$attributeIdentifier][] = $filePathParts;
                        }
                    }
                }
            }
        }
        if ($count > 0){
            $cli->output($classIdentifier . ' -> ' . $count);
        }
    }

    if ($options['file']){
        file_put_contents( OpenPABase::getCurrentSiteaccessIdentifier() . '.ocm_md.json', json_encode($idList));
    }

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown($errCode, $e->getMessage());
}
