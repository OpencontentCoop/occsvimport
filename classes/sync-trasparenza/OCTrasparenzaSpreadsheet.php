<?php

use Opencontent\Google\GoogleSheet;
use Opencontent\Google\GoogleSheetClient;
use League\HTMLToMarkdown\HtmlConverter;

class OCTrasparenzaSpreadsheet extends eZPersistentObject
{
    private static $dataFields;

    private static $dataConverters;

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

            $dataMap = $object->dataMap();
            foreach (self::getDataConverters() as $identifier => $callbacks) {
                $fromAttribute = $callbacks['fromAttribute'];
                $locale = isset($dataMap[$identifier]) ? $fromAttribute($dataMap[$identifier]) : '';
                if ($locale !== $remoteDataItem[$identifier]) {
                    $check[$identifier] = 'danger';
                }
            }
        } catch (Throwable $e) {
            $check['error'] = $e->getMessage();
        }

        return json_encode($check);
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
        foreach ($this->attributes() as $identifier){
            $data[$identifier] = $this->attribute($identifier);
        }

        return $data;
    }

    public function createContentObject()
    {
        $parentObject = eZContentObject::fetchByRemoteID($this->attribute('parent_remote_id'));
        if (!$parentObject instanceof eZContentObject || isset($check['error'])) {
            throw new Exception('Parent object ' . $this->attribute('parent_remote_id') . ' not found');
        }
    }

    public function syncContentObject($fields = [])
    {
        try {
            $object = eZContentObject::fetchByRemoteID($this->attribute('remote_id'));
            $check = $this->getCheck();
            if (!$object instanceof eZContentObject || isset($check['error'])) {
                $this->createContentObject();
            } else {
                $converters = self::getDataConverters();
                $attributes = [];
                foreach ($check as $identifier => $level) {
                    if ($level === 'danger' && empty($fields) || (!empty($fields) && in_array($identifier, $fields))) {
                        $callback = $converters[$identifier]['toAttribute'];
                        $attributes[$identifier] = $callback($this->attribute($identifier));
                    }
                }
                if (!empty($attributes)){
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
                    self::$dataConverters[$identifier]['fromAttribute'] = function (eZContentObjectAttribute $attribute
                    ) {
                        return implode(PHP_EOL, eZStringUtils::explodeStr($attribute->toString(), '|'));
                    };
                } elseif ($classAttribute->attribute('data_type_string') === eZXMLTextType::DATA_TYPE_STRING) {
                    self::$dataConverters[$identifier] = [
                        'fromAttribute' => function (eZContentObjectAttribute $attribute) use ($identifier) {
                            $converter = new \Opencontent\Opendata\Api\AttributeConverter\EzXml(
                                'pagina_trasparenza',
                                $identifier
                            );
                            return self::convertToMarkdown($converter->get($attribute)['content']);
                        },
                        'toAttribute' => function ($string) {
                            $prsDwn = new Parsedown();
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