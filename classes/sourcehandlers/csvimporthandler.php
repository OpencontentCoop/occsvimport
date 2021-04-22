<?php

use Opencontent\Opendata\Api\AttributeConverter\Tags;
use Opencontent\Opendata\Api\PublicationProcess;

class CSVImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    protected $rowIndex = 0;
    protected $rowCount;
    protected $currentGUID;

    /**
     * @var eZINI
     */
    protected $csvIni;

    /**
     * @var SQLICSVDoc
     */
    protected $doc;

    protected $classIdentifier;

    /**
     * @var eZContentClass
     */
    protected $contentClass;

    protected $countRow = 0;

    protected $errors = [];

    protected $language;

    //const REMOTE_IDENTIFIER = 'csvimport_';

    public function __construct(SQLIImportHandlerOptions $options = null)
    {
        parent::__construct($options);
        $this->options = $options;

        $this->language = $this->options->hasAttribute('language') ?
            $this->options->attribute('language') : eZContentObject::defaultLanguage();

        $this->csvIni = eZINI::instance('csvimport.ini');
        $this->classIdentifier = $this->options->attribute('class_identifier');
        $this->contentClass = eZContentClass::fetchByIdentifier($this->classIdentifier);

        if (!$this->contentClass) {
            $this->registerError("La class $this->classIdentifier non esiste.");
            die();
        }
    }

    protected function registerError($error)
    {
        $this->cli->error($error);
        if ($this->dataSource instanceof SQLICSVRowSet){
            $error = "#" . $this->dataSource->key() . ' ' . $error;
        }
        $this->errors[] = $error;
    }

    public function initialize()
    {
        $currentUser = eZUser::currentUser();
        $this->cli->warning('UserID #' . $currentUser->attribute('contentobject_id'));

        $csvOptions = new SQLICSVOptions(array(
            'csv_path' => $this->options->attribute('csv_path'),
            'delimiter' => $this->options->attribute('delimiter'),
            'enclosure' => $this->options->attribute('enclosure')
        ));
        $this->doc = new SQLICSVDoc($csvOptions);
        $this->doc->parse();
        $this->dataSource = $this->doc->rows;
    }

    public function getProcessLength()
    {
        return $this->dataSource->count();
    }

    public function getNextRow()
    {
        if ($this->dataSource->key() !== false) {
            $row = $this->dataSource->current();
            $this->dataSource->next();
        } else {
            $row = false;
        }

        return $row;
    }

    public function process($row)
    {
        try {
            $headers = $this->doc->rows->getHeaders();
            $rawHeaders = $this->doc->rows->getRawHeaders();

            $this->currentGUID = $row->{$headers[0]} . '_' . $this->classIdentifier;

            $pseudoLocations = array_keys($this->csvIni->variable('Settings', 'PseudoLocation'));

            $attributeArray = array();
            $attributeRepository = array();
            /** @var eZContentClassAttribute[] $attributes */
            $attributes = $this->contentClass->fetchAttributes();
            foreach ($attributes as $attribute) {
                $attributeArray[$attribute->attribute('identifier')] = $attribute->attribute('data_type_string');
                $attributeRepository[$attribute->attribute('identifier')] = $attribute;
            }

            $contentOptions = new SQLIContentOptions(array(
                'class_identifier' => $this->classIdentifier,
                'language' => $this->language
            ));

            if ($headers[0] == 'remoteId') {
                $remoteID = $this->generatePrefixedRemoteId($this->classIdentifier, $row->{$headers[0]});
                $contentOptions->__set('remote_id', $remoteID);
            }

            $content = SQLIContent::create($contentOptions);
            if (!isset($content->fields[$this->language])) {
                $content->addTranslation($this->language);
            }

            $i = 0;
            foreach ($headers as $key => $header) {

                $rawHeader = $rawHeaders[$key];

                //FIX per problematica array_key_exists che ritorna sempre false su prima colonna del CSV
                if ($i == 0) {
                    $rawHeader = $headers[0];
                }

                if (array_key_exists($rawHeader, $attributeArray)) {
                    switch ($attributeArray[$rawHeader]) {
                        case 'ezxmltext':
                            {
                                $content->fields->{$rawHeader} = $this->getRichContent($row->{$header});
                            }
                            break;

                        case 'ezobjectrelationlist':
                        case 'ezobjectrelation':
                            {
                                /** @var array $contentClassAttributeContent */
                                $contentClassAttributeContent = $attributeRepository[$rawHeader]->content();
                                $constraintList = isset($contentClassAttributeContent['class_constraint_list']) ?
                                    $contentClassAttributeContent['class_constraint_list'] : [];
                                $relationsNames = $row->{$header};
                                $content->fields->{$rawHeader} = $this->getRelations(
                                    $relationsNames,
                                    $constraintList
                                );
                            }
                            break;

                        case 'ezimage':
                            {
                                $content->fields->{$rawHeader} = $this->getImage($row->{$header});
                            }
                            break;

                        case 'ocmultibinary':
                            {
                                $content->fields->{$rawHeader} = $this->getFiles($row->{$header});
                            }
                            break;

                        case 'ezbinaryfile':
                        case 'ezmedia':
                            {
                                $content->fields->{$rawHeader} = $this->getFile($row->{$header});
                            }
                            break;

                        case 'ezdate':
                            {
                                $timestamp = $this->getTimestamp($row->{$header});
                                if (method_exists('eZTimestamp', 'getUtcTimestampFromLocalTimestamp')){
                                    $timestamp = eZTimestamp::getUtcTimestampFromLocalTimestamp($timestamp);
                                }
                                $content->fields->{$rawHeader} = $timestamp;
                            }
                            break;
                        case 'ezdatetime':
                            {
                                $content->fields->{$rawHeader} = $this->getTimestamp($row->{$header});
                            }
                            break;

                        case 'ezprice':
                            {
                                $content->fields->{$rawHeader} = $this->getPrice($row->{$header});
                            }
                            break;

                        case 'ezurl':
                            {
                                if (empty($row->{$header})) {
                                    $content->fields->{$rawHeader} = '|';
                                } else {
                                    $content->fields->{$rawHeader} = $row->{$header};
                                }
                            }
                            break;

                        case 'ezmatrix':
                            {
                                if (isset($this->options['incremental']) && $this->options['incremental'] == 1) {
                                    $content->fields->{$rawHeader} = $this->getIncrementalMatrix($contentOptions['remote_id'],
                                        $header, $row->{$header});
                                } else {
                                    $content->fields->{$rawHeader} = $row->{$header};
                                }
                            }
                            break;

                        case 'eztags':
                            {
                                $content->fields->{$rawHeader} = $this->getTags($row->{$header}, $attributeRepository[$rawHeader]);
                            }
                            break;

                        default:
                            {
                                $content->fields->{$rawHeader} = $row->{$header};
                            }
                            break;
                    }
                } else {
                    $doAction = false;
                    foreach ($pseudoLocations as $pseudo) {
                        if (strpos($rawHeader, $pseudo) !== false) {
                            $files = explode(',', $row->{$header});
                            array_walk($files, 'trim');

                            if (!empty($files) && $files[0] != '') {
                                $actionArray = explode('_', $rawHeader);
                                $action = array_shift($actionArray);
                                $doAction[$action][] = array(
                                    'attribute' => array_shift($actionArray),
                                    'class' => implode('_', $actionArray),
                                    'values' => $files
                                );
                            }
                        }
                    }
                }
                $i++;
            }

            $content->addLocation(SQLILocation::fromNodeID(intval($this->options->attribute('parent_node_id'))));
            $publisher = SQLIContentPublisher::getInstance();
            $publisher->publish($content);

            $newNodeID = $content->getRawContentObject()->attribute('main_node_id');
            unset($content);

            if ($doAction !== false) {
                foreach ((array)$doAction as $action => $values) {
                    $parameters = array(
                        'method' => 'make_' . $action,
                        'data' => $values,
                        'parent_node_id' => $this->options->attribute('parent_node_id'),
                        'this_node_id' => $newNodeID,
                        'guid' => $this->currentGUID,
                        'language' => $this->language,
                        'file_dir' => $this->options->hasAttribute('file_dir') ? $this->options->attribute('file_dir') : null
                    );
                    call_user_func_array(array('OCCSVImportHandler', 'call'), array('parameters' => $parameters));
                }
            }
        }catch (Exception $e){
            $this->registerError($e->getMessage());
        }
    }

    private function generatePrefixedRemoteId($classIdentifier, $name)
    {
        return $classIdentifier . '_' . $name;
    }

    protected function getRelations($relationsNames, $classes = array())
    {
        if (empty($relationsNames)) {
            return false;
        }
        $relations = array();
        $relationsNames = explode(',', $relationsNames);
        array_walk($relationsNames, 'trim');

        $classesIDs = array();
        $classesIdentifiers = array();
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $contentClass = eZContentClass::fetchByIdentifier($class);
                if ($contentClass) {
                    $classesIDs[] = $contentClass->attribute('id');
                    $classesIdentifiers[] = $contentClass->attribute('identifier');
                }
            }
        }

        foreach ($relationsNames as $name) {
            $relationByRemote = $this->getRelationByRemoteId($name, $classesIdentifiers);
            if ($relationByRemote) {
                $relations[] = $relationByRemote->attribute('id');
            } else {
                $searchResult = eZSearch::search(trim($name),
                    array(
                        'SearchContentClassID' => $classesIDs,
                        'SearchLimit' => 1
                    )
                );
                if ($searchResult['SearchCount'] > 0) {
                    $relations[] = $searchResult['SearchResult'][0]->attribute('contentobject_id');
                }
            }
        }

        if (!empty($relations)) {
            return implode('-', $relations);
        }

        return false;
    }

    private function getRelationByRemoteId($name, array $classIdentifierList)
    {
        $relationByRemote = eZContentObject::fetchByRemoteID($name);
        if ($relationByRemote instanceof eZContentObject) {
            return $relationByRemote;
        }

        foreach ($classIdentifierList as $classIdentifier) {
            $remoteID = $this->generatePrefixedRemoteId($classIdentifier, $name);
            $relationByRemote = eZContentObject::fetchByRemoteID($remoteID);
            if ($relationByRemote instanceof eZContentObject) {
                return $relationByRemote;
            }
        }

        return false;
    }

    protected function getImage($rowData)
    {
        $fileAndName = explode('|', $rowData);
        $file = $this->options->attribute('file_dir') . eZSys::fileSeparator() . $this->cleanFileName($fileAndName[0]);

        if (!is_dir($file)) {
            $name = '';
            if (isset($fileAndName[1])) {
                $name = $fileAndName[1];
            }

            $fileHandler = eZClusterFileHandler::instance($file);

            if ($fileHandler->exists()) {
                //$this->cli->notice( $file );
                return $file . '|' . $name;
            } else {
                $this->registerError($file . ' non trovato');
            }
        }

        return null;
    }

    protected function cleanFileName($fileName)
    {
        return OCCSVImportHandler::cleanFileName($fileName);
    }

    protected function getFiles($rowData)
    {
        $data = [];
        $files = explode('|', $rowData);
        foreach ($files as $file) {
            $data[] = $this->getFile($file);
        }

        return implode('|', $data);
    }

    protected function getFile($rowData)
    {
        $fileAndName = explode('|', $rowData);
        $file = $this->options->attribute('file_dir') . eZSys::fileSeparator() . $this->cleanFileName($fileAndName[0]);

        if (!is_dir($file)) {
            $name = '';
            if (isset($fileAndName[1])) {
                $name = $fileAndName[1];
            }

            $fileHandler = eZClusterFileHandler::instance($file);

            if ($fileHandler->exists()) {
                //$this->cli->notice( $file );
                return $file;
            } else {
                $this->registerError($file . ' non trovato');
            }
        }

        return null;
    }

    protected function getTimestamp($string)
    {
        if (empty($string)) {
            return null;
        }

        /*
         * Sostiutisco gli / con -
         * Dates in the m/d/y or d-m-y formats are disambiguated by looking at the separator between the various components:
         * if the separator is a slash (/), then the American m/d/y is assumed; whereas if the separator is a dash (-) or a dot (.),
         * then the European d-m-y format is assumed.
         */
        if (!is_numeric($string)) {
            $string = str_replace('/', '-', $string);
        } else {
            return $string;
        }

        $parts = explode('-', $string);

        if (mb_strlen($parts[2]) == 2) {
            $parts[2] = '20' . $parts[2];
        }
        $string = implode('-', $parts);

        if (($timestamp = strtotime($string)) !== false) {
            return $timestamp;
        }

        return null;
    }

    protected function getPrice($string)
    {
        $priceComponent = explode('|', $string);
        if (is_array($priceComponent) && count($priceComponent) == 3) {
            return $string;
        }
        $locale = eZLocale::instance();
        $data = $locale->internalCurrency($string);

        return $data . '|1|1';

    }

    protected function getIncrementalMatrix($remoteID, $attribute, $string)
    {
        $object = eZContentObject::fetchByRemoteID($remoteID);
        if (!$object instanceof eZContentObject) /*(empty($object->MainNodeID) || $object->Published == 0)*/ {
            return $string;
        }

        $dataMap = $object->dataMap();
        if (!isset($dataMap[$attribute])) {
            return $string;
        }

        return $dataMap[$attribute]->hasContent() ? $dataMap[$attribute]->toString() . '&' . $string : $string;
    }

    public function cleanup()
    {
        eZDir::recursiveDelete($this->options->attribute('file_dir'));

        return;
    }

    public function getHandlerName()
    {
        return $this->options->attribute('name');
    }

    public function getHandlerIdentifier()
    {
        return 'csvimportahandler';
    }

    public function getProgressionNotes()
    {
        $current = 'Current: ' . $this->currentGUID;
        if (count($this->errors)){
            $current .= "<br>Errors:<ul><li>" . implode("</li><li>", $this->errors) . '</li></ul>';
        }

        return $current;
    }

    protected function getTags($tagsString, eZContentClassAttribute $classAttribute)
    {
        if (empty($tagsString)){
            return '';
        }

        if (strpos($tagsString, '|#') !== false){
            return $tagsString;
        }

        $db = eZDB::instance();
        $tags = array_map('trim', explode(',', $tagsString));
        $tagIdList = [];
        foreach ($tags as $tag){
            $tag = $db->escapeString($tag);
            $rows = $db->arrayQuery("select keyword_id from eztags_keyword where keyword = '$tag' and locale = '{$this->language}'");
            $tagIdList = array_merge($tagIdList, array_column($rows, 'keyword_id'));
        }

        $parentTagId = (int)$classAttribute->attribute('data_int1');
        //@todo find parent

        $tagIds = array();
        $tagKeywords = array();
        $tagParents = array();
        $tagLanguages = array();
        foreach ($tagIdList as $tagId){
            $tagObject = eZTagsObject::fetch($tagId, $this->language);
            $tagIds[] = $tagObject->attribute('id');
            $tagKeywords[] = $tagObject->attribute('keyword');
            $tagParents[] = $tagObject->attribute('parent_id');
            $tagLanguages[] = $this->language;
        }

        $tagIds = implode('|#', $tagIds);
        $tagKeywords = implode('|#', $tagKeywords);
        $tagParents = implode('|#', $tagParents);
        $tagLanguages = implode('|#', $tagLanguages);

        return empty($tagIdList) ? '' : $tagIds . '|#' . $tagKeywords . '|#' . $tagParents . '|#' . $tagLanguages;
    }
}
