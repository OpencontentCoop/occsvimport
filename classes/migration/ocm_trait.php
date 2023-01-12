<?php

use Opencontent\Opendata\Rest\Client\PayloadBuilder;
use Opencontent\Opendata\Api\AttributeConverter\Base;
use Opencontent\Opendata\Api\AttributeConverterLoader;

trait ocm_trait
{
    protected static $parentNodes = [];

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        return $this->internalFromOpencityNode($node, $options);
    }

    protected function internalFromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $object = $node->object();
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        $this->setAttribute('_id', $object->attribute('remote_id'));
        $alreadyDone = [];
        foreach (static::$fields as $identifier) {
            [$id] = explode('___', $identifier);
            if (isset($alreadyDone[$id])) {
                continue;
            }
            $alreadyDone[$id] = true;
            $data = static::getAttributeString($id, $dataMap, $options);
            foreach ($data as $name => $value) {
                $this->setAttribute($name, $value);
            }
        }

        return $this;
    }

    /**
     * @param string $attributeIdentifier
     * @param eZContentObjectAttribute[] $dataMap
     * @return array
     * @throws Exception
     */
    protected static function getAttributeString($attributeIdentifier, $dataMap, $options = []): array
    {
        $locale = 'ita-IT';  //@todo language

        $options = array_merge([
            'matrix_converter' => 'multiline',
        ], $options);
        if (isset($dataMap[$attributeIdentifier])) {
            $attribute = $dataMap[$attributeIdentifier];
            $converter = AttributeConverterLoader::load(
                $attribute->attribute('object')->attribute('class_identifier'),
                $attribute->attribute('contentclass_attribute_identifier'),
                $attribute->attribute('data_type_string')
            );
            $content = $converter->get($attribute);
            $contentValue = $content['content'];
            switch ($attribute->attribute('data_type_string')) {
                case eZObjectRelationListType::DATA_TYPE_STRING:
                {
                    $data = [];
                    // @todo validate topics
//                    if ($attributeIdentifier === 'topics') {
//                        if ($attribute->hasContent()) {
//                            $idList = explode('-', $attribute->toString());
//                            $objects = OpenPABase::fetchObjects($idList);
//                            foreach ($objects as $object) {
//                                if ($object->mainNode()->childrenCount() == 0) {
//                                    $data[] = $object->attribute('name');
//                                }
//                            }
//                        }
//                    } else {
                    foreach ($contentValue as $metadata) {
                        $data[] = isset($metadata['name']) ? $metadata['name'][$locale] : $metadata['metadata']['name'][$locale];
                    }
//                    }
                    return [
                        $attributeIdentifier => implode(PHP_EOL, $data),
                    ];
                }

                case eZDateType::DATA_TYPE_STRING:
                    return [$attributeIdentifier => $contentValue ? date('j/n/Y', strtotime($contentValue)) : ''];

                case eZBinaryFileType::DATA_TYPE_STRING:
                    $contentValue = $converter->toCSVString($contentValue, $locale);
                    if (!empty($contentValue)){
                        $parts = explode('/', $contentValue);
                        $name = array_pop($parts);
                        $parts[] = urlencode($name);
                        return [$attributeIdentifier => implode('/', $parts)];
                    }
                    return [$attributeIdentifier => ''];

                case OCMultiBinaryType::DATA_TYPE_STRING:
                    if (!empty($contentValue)){
                        $files = [];
                        foreach ($contentValue as $file) {
                            $fileParts = explode('/', $file['url']);
                            $fileName = array_pop($fileParts);
                            $fileParts[] = urlencode($fileName);
                            $files[] = implode('/', $fileParts);
                        }
                        return [$attributeIdentifier => implode(PHP_EOL, $files)];
                    }
                    return [$attributeIdentifier => ''];

                case eZImageType::DATA_TYPE_STRING:
                    $url = $contentValue['url'];
                    return [
                        $attributeIdentifier . '___name' => $contentValue ? $contentValue['filename'] : '',
                        $attributeIdentifier . '___url' => $contentValue ? $contentValue['url'] : '',
                    ];

                case eZDateTimeType::DATA_TYPE_STRING:
                    return [$attributeIdentifier => $contentValue ? date('j/n/Y H:i', strtotime($contentValue)) : ''];

                case eZXMLTextType::DATA_TYPE_STRING:
                    return [$attributeIdentifier => $contentValue];

                case eZGmapLocationType::DATA_TYPE_STRING:
                    return [$attributeIdentifier => json_encode($contentValue)];

                case eZMatrixType::DATA_TYPE_STRING:
                {
                    if (is_callable($options['matrix_converter'])) {
                        return (array)call_user_func($options['matrix_converter'], $attribute, $converter);
                    } elseif ($options['matrix_converter'] === 'json') {
                        return [$attributeIdentifier => json_encode($contentValue)];
                    } elseif ($options['matrix_converter'] === 'multiline') {
                        $structure = self::getMatrixStructure($attribute->attribute('contentclass_attribute'));
                        $data = [];
                        foreach (array_keys($structure) as $s) {
                            $data[$attributeIdentifier . '___' . $s] = $converter->toCSVString($contentValue, $s);
                        }
                        return $data;
                    }
                }

                default:
                    return [$attributeIdentifier => $converter->toCSVString($contentValue, $locale)];
            }
        }

        return [];
    }

    protected static function getMatrixStructure(eZContentClassAttribute $attribute): array
    {
        /** @var \eZMatrixDefinition $definition */
        $definition = $attribute->attribute('content');
        $columns = $definition->attribute('columns');
        $format = [];
        foreach ($columns as $column) {
            $format[$column['identifier']] = $column['name'];
        }
        return $format;
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
            if ($item instanceof ocm_interface){
                $doStore = false;
            }
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
}