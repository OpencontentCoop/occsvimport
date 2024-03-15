<?php

use Opencontent\Google\GoogleSheet;
use Opencontent\Google\GoogleSheetClient;
use League\HTMLToMarkdown\HtmlConverter;

class OCTrasparenzaSpreadsheet extends eZPersistentObject
{
    private static $dataFields;

    private static $dataConverters;

    private static $vocabularies;

    public static function definition()
    {
        return [
            "fields" => static::getFieldsDefinition(),
            'keys' => ['remote_id'],
            'class_name' => 'OCTrasparenzaSpreadsheet',
            'name' => 'ocsynctrasparenza',
            'function_attributes' => ['check' => 'getCheck',],
        ];
    }

    public function getCheck()
    {
        return json_decode($this->attribute('check_data'), true);
    }

    protected static function getFieldsDefinition(): array
    {
        $fields = [];
        foreach (self::getDataFields() as $identifier => $name) {
            $fields[$identifier] = [
                'name' => $identifier,
                'datatype' => 'string',
                'default' => '',
                'required' => false,
            ];
        }
        $fields['index'] = [
            'name' => 'index',
            'datatype' => 'integer',
            'default' => 0,
            'required' => true,
        ];
        $fields['remote_id'] = [
            'name' => 'remote_id',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];
        $fields['tree'] = [
            'name' => 'tree',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];
        $fields['parent_remote_id'] = [
            'name' => 'parent_remote_id',
            'datatype' => 'string',
            'default' => '',
            'required' => true,
        ];
        $fields['update_at'] = [
            'name' => 'update_at',
            'datatype' => 'integer',
            'default' => 0,
            'required' => false,
        ];
        $fields['check_data'] = [
            'name' => 'check_data',
            'datatype' => 'string',
            'default' => '[]',
            'required' => false,
        ];
        $fields['created_at'] = [
            'name' => 'created_at',
            'datatype' => 'integer',
            'default' => time(),
            'required' => false,
        ];

        return $fields;
    }

    private static function initDb()
    {
        $db = eZDB::instance();
        eZDB::setErrorHandling(eZDB::ERROR_HANDLING_EXCEPTIONS);
        $tableQuery = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'ocsynctrasparenza';";
        $exists = array_fill_keys(array_column($db->arrayQuery($tableQuery), 'tablename'), true);
        if (empty($exists)) {
            $fieldQuery = '';
            foreach (self::getDataFields() as $field => $name) {
                $fieldQuery .= "$field text,";
            }
            $tableCreateSql = <<< EOT
CREATE TABLE ocsynctrasparenza (  
  index INTEGER NOT NULL,
  remote_id text NOT NULL,
  parent_remote_id text NOT NULL,
  tree text NOT NULL,
  $fieldQuery
  update_at INTEGER DEFAULT 0,
  check_data json,
  created_at INTEGER DEFAULT 0 
);
ALTER TABLE ONLY ocsynctrasparenza ADD CONSTRAINT ocsynctrasparenza_pkey PRIMARY KEY (remote_id);
CREATE INDEX ocsynctrasparenza_remote_id ON ocsynctrasparenza USING btree (remote_id);
CREATE INDEX ocsynctrasparenza_parent_remote_id ON ocsynctrasparenza USING btree (parent_remote_id);
EOT;
            $db->query($tableCreateSql);
        } else {
            $db->query('TRUNCATE TABLE ocsynctrasparenza');
        }
    }

    private static $googleSheetClient;

    public static function instanceGoogleSheetClient(): GoogleSheetClient
    {
        if (self::$googleSheetClient === null) {
            self::$googleSheetClient = new OCMGoogleSheetClient();
        }

        return self::$googleSheetClient;
    }

    private static function instanceGoogleSheet($id): GoogleSheet
    {
        return new GoogleSheet($id, self::instanceGoogleSheetClient());
    }

