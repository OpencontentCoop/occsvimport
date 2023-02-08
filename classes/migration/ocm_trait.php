<?php

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Rest\Client\PayloadBuilder;
use Opencontent\Opendata\Api\AttributeConverter\Base;
use Opencontent\Opendata\Api\AttributeConverterLoader;

trait ocm_trait
{
    protected static $parentNodes = [];

    protected function getOpencityFieldMapper(): array
    {
        return array_fill_keys(static::$fields, false);
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ocm_interface
    {
        return $this->fromNode($node, $this->getOpencityFieldMapper(), $options);
    }

    protected function getComunwebFieldMapper(): array
    {
        return array_fill_keys(static::$fields, false);
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        return $this->fromNode($node, $this->getComunwebFieldMapper(), $options);
    }

    protected function fromNode(eZContentObjectTreeNode $node, array $mapper, array $options = [])
    {
        $object = $node->object();
        $content = Content::createFromEzContentObject($object);
        $contentData = $content->data;
        $firstLocalizedContentData = [];
        $firstLocalizedContentLocale = 'ita-IT';
        foreach ($contentData as $locale => $data){
            $firstLocalizedContentData = $data;
            $firstLocalizedContentLocale = $locale;
            break;
        }

        foreach ($mapper as $identifier => $callFunction){
            if ($callFunction === false){
                $callFunction = OCMigration::getMapperHelper($identifier);
            }
            $this->setAttribute($identifier, call_user_func($callFunction,
                $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ));
        }
        if (!empty($mapper)) {
            $this->setAttribute('_id', $object->attribute('remote_id'));
        }
        $nodeUrl = $node->attribute('url_alias');
        eZURI::transformURI($nodeUrl, false, 'full');
        $this->setAttribute('_original_url', $nodeUrl);
        $this->setAttribute('_parent_name', $node->fetchParent()->attribute('url_alias'));

        return $this;
    }

    protected static $capabilities = [
        'import' => true,
        'export' => true,
        'pull' => true,
        'push' => true,
    ];

    public static function canImport(): bool
    {
        return self::$capabilities['import'];
    }

    public static function canPull(): bool
    {
        return self::$capabilities['pull'];
    }

    public static function canPush(): bool
    {
        return self::$capabilities['push'];
    }

    public static function canExport(): bool
    {
        return self::$capabilities['export'];
    }

    public static function enableImport(): void
    {
        self::$capabilities['import'] = true;
    }

    public static function disableImport(): void
    {
        self::$capabilities['import'] = false;
    }

    public function __construct($row = null)
    {
        $this->PersistentDataDirty = false;
        if (is_string($row)) {
            $row = $this->fetch($row, false);
        }
        $this->fill((array)$row);
    }

    protected static function getStringFieldDefinition($identifier): array
    {
        return [
            'name' => $identifier,
            'datatype' => 'string',
            'default' => '',
            'required' => false,
        ];
    }

    protected static function getFieldsDefinition(array $identifiers): array
    {
        $fields = [];
        foreach ($identifiers as $identifier) {
            $fields[$identifier] = static::getStringFieldDefinition($identifier);
        }
        $fields['_original_url'] = [
            'name' => '_original_url',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];
        $fields['_parent_name'] = [
            'name' => '_parent_name',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];
        $fields['_id'] = [
            'name' => '_id',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];

        return $fields;
    }

    public static function definition()
    {
        return [
            "fields" => static::getFieldsDefinition(static::$fields),
            'keys' => ['_id'],
            'class_name' => static::class,
            'name' => static::class,
        ];
    }

    protected function getNewPayloadBuilderInstance(): PayloadBuilder
    {
        return new PayloadBuilder();
    }

    protected function formatDate($value)
    {
        if (!empty($value) && strtotime($value)) {
            return date('c', strtotime($value));
        }

        return false;
    }

    protected function isEmptyArray(array $array): bool
    {
        foreach ($array as $value) {
            $trimmed = trim($value);
            if (!empty($trimmed)) {
                return false;
            }
        }

        return true;
    }

    protected function getNodeIdFromRemoteId($remoteId): int
    {
        if (isset(self::$parentNodes[$remoteId])) {
            return self::$parentNodes[$remoteId];
        }
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if (!$object instanceof eZContentObject) {
            throw new Exception("Object by remote $remoteId not found");
        }
        self::$parentNodes[$remoteId] = (int)$object->mainNodeID();

        return self::$parentNodes[$remoteId];
    }

    public function storeThis(bool $isUpdate): bool
    {
        $doStore = true;
        if ($isUpdate) {
            $item = $this->fetch($this->attribute('_id'));
            if ($item instanceof ocm_interface) {
                $doStore = false;
            }
        }
        if (empty($this->attribute('_id'))){
            $doStore = false;
        }
        if ($doStore) {
            eZPersistentObject::storeObject($this);
        }

        return $doStore;
    }

    public function fetch($id, $asObject = true)
    {
        $conditions = ['_id' => $id];
        return eZPersistentObject::fetchObject(
            static::definition(),
            null,
            $conditions,
            $asObject
        );
    }

    public function appendAttribute($name, $value, $separator = PHP_EOL)
    {
        $this->setAttribute($name, trim($this->attribute($value) . $separator . $value, $separator));
    }

    /**
     * @param eZContentObjectAttribute[] $dataMap
     * @param string $identifier
     * @return bool
     */
    protected static function hasAttributeStringContent(array $dataMap, string $identifier): bool
    {
        return isset($dataMap[$identifier])
            && $dataMap[$identifier]->hasContent()
            && !empty(trim($dataMap[$identifier]->toString()));
    }

    public static function removeById($id)
    {
        eZPersistentObject::removeObject(static::definition(), ["_id" => $id]);
    }
}