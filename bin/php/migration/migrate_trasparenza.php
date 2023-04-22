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
    "[root:][file:][base_url:][u:][p:]",
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
$client = false;
if (file_exists($treePath)){
    $tree = json_decode(file_get_contents($treePath), true);
}elseif ($baseUrl){
    $tree = json_decode(file_get_contents($baseUrl . '/api/ocm/v1/trasparenza'), true);

}
if ($baseUrl){
    $client = new \Opencontent\Opendata\Rest\Client\HttpClient($baseUrl, $options['u'], $options['p'], 'full');
    $readClient = new \Opencontent\Opendata\Rest\Client\HttpClient($baseUrl, $options['u'], $options['p'], 'content');
}
if (!$tree){
    $cli->error('Missing tree data');
    $tree = [];
}

try {
    $repository = new \Opencontent\Opendata\Api\ContentRepository();
    $repository->setCurrentEnvironmentSettings(\Opencontent\Opendata\Api\EnvironmentLoader::loadPreset('content'));

    function stringify($value){
        if (is_string($value)){
            return $value;
        }

        if (is_array($value)){
            $values = [];
            foreach ($value as $v){
                $values[] = stringify($v);
            }
            return implode(', ', $values);
        }

        return var_export($value, true);
    }

    $import = [];
    $addLocation = [];
    foreach ($tree as $parentId => $childrenIdList){
        $parent = eZContentObject::fetchByRemoteID($parentId);
        if (!$parent instanceof eZContentObject && !isset($import[$parentId])){
            throw new Exception("Contenitore $parentId non trovata");
        }
        foreach ($childrenIdList as $childId){
            $child = eZContentObject::fetchByRemoteID($childId);
            if ($child instanceof eZContentObject){
                $cli->output("Add $childId location to $parentId");
                $addLocation[$childId] = 1;
            }else{
                $cli->warning("Import $childId to $parentId ", !$client);
                $import[$childId] = 1;

                if ($client){
                    $content = $client->read($childId);
                    $classDefinition = $readClient->read($childId)['metadata']['classDefinition'];
                    $identifiers = array_column($classDefinition['fields'], 'identifier');
                    $labels = array_column($classDefinition['fields'], 'name');
                    $fieldsLabels = array_combine($identifiers, $labels);

                    $cli->output($content['metadata']['classIdentifier'] . ' ' . $content['metadata']['name']['ita-IT']);

                    $info = [];
                    foreach ($content['data'] as $language => $datum){
                        foreach ($datum as $identifier => $field){
                            if (empty($field['content'])){
                                continue;
                            }
                            switch ($field['datatype']){
                                case 'ezboolean':
                                case 'ezemail':
                                case 'ezfloat':
                                case 'ezinteger':
                                case 'ezkeyword':
                                case 'ezprice':
                                case 'ezselection':
                                case 'ezstring':
                                case 'eztags':
                                case 'eztext':
                                case 'ezurl':
                                case 'eztime':
                                    $info[$language][$identifier] = stringify($field['content']);
                                    break;

                                case 'ezxmltext':
                                    $converter = new HtmlConverter();
                                    $info[$language][$identifier] = $converter->convert($field['content']);
                                    break;

                                case 'ezdate':
                                case 'ezdatetime':
                                    $date = explode('T', $field['content'])[0];
                                    $info[$language][$identifier] = $date;
                                    break;

                                case 'ezobjectrelation':
                                case 'ezobjectrelationlist':
                                    $values = array_column(array_column($field['content'], 'name'), $language);
                                    $info[$language][$identifier] = stringify($values);
                                    break;

                                case 'ezauthor':
                                    $values = array_column($field['content'], 'name');
                                    foreach ($values as $i => $v) {
                                        if ('Administrator User' == $v) {
                                            unset($values[$i]);
                                        }
                                    }
                                    $info[$language][$identifier] = stringify($values);
                                    break;

                                case 'ezmatrix':
                                    break;

                                case 'ezgmaplocation':
                                    $info[$language][$identifier] = $field['content']['address'] ?? '';
                                    break;

                                case 'ezbinaryfile':
                                case 'ezimage':
                                case 'ezmedia':
                                case 'ocmultibinary':
                                case 'ezflowmedia':
                                    $info[$language][$identifier] = '......';
                                    break;
                            }

                            if (isset($info[$language][$identifier]) && empty($info[$language][$identifier])){
                                unset($info[$language][$identifier]);
                            }
                        }
                    }

                    $matrix = [];
                    $tempName = $content['metadata']['name'][$language] ?? '';
                    foreach ($info as $language => $values){
                        foreach ($values as $identifier => $value){
                            if ($value === $tempName) continue;

                            $label = $fieldsLabels[$identifier][$language] ?? $identifier;
                            if ($label === $identifier){
                                $label = $fieldsLabels[$identifier]['ita-IT'] ?? $identifier;
                            }
                            $matrix[$language][] = [
                                'label' => $label,
                                'value' => $value
                            ];
                        }
                    }

                    if ($parent instanceof eZContentObject) {
                        $payload = new \Opencontent\Opendata\Rest\Client\PayloadBuilder();
                        $payload->setRemoteId($childId).
                        $payload->setClassIdentifier('documento_trasparenza');
                        $payload->setParentNodes([$parent->mainNodeID()]);
                        $payload->setLanguages($content['metadata']['languages']);
                        $payload->setPublished($content['metadata']['published']);
                        foreach ($content['metadata']['name'] as $l => $v) {
                            $payload->setData($l, 'title', $v);
                        }
                        foreach ($matrix as $l => $v) {
                            $payload->setData($l, 'info', $v);
                        }
                        $response = $repository->create($payload->getArrayCopy());
                        print_r($response['message'] . ' ' . $childId);die();
                    }else{
                        $cli->error('Parent not found for content ' . $childId);
                    }
                }
            }
        }
    }

    $cli->output('Add location: ' . count($addLocation));
    $cli->warning('Import: ' . count($import));

} catch (Throwable $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();


