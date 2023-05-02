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
    "[file:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$data = [];
$file = $options['file'];
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
}

try {
    foreach($data as $remoteId => $values){
        $cli->output($remoteId, false);
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if ($object instanceof eZContentObject){
            $dataMap = $object->dataMap();
            foreach ($values as $identifier => $value){
                if (isset($dataMap[$identifier])
                    && $dataMap[$identifier]->attribute('data_type_string') == OCMultiBinaryType::DATA_TYPE_STRING
                    && $dataMap[$identifier]->hasContent()){
                    $content = $dataMap[$identifier]->content();
                    print_r($content);
                    print_r($value);
                    die('todo');
                }else{
                    $cli->warning(" attribute $identifier not found or empty");
                }
            }
        }else{
            $cli->error(' content not found');
        }
    }
} catch (Throwable $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();


