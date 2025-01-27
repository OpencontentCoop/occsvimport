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
    "[file:][source:][id:][count][no-interaction]",
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

$client = new \Opencontent\Opendata\Rest\Client\HttpClient($options['source']);
//$client = new OCLocalHttpClient($options['source']);

$count = [];
$total = count($data);
$i = 0;
try {
    foreach ($data as $remoteId => $values) {
        $i++;
        if ($options['id'] && $remoteId != $options['id']) {
            continue;
        }
        $cli->warning("$i/$total" . ' ##### ' . $remoteId);
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if ($object instanceof eZContentObject) {
            try {
                $remoteData = $client->read($remoteId);
            } catch (Exception $e) {
                $cli->warning(' - ' . $e->getMessage());
                continue;
            }

            $payload = new \Opencontent\Opendata\Rest\Client\PayloadBuilder();
            $payload->setRemoteId($remoteId);
            $payload->setLanguages($remoteData['metadata']['languages']);
            $payload->setClassIdentifier($object->attribute('class_identifier'));

            $dataMap = $object->dataMap();
            foreach ($values as $identifier => $originalDecorations) {
                $cli->output(' - Attribute ' . $identifier);
                if (
                    isset($dataMap[$identifier])
                    && $dataMap[$identifier]->attribute('data_type_string') == OCMultiBinaryType::DATA_TYPE_STRING
                    && $dataMap[$identifier]->contentClassAttribute()->attribute(OCMultiBinaryType::ALLOW_DECORATIONS_FIELD) == 1
                    && $dataMap[$identifier]->hasContent()
                ) {
                    if ($options['count']) {
                        $count[$object->attribute('class_identifier')]++;
                        continue;
                    }
//                    print_r($originalDecorations);

//                    $files = $dataMap[$identifier]->content();
//                    print_r($files);

                    foreach ($remoteData['metadata']['languages'] as $locale) {
                        $fieldData = $remoteData['data'][$locale][$identifier];
                        foreach ($fieldData as $j => $item) {
                            $itemUrlParts = explode('/file/', $item['url']);
                            $itemUrl = $itemUrlParts[0];
                            $fieldData[$j]['filename'] = basename($itemUrl);
                        }
                        $payload->setData($locale, $identifier, $fieldData);
                    }
                } else {
                    $cli->error("     attribute $identifier not found or empty or decoration disabled");
                }
            }

            if (!empty($payload->getData()) && !$options['count']) {
                if (!$options['no-interaction']) {
                    $question = ezcConsoleQuestionDialog::YesNoQuestion(new ezcConsoleOutput(), "Store?", "n");
                    $store = ezcConsoleDialogViewer::displayDialog($question) == "y";
                } else {
                    $store = true;
                }
                if ($store) {
                    $repo = new \Opencontent\Opendata\Api\ContentRepository();
                    $currentEnvironment = \Opencontent\Opendata\Api\EnvironmentLoader::loadPreset('content');
                    $repo->setEnvironment($currentEnvironment);
                    $parser = new ezpRestHttpRequestParser();
                    $request = $parser->createRequest();
                    $currentEnvironment->__set('request', $request);
                    try {
                        $data = $repo->update($payload->getArrayCopy());
                        if ($options['id']) {
                            print_r($payload->getArrayCopy());
                        }
                    } catch (Exception $e) {
                        if ($options['id']) {
                            print_r($payload->getArrayCopy());
                        }
                        $cli->error($e->getMessage());
                        $cli->output($e->getTraceAsString());
                    }
                }
            }
        } else {
            $cli->error(' content not found');
        }
    }

    if ($options['count']) {
        print_r($count);
    }
} catch (Throwable $e) {
    $cli->error($e->getMessage());
    $cli->output($e->getTraceAsString());
}


$script->shutdown();


