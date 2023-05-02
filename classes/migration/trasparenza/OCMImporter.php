<?php

use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\EnvironmentLoader;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Rest\Client\HttpClient;
use Opencontent\Opendata\Rest\Client\PayloadBuilder;

class OCMImporter
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var HttpClient
     */
    private $readClient;

    /**
     * @var ContentRepository
     */
    private $repository;

    /**
     * @var EnvironmentSettings
     */
    private $environmentSettings;

    private $dryRun;

    private $dryRunValues = [];

    private $settings;

    private $stats = [
        'import' => 0,
        'add-location' => 0,
        'error' => 0,
    ];

    private static $relationsNodeId;

    private $recursion = 0;

    public function __construct($baseUrl, $user = null, $password = null, array $settings = [])
    {
        $this->baseUrl = $baseUrl;
        $client = new OCMNullClient();
        $readClient = new OCMNullClient();
        if ($user && $password){
            $client = new HttpClient($baseUrl, $user, $password, 'full');
            $readClient = new HttpClient($baseUrl);
        }
        $this->client = $client;
        $this->readClient = $readClient;
        $this->settings = array_merge_recursive(
            [
                'update-content' => false,
                'skip-classes' => [
                    'valuation',
                    'user_group',
                    'global_layout',
                    'shared_link',
                    'tipo_bando',
                ],
                'remap-classes' => [
                    'file_pdf' => 'file',
                    'area_tematica' => 'folder',
                    'homepage_interna' => 'folder',
                    'folder_lavoro' => 'folder',
                    'frontpage' => 'folder',
                    'pagina_sito' => 'folder',
                    'pagina_sito_trasparenza' => 'folder',
                    'politici' => 'folder',
                ],
                'default-values' => [
                    'image/license' => [
                        "Creative Commons Attribution 4.0 International (CC BY 4.0)",
                    ],
                    'image/author' => eZINI::instance()->variable('SiteSettings', 'SiteName'),
                ],
            ],
            $settings
        );

        $this->repository = new ContentRepository();
        $this->environmentSettings = EnvironmentLoader::loadPreset('content');
        $this->repository->setCurrentEnvironmentSettings($this->environmentSettings);
    }

    public function setAsDryRun(): void
    {
        $this->dryRun = true;
    }

    private function fetchByRemoteID($remoteId)
    {
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if (!$object instanceof eZContentObject) {
            $object = eZContentObject::fetchByRemoteID($this->prefixRemoteId($remoteId));
        }

        return $object;
    }

    private function prefixRemoteId($remoteId)
    {
        return 'dc_' . $remoteId;
    }

    public function walkTree($tree)
    {
        foreach ($tree as $parentId => $childrenIdList) {
            $parentObject = $this->fetchByRemoteID($parentId);

            if (!$parentObject instanceof eZContentObject && $this->dryRun && isset($this->dryRunValues[$parentId])) {
                $parentObject = $this->fetchByRemoteID($this->dryRunValues[$parentId]);
            }
            try {
                if (!$parentObject instanceof eZContentObject) {
                    throw new Exception("Contenitore $parentId non trovata");
                }

                if ($parentObject instanceof eZContentObject && !$parentObject->mainNodeID()) {
                    throw new Exception("Oggetto " . $parentObject->attribute('remote_id') . " senza main node");
                }

                $this->log($parentObject->attribute('name') . ' - ' . $parentId);

                foreach ($childrenIdList as $childRemoteId) {
                    $child = eZContentObject::fetchByRemoteID($childRemoteId);
                    if ($child instanceof eZContentObject) {
                        $this->recursion++;
                        try {
                            $this->addLocation($child, $parentObject);
                        } catch (Throwable $e) {
                            $this->error($e);
                        }
                        $this->recursion--;
                    } else {
                        $childAsDocumentoTrasparenza = eZContentObject::fetchByRemoteID(
                            $this->prefixRemoteId($childRemoteId)
                        );
                        if ($childAsDocumentoTrasparenza instanceof eZContentObject) {
                            continue;
                        }

                        $this->recursion++;
                        try {
                            $this->import($childRemoteId, $parentObject->mainNodeID());
                        } catch (Throwable $e) {
                            $this->error("Error importing {$childRemoteId}: " . $e->getMessage());
                        }
                        $this->recursion--;
                    }
                }
            } catch (Throwable $e) {
                $this->error($e);
            }
        }
    }

    private function addLocation($object, $parentObject)
    {
        if ($this->dryRun) {
            $this->stats['add-location']++;
            return true;
        }

        $addedLocation = false;
        if ($parentObject instanceof eZContentObject && $object instanceof eZContentObject) {
            if (!$object->mainNodeID()) {
                throw new Exception("Oggetto " . $object->attribute('remote_id') . " senza main node");
            }
            if (!$parentObject->mainNodeID()) {
                throw new Exception("Oggetto " . $parentObject->attribute('remote_id') . " senza main node");
            }

            $targetNodeId = $parentObject->mainNodeID();
            $assignedNodes = $object->assignedNodes(false);

            foreach ($assignedNodes as $assignedNode) {
                if ($assignedNode['parent_node_id'] == $targetNodeId) {
                    $this->debug(
                        "Already exists " . $object->attribute('remote_id') . " location in "
                        . $parentObject->attribute('remote_id')
                    );
                    return false;
                }
            }

            $this->log(
                "Add " . $object->attribute('remote_id') . " location to "
                . $parentObject->attribute('remote_id')
            );

            if (eZOperationHandler::operationIsAvailable('content_addlocation')) {
                eZOperationHandler::execute(
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
                eZContentOperationCollection::addAssignment(
                    $object->mainNodeID(),
                    $object->attribute('id'),
                    [$targetNodeId]
                );
            }
            $addedLocation = count($assignedNodes) < count($object->assignedNodes(false));
        }

        if ($addedLocation) {
            $this->stats['add-location']++;
        }

        return $addedLocation;
    }

    private function import($remoteObjectIdentifier, $parentNodeId, $withRelations = true): int
    {
        $parentNodeId = (int)$parentNodeId;
        if ($parentNodeId === 0) {
            throw new Exception("Parent node non trovato importando l'oggetto $remoteObjectIdentifier");
        }

        $this->readClient->read($remoteObjectIdentifier);

        $content = $this->client->read($remoteObjectIdentifier);

        $classIdentifier = $originalClassIdentifier = $content['metadata']['classIdentifier'];
        $remoteId = $content['metadata']['remoteId'];

        $localObject = $this->fetchByRemoteID($remoteId);
        if ($localObject instanceof eZContentObject && !$this->settings['update-content']) {
            $this->debug(
                'Already exists '
                . $classIdentifier . ' - '
                . $content['metadata']['name']['ita-IT'] . '  - '
                . $content['metadata']['remoteId']
            );
            return (int)$localObject->attribute('id');
        }

        if (in_array($classIdentifier, $this->settings['skip-classes'])) {
            $this->log(
                'Skip '
                . $classIdentifier . ' - '
                . $content['metadata']['name']['ita-IT'] . '  - '
                . $content['metadata']['remoteId']
            );
            return 0;
        }

        $isRemapped = false;
        if (isset($this->settings['remap-classes'][$classIdentifier])) {
            $classIdentifier = $this->settings['remap-classes'][$classIdentifier];
            $isRemapped = true;
        }

        $this->log(
            'Import '
            . $classIdentifier . ($isRemapped ? " (remapped from $originalClassIdentifier)" : '') . ' - '
            . $content['metadata']['name']['ita-IT'] . '  - '
            . $content['metadata']['remoteId'] . ' ' . ($withRelations ? '(with relations)' : '')
        );

        $contentClass = $this->importClassIfNeeded($classIdentifier);

        if ($this->dryRun) {
            $this->dryRunValues[$content['metadata']['remoteId']] = '5399ef12f98766b90f1804e5d52afd75';
            $this->stats['import']++;
            return 1;
        }

        $payload = new PayloadBuilder();
        $payload->setRemoteId($remoteObjectIdentifier);
        $payload->setClassIdentifier($classIdentifier);
        $payload->setParentNodes([$parentNodeId]);
        $payload->setLanguages($content['metadata']['languages']);
        $payload->setPublished($content['metadata']['published']);
        $payload->setStateIdentifier('moderation.accepted');

        /** @var eZContentClassAttribute[] $dataMap */
        $dataMap = $contentClass->dataMap();
        foreach ($content['data'] as $language => $datum) {
            foreach ($datum as $identifier => $field) {
                if (!isset($dataMap[$identifier])
                    || $dataMap[$identifier]->attribute('data_type_string') !== $field['datatype']) {
                    continue;
                }
                switch ($field['datatype']) {
                    case eZXMLTextType::DATA_TYPE_STRING:
                        if ($withRelations) {
                            $this->recursion++;
                            $field['data_text'] = $this->parseAndImportEmbedContents($field['data_text']);
                            $this->recursion--;
                            $payload->setData($language, $identifier, $field['data_text']);
                        } else {
                            $payload->setData($language, $identifier, $field['content']);
                        }
                        break;

                    case eZObjectRelationListType::DATA_TYPE_STRING:
                    case eZObjectRelationType::DATA_TYPE_STRING:
                        if ($withRelations) {
                            $data = [];
                            foreach ($field['content'] as $item) {
                                $this->recursion++;
                                $data[] = $this->import($item['remoteId'], $this->getRelationsNodeId(), false);
                                $this->recursion--;
                            }
                            $payload->setData($language, $identifier, $data);
                        }
                        break;

                    case eZURLType::DATA_TYPE_STRING:
                        $payload->setData($language, $identifier, $field['data_text']);
                        break;

                    case eZUserType::DATA_TYPE_STRING:
                        //skip
                        break;

                    default:
                        $isEmpty = empty($field['content']);
                        if (isset($field['content'][0]) && trim($field['content'][0]) === ''){
                            $isEmpty = true;
                        }
                        if (!$isEmpty) {
                            $payload->setData($language, $identifier, $field['content']);
                        }
                }
            }
        }

        foreach ($this->settings['default-values'] as $id => $value) {
            [$class, $identifier] = explode('/', $id);
            if ($classIdentifier == $class) {
                $payload->setData(null, $identifier, $value);
            }
        }

        foreach ($dataMap as $identifier => $classAttribute){
            $value = $payload->getData($identifier, 'ita-IT');
            if ($classAttribute->attribute('is_required') && empty($value)){
                switch ($classAttribute->attribute('data_type_string')){
                    case eZDateType::DATA_TYPE_STRING:
                    case eZDateTimeType::DATA_TYPE_STRING:
                        $payload->setData(null, $identifier, date('c', 86400));
                        break;

                    case eZBooleanType::DATA_TYPE_STRING:
                        $payload->setData(null, $identifier, 1);
                        break;

                    case eZObjectRelationListType::DATA_TYPE_STRING:
                    case eZObjectRelationType::DATA_TYPE_STRING:
                        $payload->setData(null, $identifier, [OpenPaFunctionCollection::fetchHome()->attribute('contentobject_id')]);
                        break;

                    default:
                        $payload->setData(null, $identifier, '[...]');
                }
            }
        }

        try {
            $createStruct = $this->environmentSettings->instanceCreateStruct($payload->getArrayCopy());
            $createStruct->validate();
        } catch (Exception $e) {
            print_r([
                $e->getMessage(),
                array_combine(
                    array_column($content['data']['ita-IT'], 'identifier'),
                    array_column($content['data']['ita-IT'], 'content')
                ),
                $payload->getArrayCopy(),
            ]);
            die();
        }

        $result = $this->repository->createUpdate($payload->getArrayCopy());

        $this->stats['import']++;

        return (int)$result['content']['metadata']['id'];
    }

    private function importClassIfNeeded($classIdentifier)
    {
        $localClass = eZContentClass::fetchByIdentifier($classIdentifier);
        if (!$localClass instanceof eZContentClass) {
            $remoteUrl = $this->baseUrl . '/classtools/definition/' . $classIdentifier;
            $remoteDefinition = json_decode(file_get_contents($remoteUrl), true);
            if (!$remoteDefinition) {
                throw new Exception('Remote class not found!');
            }
            $remoteDefinition = $this->fixNewContentClassDefinition($remoteDefinition);
            eZDir::mkdir('migration/classes', false, true);
            file_put_contents('migration/classes/' . $classIdentifier, json_encode($remoteDefinition));

            if (!$this->dryRun) {
                $classTool = new OCClassTools($classIdentifier, true, [], 'migration/classes/' . $classIdentifier);
                $classTool->sync();
            }
            if (!isset($this->dryRunValues['classes'][$classIdentifier])) {
                $this->warning("Import class $classIdentifier");
                $this->dryRunValues['classes'][$classIdentifier] = true;
            }

            $localClass = eZContentClass::fetchByIdentifier($classIdentifier);
        }

        return $localClass;
    }

    private function fixNewContentClassDefinition($remoteDefinition)
    {
        foreach ($remoteDefinition['DataMap'] as $index => $fields) {
            foreach ($fields as $identifier => $field) {
                if ($field['DataTypeString'] === eZObjectRelationListType::DATA_TYPE_STRING) {
                    $dataText5 = $field['DataText5'];
                    $dataType = new eZObjectRelationListType();
                    $doc = $dataType->parseXML($dataText5);
                    $structure = $dataType->createClassContentStructure($doc);
                    if (isset($structure['default_placement']['node_id'])) {
                        $structure['default_placement']['node_id'] = $this->getRelationsNodeId();
                        $doc = $dataType->createClassDOMDocument($structure);
                        $remoteDefinition['DataMap'][$index][$identifier]['DataText5'] =
                            eZObjectRelationListType::domString($doc);
                    }
                }
            }
        }
        $remoteDefinition['InGroups'] = [['GroupName' => 'Amministrazione trasparente']];
        return $remoteDefinition;
    }

    private function parseAndImportEmbedContents($text)
    {
        $outputHandler = new eZXHTMLXMLOutput(
            $text, false
        );

        $outputHandler->Document = new DOMDocument('1.0', 'utf-8');
        $success = $outputHandler->Document->loadXML($text);
        if (!$success) {
            return $text;
        }

        $linkRelatedObjectIDArray = $outputHandler->getAttributeValueArray('link', 'object_id');
        $linkNodeIDArray = $outputHandler->getAttributeValueArray('link', 'node_id');
        $objectRelatedObjectIDArray = $outputHandler->getAttributeValueArray('object', 'id');
        $embedRelatedObjectIDArray = $outputHandler->getAttributeValueArray('embed', 'object_id');
        $embedInlineRelatedObjectIDArray = $outputHandler->getAttributeValueArray('embed-inline', 'object_id');
        $embedNodeIDArray = $outputHandler->getAttributeValueArray('embed', 'node_id');
        $embedInlineNodeIDArray = $outputHandler->getAttributeValueArray('embed-inline', 'node_id');

        $relatedObjectIDArray = array_merge(
            $linkRelatedObjectIDArray,
            $objectRelatedObjectIDArray,
            $embedRelatedObjectIDArray,
            $embedInlineRelatedObjectIDArray
        );
        $relatedObjectIDArray = array_unique($relatedObjectIDArray);

        $nodeIDArray = array_merge(
            $linkNodeIDArray,
            $embedNodeIDArray,
            $embedInlineNodeIDArray
        );
        $nodeIDArray = array_unique($nodeIDArray);

        if (count($relatedObjectIDArray) > 0 || count($nodeIDArray) > 0) {
            $mapObjects = [];
            $mapNodes = [];

            foreach ($relatedObjectIDArray as $relatedObjectID) {
                try {
                    $object = $this->client->read($relatedObjectID);
                    $localObjectID = $this->import($object['metadata']['remoteId'], $this->getRelationsNodeId(), false);
                    $mapObjects[$relatedObjectID] = $localObjectID;
                } catch (Throwable $e) {
                    $this->error($e);
                }
            }
            foreach ($nodeIDArray as $nodeID) {
                try {
                    $node = $this->client->browse($nodeID, 0);
                    $localObjectID = $this->import($node['remoteId'], $this->getRelationsNodeId(), false);
                    $localObject = eZContentObject::fetch($localObjectID);
                    if ($localObject instanceof eZContentObject) {
                        $mapNodes[$nodeID] = $localObject->mainNodeID();
                    }
                } catch (Throwable $e) {
                    $this->error($e);
                }
            }

            foreach ($mapObjects as $id => $newId) {
                $text = str_replace(
                    'object_id="' . $id . '"',
                    'object_id="' . $newId . '"',
                    $text
                );
            }
            foreach ($mapNodes as $id => $newId) {
                $text = str_replace(
                    'node_id="' . $id . '"',
                    'node_id="' . $newId . '"',
                    $text
                );
            }
        }

        return str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $text);
    }

    private function getRelationsNodeId(): int
    {
        if ($this->dryRun) {
            return 1;
        }

        if (!self::$relationsNodeId) {
            $remoteId = 'relazioni-trasparenza';
            $object = eZContentObject::fetchByRemoteID($remoteId);
            if (!$object instanceof eZContentObject) {
                $params = [
                    'remote_id' => $remoteId,
                    'parent_node_id' => 43,
                    'class_identifier' => 'folder',
                    'attributes' => [
                        'name' => 'Contenuti correlati trasparenza',
                    ],
                ];
                $object = eZContentFunctions::createAndPublishObject($params);
            }

            if (!$object instanceof eZContentObject) {
                throw new Exception('Fallita la creazione del contenitore relazioni');
            }

            self::$relationsNodeId = $object->mainNodeID();
        }

        return (int)self::$relationsNodeId;
    }

    private function log($message)
    {
        eZCLI::instance()->output($this->padMessage($message));
    }

    private function debug($message)
    {
        eZCLI::instance()->output($this->padMessage($message));
    }

    private function warning($message)
    {
        eZCLI::instance()->warning($this->padMessage($message));
    }

    private function error($message)
    {
        if ($message instanceof Throwable) {
            $message = $message->getMessage();
        }
        eZCLI::instance()->error($this->padMessage($message));
        $this->stats['error']++;
    }

    private function padMessage($message)
    {
        $prefix = $this->recursion > 0 ? str_pad(' ', $this->recursion * 2, "    ", STR_PAD_LEFT) . '|- ' : '';
        return $prefix . $message;
    }
}