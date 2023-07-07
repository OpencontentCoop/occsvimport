<?php

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Rest\Client\PayloadBuilder;
use League\HTMLToMarkdown\HtmlConverter;

abstract class OCMPersistentObject extends eZPersistentObject implements ocm_interface
{
    public static $fields = [];

    protected static $parentNodes = [];

    public static function getSortField(): string
    {
        return 'name';
    }

    protected function getOpencityFieldMapper(): array
    {
        return array_fill_keys(static::$fields, false);
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
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

    protected function fromNode(eZContentObjectTreeNode $node, array $mapper, array $options = []): OCMPersistentObject
    {
        $object = $node->object();
        $content = Content::createFromEzContentObject($object);
        $contentData = $content->data;

        foreach ($mapper as $identifier => $callFunction) {
            if (is_numeric($identifier)) {
                throw new Exception(
                    "Invalid identifier $identifier " . var_export($callFunction, true) . ' in type ' . get_class($this)
                );
            }
            $attributeIdentifier = $identifier;

            // default ita
            $firstLocalizedContentLocale = 'ita-IT';
            $firstLocalizedContentData = $contentData[$firstLocalizedContentLocale] ?? [];;

            // se non c'è ita prende il primo che trova
            if (empty($firstLocalizedContentData)) {
                foreach ($contentData as $locale => $data) {
                    $firstLocalizedContentData = $data;
                    $firstLocalizedContentLocale = $locale;
                    break;
                }
            }

            // se è un campo de_ usa ger
            if (strpos($identifier, 'de_') === 0) {
                $firstLocalizedContentLocale = 'ger-DE';
                $firstLocalizedContentData = $contentData[$firstLocalizedContentLocale] ?? [];
                $attributeIdentifier = substr($identifier, 3);
            }

            if ($callFunction === false) {
                $callFunction = OCMigration::getMapperHelper($attributeIdentifier);
            }

            $this->setAttribute(
                $identifier,
                call_user_func(
                    $callFunction,
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                )
            );
        }
        if (!empty($mapper)) {
            $this->setAttribute('_id', $object->attribute('remote_id'));
        }
        $this->setNodeReference($node);

        return $this;
    }

    public function setNodeReference(eZContentObjectTreeNode $node)
    {
        $nodeUrl = $node->attribute('url_alias');
        eZURI::transformURI($nodeUrl, false, 'full');
        $nodeUrl = str_replace('http://', 'https://', $nodeUrl);
        $this->setAttribute('_original_url', $nodeUrl);
        $this->setAttribute('_parent_name', $node->fetchParent()->attribute('url_alias'));
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

    public static function checkPayloadGeneration(): bool
    {
        return true;
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

    public static function definition(): array
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
        return static::getDatePayload($value);
    }

    protected static function isEmptyArray(array $array): bool
    {
        return OCMigration::isEmptyArray($array);
    }

    protected static function trimArray(array $array): array
    {
        $trimmed = [];
        foreach ($array as $item) {
            $trimmed[] = trim($item);
        }

        return $trimmed;
    }

    /**
     * @param $remoteId
     * @return int
     * @throws Exception
     */
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
        if (empty($this->attribute('_id'))) {
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

    /**
     * @param string $field
     * @param string $value
     * @param $id
     * @param bool $asObject
     * @return ocm_interface
     */
    public static function instanceBy(string $field, string $value, $id, bool $asObject = true): ocm_interface
    {
        $conditions = [$field => trim($value)];
        $instance = eZPersistentObject::fetchObject(
            static::definition(),
            null,
            $conditions,
            $asObject
        );

        if (!$instance) {
            $instance = new static();
            $instance->setAttribute('_id', $id);
            $instance->setAttribute($field, trim($value));
        }

        return $instance;
    }

    public static function removeById($id)
    {
        eZPersistentObject::removeObject(static::definition(), ["_id" => $id]);
    }

    public static function getDateValidationHeaders(): array
    {
        return [];
    }

    public static function getRangeValidationHash(): array
    {
        return [];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [];
    }

    public static function getUrlValidationHeaders(): array
    {
        return [];
    }

    public static function getRangeRef(): array
    {
        return [
            'sheet' => static::getSpreadsheetTitle(),
            'column' => static::getColumnName(),
            'start' => 4,
        ];
    }

    public static function getVocabolaryRangeRef($identifier): array
    {
        $identifiers = [
            'argomenti' => 'Argomenti',
            'formati' => 'Formati',
            'licenze' => 'Licenze',
            'life-events' => 'Life Events',
            'business-events' => 'Business Events',
            'eventi' => 'Tematiche eventi',
            'documenti' => 'Tipologia di documento',
            'stagionalita' => 'Stagionalità',
            'organizzazioni' => 'Tipo di struttura organizzativa ',
            'luoghi' => 'Tipi di luogo',
            'contatti' => 'Tipologia di contatto',
            'tipi-contatto' => 'Tipo di contatto',
            'ruoli' => 'Ruoli',
            'incarichi' => 'Tipi di incarichi',
            'notizie' => 'Tipi di notizia',
            'organizzazioni-private' => "Tipi di organizzazione privata",
            'attivita' => "Tipo di attività",
            'content-type' => 'Tipi di contenuto',
            'popolazione' => 'Fasce generali di popolazione',
            'giuridica' => 'Forma giuridica',
            'service_status' => 'Stato del servizio',
            'service_category' => 'Categoria del servizio',
            'service_io' => 'Input-Output',
            'service_interaction' => 'Livello di interattività',
            'service_nace' => 'NACE',
            'service_auth' => 'Tipo di autenticazione',
            'service_channel' => 'Tipologia del canale del servizio',
            'service_procedure' => 'Tipologia di procedimento',
            'lingue' => 'Lingue',
        ];
        if (!isset($identifiers[$identifier])) {
            throw new Exception("Invalid voc identifier $identifier");
        }
        return [
            'sheet' => 'Vocabolari controllati',
            'column' => $identifiers[$identifier],
            'start' => 2,
        ];
    }

    public function convertToMarkdown(?string $html): string
    {
        if (!$html) {
            return '';
        }

        $converter = new HtmlConverter();
//        $converter->getEnvironment()->addConverter(new \League\HTMLToMarkdown\Converter\TableConverter());
        return $converter->convert($html);
    }

    protected static function fillNodeReferenceFromSpreadsheet(array $row, ocm_interface $item)
    {
        if (isset($row['Pagina contenitore'])) {
            $item->setAttribute('_parent_name', $row['Pagina contenitore']);
        }
        if (isset($row['Url originale'])) {
            $item->setAttribute('_original_url', $row['Url originale']);
        }
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        $data = [];
        $names = explode(PHP_EOL, $name);
        if (!self::isEmptyArray($names)) {
            $names = self::trimArray($names);
            if ($tryWithPrefix) {
                foreach ($names as $name) {
                    if (strpos($name, $tryWithPrefix) === false) {
                        $names[] = $tryWithPrefix . $name;
                    }
                }
            }

            $list = static::fetchObjectList(
                static::definition(), ['_id'],
                ['trim(' . $field . ')' => [$names]]
            );
            $data = array_column($list, '_id');
        }

        return $data;
    }

    public static function getIdByName($name, $field = 'name', string $tryWithPrefix = null): ?array
    {
        $data = static::getIdListByName($name, $field, $tryWithPrefix);
        if (count($data)) {
            return [$data[0]];
        }

        return null;
    }

    public static function getBooleanPayload(string $data)
    {
        if (empty($data)) {
            return null;
        }

        return !empty($data);
    }

    protected static function getDatePayload(string $data, $format = 'c')
    {
        if (empty($data)) {
            return null;
        }
        if (strpos($data, '-') !== false) {
            [$y, $m, $d] = explode('.', $data);
        } else {
            [$d, $m, $y] = explode('/', $data);
        }
        $timestamp = mktime(0, 0, 0, $m, $d, $y);

        return $format ? date($format, $timestamp) : $timestamp;
    }

    protected static function getDateTimePayload(string $data, $format = 'c')
    {
        if (empty($data)) {
            return null;
        }

        [$day, $hours] = explode(' ', trim($data));
        if (strpos($day, '-') !== false) {
            [$y, $m, $d] = explode('.', $day);
        } else {
            [$d, $m, $y] = explode('/', $day);
        }
        [$h, $min] = explode(':', $hours);
        $timestamp = mktime((int)$h, (int)$min, 0, (int)$m, (int)$d, (int)$y);

        return $format ? date($format, $timestamp) : $timestamp;
    }

    public function formatBinary(string $data, bool $isMultiple = true)
    {
        return static::getBinaryPayload($data, $isMultiple);
    }

    public function formatTags($name)
    {
        if (empty($name)) {
            return [];
        }

        $names = explode(PHP_EOL, $name);

        if (count($names) === 1 && strpos($names[0], ',') !== false){
            $filteredList = OCMigrationVocs::filterVocs($names);
            if (empty($filteredList)) {
                $withCommaTag = $names[0];
                $withCommaTag = str_replace(', ', '$', $withCommaTag);
                $names = explode(',', $withCommaTag);
                $names = array_map(function ($item){
                   return str_replace('$',', ', $item);
                }, $names);
            }
        }
        $names = explode(PHP_EOL, OCMigrationVocs::filterVocs($names));
        if (!self::isEmptyArray($names)) {
            return $names;
        }

        return [];
    }

    public function formatAuthor(string $name)
    {
        if (empty($name)) {
            return null;
        }

        $parts = explode(' ', $name);
        $email = array_pop($parts);

        return [[
            'name' => trim(implode(' ', $parts)),
            'email' => $email,
        ]];
    }

    protected static function getBinaryPayload(string $data, bool $isMultiple = true)
    {
        $values = [];
        if (empty($data)) {
            return $values;
        }
        $items = explode(PHP_EOL, $data);
        foreach ($items as $item) {
            $displayName = $group = $text = '';
            if (stripos($item, '#') !== false){
                $parts = explode('#', $item);
                $subParts = explode('|', $parts[1]);
                $displayName = $subParts[0];
                $group = $subParts[1] ?? '';
                $text = $subParts[2] ?? '';
                $item = $parts[0];
            }

            $filename = basename($item);
            $suffix = eZFile::suffix($filename);
            if ($suffix == $filename || strpos($suffix, '?') !== false) {
                $filename = self::getFileNameFromContentDisposition($item);
            }

            $values[] = [
                'url' => OCMigration::getSecureUrl($item),
                'filename' => $filename,
                'displayName' => $displayName,
                'group' => $group,
                'text' => $text,
            ];
        }
        if (!$isMultiple && !empty($values)) {
            return $values[0];
        }

        return $values;
    }

    private static function getFileNameFromContentDisposition($url)
    {
        $filename = basename($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)eZINI::instance('sqliimport.ini')->variable('ImportSettings', 'StreamTimeout'));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, eZStaticCache::USER_AGENT);
        $headers = curl_exec($ch);
        $parsedHeaders = array_map(function ($x) {
            return array_map("trim", explode(":", $x, 2));
        }, array_filter(array_map("trim", explode("\n", $headers))));
        foreach ($parsedHeaders as $line) {
            if (strtolower(trim($line[0])) == 'content-disposition') {
                $parts = explode('filename=', $line[1]);
                if (!isset($parts[1])) {
                    $parts = explode("filename*=UTF-8''", $line[1]);
                }
                $filename = urldecode(trim(array_pop($parts), '"'));
            }
        }

        return $filename;
    }

    public static function fetchByField($field, $name): array
    {
        $data = [];
        $names = explode(PHP_EOL, $name);
        if (!self::isEmptyArray($names)) {
            $data = static::fetchObjectList(
                static::definition(), null,
                ['trim(' . $field . ')' => [self::trimArray($names)]]
            );
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function storePayload(): int
    {
        $payloads = $this->generatePayload();
        if ($payloads instanceof PayloadBuilder) {
            $payloads = [$this::getImportPriority() => $payloads];
        }

        $index = 0;
        foreach ($payloads as $priority => $payload) {
            $payload = $payload->getArrayCopy();
            $idSuffix = ($index === 0) ? '' : '---' . $index;
            if (!empty($payload)) {
                OCMPayload::create(
                    $this->attribute('_id') . $idSuffix,
                    get_class($this),
                    $priority,
                    $payload
                );
                $index++;
            }
        }

        return $index;
    }

    protected function appendTranslationsToPayloadIfNeeded(PayloadBuilder $payload, $serializers = array())
    {
        $fields = static::$fields;
        $translationsLocalized = [];
        foreach ($fields as $field) {
            if (strpos($field, 'de_') === 0 && !empty($this->attribute($field))) {
                $attributeIdentifier = substr($field, 3);
                if (isset($serializers[$field])){
                    $translationsLocalized['ger-DE'][$attributeIdentifier] = $serializers[$field];
                }else {
                    $translationsLocalized['ger-DE'][$attributeIdentifier] = $this->attribute($field);
                }
            }
        }

        $defaultLocale = 'ita-IT';
        $payloadData = $payload->getData();

        if (!empty($translationsLocalized)) {

            $appendLocales = array_keys($translationsLocalized);
            $payload->setLanguages(
                array_unique(
                    array_merge($payload->getMetadaData('languages'), $appendLocales)
                )
            );
            foreach ($payloadData[$defaultLocale] as $identifier => $value){
                foreach ($appendLocales as $appendLocale){
                    if (isset($translationsLocalized[$appendLocale][$identifier])){
                        $payload->setData($appendLocale, $identifier, $translationsLocalized[$appendLocale][$identifier]);
                        unset($translationsLocalized[$appendLocale][$identifier]);
                    }else{
                        $payload->setData($appendLocale, $identifier, $value);
                    }
                }
            }

            foreach ($translationsLocalized as $translationLocale => $translations) {
                foreach ($translations as $identifier => $value) {
                    $payload->setData($translationLocale, $identifier, $value);
                }
            }
        }

        return $payload;
    }

    public function attributeArray($name)
    {
        $value = $this->attribute($name);
        if (!empty($value)) {
            $values = explode(PHP_EOL, $value);
            return self::trimArray($values);
        }

        return $value;
    }

    public function id(): ?string
    {
        return $this->attribute('_id');
    }

    public function name(): ?string
    {
        return $this->attribute(static::getSortField());
    }

    public function avoidNameDuplication()
    {
        return true;
    }

    /**
     * @return void
     * @throw RuntimeException
     */
    public function checkRequiredColumns()
    {
        //throw new RuntimeException();
    }

    public function getSpreadsheetRow()
    {
        $rowLink = OCMigrationSpreadsheet::instance()->getRowLink(
            static::getSpreadsheetTitle(),
            static::getIdColumnLabel(),
            $this->id()
        );
        if (!$rowLink) {
            throw new Exception('Error creating spreadsheet link');
        }
        return $rowLink;
    }

    public function fillOverflowData()
    {
        foreach ($this->attributes() as $attributeKey){
            $data = $this->attribute($attributeKey);
            $isAnOverflowField = strpos($data, 'Il valore di questo campo supera il limite di caratteri ammessi') !== false;
            $isAnOverrideField = $data == $this->id() && $attributeKey !== '_id';
            if ($isAnOverflowField || $isAnOverrideField){
                $baseUrl = parse_url($this->attribute('_original_url'), PHP_URL_HOST);
                $className = str_replace('ocm_', '', get_class($this));
                $remoteUrl = 'https://' . $baseUrl . '/api/ocm/v1/' . $className . '/' . $this->id();
                $remoteData = json_decode(file_get_contents($remoteUrl), true);
                $remoteAttributeData = $remoteData[$attributeKey] ?? '';
                $this->setAttribute($attributeKey, $remoteAttributeData);
                $this->store();
            }
        }
    }

    public function createUrlAlias()
    {
        $languageCode = 'ita-IT';
        $originalUrl = $this->attribute('_original_url');
        $aliasText = parse_url($originalUrl, PHP_URL_PATH);
        if (!empty($aliasText)) {
            $object = eZContentObject::fetchByRemoteID($this->id());
            $parentIsRoot = true;
            $aliasRedirects = true;
            if ($object instanceof eZContentObject) {
                $node = $object->mainNode();
                if ($node instanceof eZContentObjectTreeNode) {
                    $language = eZContentLanguage::fetchByLocale($languageCode);

                    $parentID = 0;
                    $linkID = 0;
                    $filter = new eZURLAliasQuery();
                    $filter->actions = ['eznode:' . $node->attribute('node_id')];
                    $filter->type = 'name';
                    $filter->limit = false;
                    $existingElements = $filter->fetchAll();
                    // TODO: add error handling when $existingElements is empty
                    if (count($existingElements) > 0) {
                        $parentID = (int)$existingElements[0]->attribute('parent');
                        $linkID = (int)$existingElements[0]->attribute('id');
                    }
                    if ($parentIsRoot) {
                        $parentID = 0; // Start from the top
                    }
                    $mask = $language->attribute('id');
                    $obj = $node->object();
                    $alwaysMask = ($obj->attribute('language_mask') & 1);
                    $mask |= $alwaysMask;

                    $origAliasText = $aliasText;
                    $result = eZURLAliasML::storePath(
                        $aliasText,
                        'eznode:' . $node->attribute('node_id'),
                        $language,
                        $linkID,
                        $alwaysMask,
                        $parentID,
                        true,
                        false,
                        false,
                        $aliasRedirects
                    );
                    if ($result['status'] === eZURLAliasML::LINK_ALREADY_TAKEN) {
                        return 'already-exists';
                    }
                    if ($result['status'] === true) {
                        ezpEvent::getInstance()->notify('content/cache', [$node->attribute('node_id')]);
                        return 'created';
                    }
                }
            }
        }
        return 'empty-text';
    }
}