    public static function setConnectedSpreadSheet($spreadsheetId)
    {
        $checkAccessSpreadsheet = self::instanceGoogleSheet($spreadsheetId);
        $siteData = eZSiteData::fetchByName('trasparenza_spreadsheet');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('trasparenza_spreadsheet', false);
        }
        $siteData->setAttribute('value', $spreadsheetId);
        $siteData->store();
        self::refresh();
    }

    public static function refresh()
    {
        eZDebug::writeDebug('Refresh data', __FILE__);
        self::initDb();
        $spreadsheet = self::instanceGoogleSheet(self::getConnectedSpreadSheet());
        $data = $spreadsheet->getSheetDataHash('Dati');
        self::writeData($data);
    }

    public static function check($remoteId, $remoteDataItem)
    {
        $check = [];
        try {
            $object = eZContentObject::fetchByRemoteID($remoteId);
            if (!$object instanceof eZContentObject) {
                throw new Exception("Object $remoteId not found");
            }
            $item = self::serializeObject($object);
            foreach (self::getDataFields() as $identifier => $callbacks) {
                $diff = (new eZDiffTextEngine())->createDifferenceObject(
                    $remoteDataItem[$identifier],
                    $item[$identifier]
                );
                $changeset = $diff->getChanges();
                $status = array_sum(array_column($changeset, 'status'));
                if ($status > 0) {
                    $check[$identifier] = 'danger';
                }
            }
        } catch (Throwable $e) {
            $check['error'] = $e->getMessage();
        }

        return json_encode($check);
    }

    public static function diff(string $remoteId)
    {
        $item = self::fetchByRemoteId($remoteId);
        return $item ? $item->getDiff() : [
            'locale' => [],
            'remote' => [],
            'check' => ['error' => 'Item not found'],
        ];
    }

    public function getDiff()
    {
        $item = self::serializeObject($this->getObject());
        return [
            'locale' => $this->unserializeItem($item, true),
            'remote' => $this->unserializeItem($this->toArray(), true),
            'check' => $this->getCheck(),
        ];
    }

    public static function serializeObject(
        eZContentObject $object,
        eZContentObject $parentObject = null,
        $treePrefix = ''
    ) {
        if (!$parentObject) {
            $parent = eZContentObjectTreeNode::fetch((int)$object->mainParentNodeID());
            $parentObjectRemoteId = $parent ? $parent->object()->remoteID() : '?';
        } else {
            $parentObjectRemoteId = $parentObject->remoteID();
        }
        $item = [
            'tree' => $treePrefix . $object->attribute('name'),
            'remote_id' => $object->remoteID(),
            'parent_remote_id' => $parentObjectRemoteId,
        ];
        $dataMap = $object->dataMap();
        foreach (self::getDataConverters() as $identifier => $callbacks) {
            $callback = $callbacks['fromAttribute'];
            $item[$identifier] = isset($dataMap[$identifier]) ? $callback($dataMap[$identifier]) : '';
        }
        return $item;
    }

    public static function fetchByRemoteId(string $remoteId)
    {
        return OCTrasparenzaSpreadsheet::fetchObject(
            OCTrasparenzaSpreadsheet::definition(),
            null,
            ['remote_id' => $remoteId]
        );
    }

    public static function syncItems($itemIdList = [], $fieldList = [])
    {
        $cond = empty($itemIdList) ? [] : [
            'remote_id' => [$itemIdList],
        ];
        /** @var self[] $items */
        $items = self::fetchObjectList(self::definition(), null, $cond, ['index' => 'asc']);
        foreach ($items as $item) {
            $item->syncContentObject($fieldList);
        }
    }

    public static function fetchObjectsWithCheck(): array
    {
        $cond = [
            'check_data::text' => ['<>', '[]'],
        ];
        /** @var OCTrasparenzaSpreadsheet[] $items */
        return OCTrasparenzaSpreadsheet::fetchObjectList(
            OCTrasparenzaSpreadsheet::definition(),
            null,
            $cond,
            ['index' => 'asc']
        );
    }

    public function toArray()
    {
        $data = [];
        foreach ($this->attributes() as $identifier) {
            $data[$identifier] = $this->attribute($identifier);
        }

        return $data;
    }

    public function getParentObject()
    {
        return eZContentObject::fetchByRemoteID($this->attribute('parent_remote_id'));
    }

    public function getObject()
    {
        return eZContentObject::fetchByRemoteID($this->attribute('remote_id'));
    }

    public function createContentObject()
    {
        $parentObject = $this->getParentObject();
        if (!$parentObject instanceof eZContentObject || isset($check['error'])) {
            throw new Exception('Parent object ' . $this->attribute('parent_remote_id') . ' not found');
        }
        $mainNode = $parentObject->mainNode();
        $subtreeByNodeId = $mainNode->subTree([
            'ClassFilterType' => 'include',
            'ClassFilterArray' => ['pagina_trasparenza'],
            'ObjectNameFilter' => $this->attribute('titolo'),
        ]);
        if (!empty($subtreeByNodeId)) {
            echo '<pre>';
            print_r($subtreeByNodeId);
            die();
        } else {
            $converters = self::getDataConverters();
            $attributes = [];
            foreach ($converters as $identifier => $converter) {
                $callback = $converter['toAttribute'];
                $attributes[$identifier] = $callback($this->attribute($identifier));
            }
            eZContentFunctions::createAndPublishObject([
                'parent_node_id' => $mainNode->attribute('node_id'),
                'remote_id' => $this->attribute('remote_id'),
                'class_identifier' => 'pagina_trasparenza',
                'attributes' => $attributes,
            ]);
            $this->setAttribute('check_data', self::check($this->attribute('remote_id'), $this->toArray()));
        }
    }

    public function unserializeItem(array $data, $asHtml = false): array
    {
        $converters = self::getDataConverters();
        $attributes = [];
        foreach ($data as $identifier => $value) {
            if (isset($converters[$identifier])) {
                $callback = $converters[$identifier]['toAttribute'];
                $attributes[$identifier] = $callback($value, $asHtml);
            }
        }
        return $attributes;
    }

    public function syncContentObject($fields = [])
    {
        try {
            $object = $this->getObject();
            $check = $this->getCheck();
            if (!$object instanceof eZContentObject || isset($check['error'])) {
                $this->createContentObject();
            } else {
                $converters = self::getDataConverters();
                $attributes = [];
                foreach ($check as $identifier => $diff) {
                    if (empty($fields) || (!empty($fields) && in_array($identifier, $fields))) {
                        $callback = $converters[$identifier]['toAttribute'];
                        $attributes[$identifier] = $callback($this->attribute($identifier));
                    }
                }
                if (!empty($attributes)) {
                    eZContentFunctions::updateAndPublishObject($object, ['attributes' => $attributes]);
                    eZContentObject::clearCache($object->attribute('id'));
                    $this->setAttribute('check_data', self::check($this->attribute('remote_id'), $this->toArray()));
                }
            }
        } catch (Throwable $e) {
            $this->setAttribute('check_data', json_encode(['error' => $e->getMessage()]));
        }
        $this->setAttribute('update_at', time());
        $this->store();
    }

    private static function convertToMarkdown(?string $html): string
    {
        if (!$html) {
            return '';
        }
        $converter = new HtmlConverter();
        return $converter->convert($html);
    }

    private static function writeData($data)
    {
        foreach ($data as $index => $item) {
            $item['index'] = $index;
            $item['check_data'] = self::check($item['remote_id'], $item);
            (new self($item))->store();
        }
    }

    public static function getDataFields(): array
    {
        if (self::$dataFields === null) {
            $class = eZContentClass::fetchByIdentifier('pagina_trasparenza');
            if (!$class instanceof eZContentClass) {
                throw new Exception("Class pagina_trasparenza not found");
            }
            self::$dataFields = [];
            foreach ($class->dataMap() as $identifier => $classAttribute) {
                if ($classAttribute->attribute('data_type_string') === eZPageType::DATA_TYPE_STRING) {
                    continue;
                }
                self::$dataFields[$identifier] = $classAttribute->attribute('name');
            }
        }

        return self::$dataFields;
    }

    public static function getVocabularies()
    {
        if (self::$vocabularies === null) {
            self::$vocabularies = [];
            $class = eZContentClass::fetchByIdentifier('pagina_trasparenza');
            if (!$class instanceof eZContentClass) {
                throw new Exception("Class pagina_trasparenza not found");
            }
            foreach ($class->dataMap() as $identifier => $classAttribute) {
                if ($classAttribute->attribute('data_type_string') === eZPageType::DATA_TYPE_STRING) {
                    continue;
                }
                if ($classAttribute->attribute('data_type_string') === eZSelectionType::DATA_TYPE_STRING) {
                    $vocabulary = array_column($classAttribute->content()['options'], 'name');
                    array_unshift($vocabulary, $identifier);
                    self::$vocabularies[] = $vocabulary;
                }
            }
        }

        return self::$vocabularies;
    }

    private static function getClassAttribute(string $id): ?eZContentClassAttribute
    {
        $class = eZContentClass::fetchByIdentifier('pagina_trasparenza');
        foreach ($class->dataMap() as $identifier => $classAttribute) {
            if ($identifier === $id) {
                return $classAttribute;
            }
        }

        return null;
    }

    private static $appendToVoc = [];

    private static function appendOptionToVocabulary(eZContentClassAttribute $classAttribute, $optionValue)
    {
        if (isset(self::$appendToVoc[$classAttribute->attribute('id') . '-' . $optionValue])) {
            return;
        }
        /** @var array $attributeContent */
        $attributeContent = $classAttribute->content();
        $currentOptions = $attributeContent['options'];

        $currentCount = 0;
        foreach ($currentOptions as $option) {
            $currentCount = max($currentCount, $option['id']);
        }
        $currentCount += 1;
        $currentOptions[] = [
            'id' => $currentCount,
            'name' => $optionValue,
        ];
        $doc = new DOMDocument('1.0', 'utf-8');
        $root = $doc->createElement("ezselection");
        $doc->appendChild($root);
        $options = $doc->createElement("options");
        $root->appendChild($options);
        foreach ($currentOptions as $optionArray) {
            unset($optionNode);
            $optionNode = $doc->createElement("option");
            $optionNode->setAttribute('id', $optionArray['id']);
            $optionNode->setAttribute('name', $optionArray['name']);
            $options->appendChild($optionNode);
        }
        $xml = $doc->saveXML();
        $classAttribute->setAttribute("data_text5", $xml);
        $classAttribute->store();
        self::$appendToVoc[$classAttribute->attribute('id') . '-' . $optionValue] = true;
    }

    public static function getDataConverters(): array
    {
        if (self::$dataConverters === null) {
            $class = eZContentClass::fetchByIdentifier('pagina_trasparenza');
            if (!$class instanceof eZContentClass) {
                throw new Exception("Class pagina_trasparenza not found");
            }
            self::$dataConverters = [];
            foreach ($class->dataMap() as $identifier => $classAttribute) {
                if ($classAttribute->attribute('data_type_string') === eZPageType::DATA_TYPE_STRING) {
                    continue;
                }
                if ($classAttribute->attribute('data_type_string') === eZSelectionType::DATA_TYPE_STRING) {
                    self::$dataConverters[$identifier] = [
                        'fromAttribute' => function (eZContentObjectAttribute $attribute) {
                            return implode(PHP_EOL, eZStringUtils::explodeStr($attribute->toString(), '|'));
                        },
                        'toAttribute' => function ($string) use ($identifier) {
                            $classAttribute = self::getClassAttribute($identifier);
                            if ($classAttribute) {
                                $vocabulary = array_column($classAttribute->content()['options'], 'name');
                                if (!in_array($string, $vocabulary)) {
                                    self::appendOptionToVocabulary($classAttribute, trim($string));
                                }
                            }
                            return trim($string);
                        },
                    ];
                } elseif ($classAttribute->attribute('data_type_string') === eZXMLTextType::DATA_TYPE_STRING) {
                    self::$dataConverters[$identifier] = [
                        'fromAttribute' => function (eZContentObjectAttribute $attribute) use ($identifier) {
                            $converter = new \Opencontent\Opendata\Api\AttributeConverter\EzXml(
                                'pagina_trasparenza',
                                $identifier
                            );
                            return self::convertToMarkdown($converter->get($attribute)['content']);
                        },
                        'toAttribute' => function ($string, $asHtml = false) {
                            $prsDwn = new Parsedown();
                            if ($asHtml) {
                                return $prsDwn->text($string);
                            }
                            return SQLIContentUtils::getRichContent($prsDwn->text($string));
                        },
                    ];
                } else {
                    self::$dataConverters[$identifier] = [
                        'fromAttribute' => function (eZContentObjectAttribute $attribute) {
                            return $attribute->toString();
                        },
                        'toAttribute' => function ($string) {
                            return $string;
                        },
                    ];
                }
            }
        }

        return self::$dataConverters;
    }

    public static function removeConnectedSpreadSheet()
    {
        $siteData = eZSiteData::fetchByName('trasparenza_spreadsheet');
        if ($siteData instanceof eZSiteData) {
            $siteData->remove();
        }
        self::initDb();
    }

    public static function getConnectedSpreadSheet()
    {
        $siteData = eZSiteData::fetchByName('trasparenza_spreadsheet');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('trasparenza_spreadsheet', false);
        }

        return $siteData->attribute('value');
    }

    public static function getConnectedSpreadSheetTitle()
    {
        $id = self::getConnectedSpreadSheet();
        $title = false;
        if ($id) {
            $spreadsheet = self::instanceGoogleSheet($id);
            $title = $spreadsheet->getTitle();
        }

        return $title;
    }

}