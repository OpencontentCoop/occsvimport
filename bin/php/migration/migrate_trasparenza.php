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
if (file_exists($treePath)) {
    $tree = json_decode(file_get_contents($treePath), true);
} elseif ($baseUrl) {
    $tree = json_decode(file_get_contents($baseUrl . '/api/ocm/v1/trasparenza'), true);
}
if ($baseUrl) {
    $client = new \Opencontent\Opendata\Rest\Client\HttpClient($baseUrl, $options['u'], $options['p'], 'full');
}
if (!$tree) {
    $cli->error('Missing tree data');
    $tree = [];
}

function addLocation(eZContentObject $object, $targetNodeId)
{
    $assignedNode = $object->assignedNodes(false);
    if (eZOperationHandler::operationIsAvailable('content_addlocation')) {
        $operationResult = eZOperationHandler::execute(
            'content',
            'addlocation',
            [
                'node_id' => $object->mainNodeID(),
                'object_id' => $object->attribute('id'),
                'select_node_id_array' => [$targetNodeId],
            ],
            null,
            true
        );
    } else {
        eZContentOperationCollection::addAssignment($object->mainNodeID(), $object->attribute('id'), [$targetNodeId]);
    }
    return count($assignedNode) < count($object->assignedNodes(false));
}

try {
    $repository = new \Opencontent\Opendata\Api\ContentRepository();
    $repository->setCurrentEnvironmentSettings(\Opencontent\Opendata\Api\EnvironmentLoader::loadPreset('content'));

    function stringify($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $values = [];
            foreach ($value as $v) {
                $values[] = stringify($v);
            }
            return implode(', ', $values);
        }

        return var_export($value, true);
    }

    $import = [];
    $addLocation = [];
    foreach ($tree as $parentId => $childrenIdList) {
        $parent = eZContentObject::fetchByRemoteID($parentId);
        if (!$parent instanceof eZContentObject){
            $parent = eZContentObject::fetchByRemoteID('dc_' . $parentId);
        }
        if (!$parent instanceof eZContentObject && !isset($import[$parentId])) {
            throw new Exception("Contenitore $parentId non trovata");
        }
        foreach ($childrenIdList as $childId) {
            $child = eZContentObject::fetchByRemoteID($childId);

            if ($child instanceof eZContentObject) {
                if (!$child->mainNodeID()) {
                    throw new Exception("Oggetto $childId senza main node");
                }

                $addLocation[$childId] = 1;
                if ($parent instanceof eZContentObject) {
                    if (addLocation($child, $parent->mainNodeID())) {
                        $cli->output("Add $childId location to $parentId if needed " . $child->attribute('id'));
                    }
                } else {
                    $cli->error("Parent location $parentId not found for content $childId");
                }
            } else {
                $childDc = eZContentObject::fetchByRemoteID('dc_' . $childId);
                if ($childDc instanceof eZContentObject) {
                    continue;
                }

                $cli->warning("Import $childId to $parentId ", !$client);
                $import[$childId] = 1;

                if ($client) {
                    try {
                        $content = $client->read($childId);
                    } catch (Throwable $e) {
                        $cli->error(' - ' . $e->getMessage());
                        continue;
                    }
                    $classDefinition = $client->request('GET', $baseUrl . '/api/opendata/v2/classes/' . $content['metadata']['classIdentifier']);
                    $identifiers = array_column($classDefinition['fields'], 'identifier');
                    $labels = array_column($classDefinition['fields'], 'name');
                    $fieldsLabels = array_combine($identifiers, $labels);

                    $cli->output(
                        ' - ' . $content['metadata']['classIdentifier'] . ' ' . $content['metadata']['name']['ita-IT']
                    );
                    $payload = new \Opencontent\Opendata\Rest\Client\PayloadBuilder();

                    $asIs = [
                        'link',
                        'nota_trasparenza',
                        'dataset_lotto',
                        'lotto',
                    ];

                    if (in_array('', $asIs)) {
                        $payload->setRemoteId($childId);
                        $payload->setClassIdentifier($content['metadata']['classIdentifier']);
                        foreach ($content['data'] as $language => $datum) {
                            foreach ($datum as $identifier => $field) {
                                $payload->setData($language, $identifier, $field['content']);
                            }
                        }
                    } else {

                        $blackListedIdentifiers = [];

                        $blackListedLabels = [
                            'Tipo di risposta',
                            'Visualizza elementi contenuti',
                        ];

                        $expireIdentifiers = [
                            'data_archiviazione',
                            'unpublish_date',
                            'data_finepubblicazione',
                            'unpublish_date'
                        ];

                        $info = [];
                        $files = [];
                        foreach ($content['data'] as $language => $datum) {
                            foreach ($datum as $identifier => $field) {
                                if (empty($field['content'])) {
                                    continue;
                                }
                                $label = $fieldsLabels[$identifier][$language] ?? $identifier;
                                switch ($field['datatype']) {
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
                                        $date = explode('-', $date);
                                        $date = array_reverse($date);
                                        $info[$language][$identifier] = implode('/', $date);

                                        if (in_array($identifier, $expireIdentifiers)){
                                            $payload->setData($language, $identifier, $field['content']);
                                        }

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

                                    case 'ocmultibinary':
                                        foreach ($field['content'] as $file){
                                            $file['group'] = $label;
                                            $files[$language][] = $file;
                                        }
                                        break;

                                    case 'ezmedia':
                                    case 'ezflowmedia':
                                    case 'ezbinaryfile':
                                        $files[$language][] = [
                                                'filename' => $field['content']['filename'],
                                                'url' => $field['content']['url'],
                                                'group' => $label,
                                            ];
                                        break;

                                    case 'ezimage':
                                        $files[$language][] = [
                                            'filename' => $field['content']['filename'],
                                            'url' => $baseUrl . $field['content']['url'],
                                            'group' => $label,
                                        ];
                                        break;
                                }

                                if (isset($info[$language][$identifier]) && empty($info[$language][$identifier])) {
                                    unset($info[$language][$identifier]);
                                }
                            }
                        }

                        $matrix = [];
                        $tempName = $content['metadata']['name'][$language] ?? '';
                        foreach ($info as $language => $values) {
                            $matrix[$language][] = [
                                'label' => 'Tipo',
                                'value' => $classDefinition['name'][$language] ?? $classDefinition['name']['ita-IT'],
                            ];
                            foreach ($values as $identifier => $value) {
                                if ($value === $tempName) {
                                    continue;
                                }

                                $label = $fieldsLabels[$identifier][$language] ?? $identifier;
                                if ($label === $identifier) {
                                    $label = $fieldsLabels[$identifier]['ita-IT'] ?? $identifier;
                                }
                                if (!in_array($label, $blackListedLabels) && !in_array($identifier, $blackListedIdentifiers)) {
                                    $matrix[$language][] = [
                                        'label' => $label,
                                        'value' => $value,
                                    ];
                                }
                            }
                        }

                        $payload->setRemoteId('dc_' . $childId);
                        $payload->setClassIdentifier('documento_trasparenza');
                        foreach ($content['metadata']['name'] as $l => $v) {
                            $payload->setData($l, 'title', $v);
                        }
                        foreach ($matrix as $l => $v) {
                            $payload->setData($l, 'info', $v);
                        }
                        foreach ($files as $l => $v){
                            $payload->setData($l, 'files', $v);
                        }
                    }

                    if ($parent instanceof eZContentObject) {
                        $payload->setParentNodes([$parent->mainNodeID()]);
                        $payload->setLanguages($content['metadata']['languages']);
                        $payload->setPublished($content['metadata']['published']);

                        $payload->setData('ita-IT', 'source', json_encode($content));

                        try {
                            $response = $repository->create($payload->getArrayCopy());
                            $cli->output(
                                ' - ' . $response['message'] . ' ' . $payload->getMetadaData('remoteId')
                            );
                        } catch (Throwable $e) {
                            $cli->error(' - ' . $e->getMessage());
                        }
                    } else {
                        $cli->error(' - Parent not found for content ' . $childId);
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


