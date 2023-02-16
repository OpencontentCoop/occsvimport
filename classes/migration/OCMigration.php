<?php

use Opencontent\Opendata\Api\AttributeConverterLoader;
use Opencontent\Opendata\Api\Values\Content;

class OCMigration extends eZPersistentObject
{
    final public static function version()
    {
        return '1.1.0';
    }

    /**
     * @param string|null $context
     * @return OCMigrationComunweb|OCMigrationOpencity
     * @throws Exception
     */
    final public static function factory(string $context = null)
    {
        if (!$context) {
            !$context = self::discoverContext();
        }
        if ($context === 'comunweb') {
            return new OCMigrationComunweb();
        }

        if ($context === 'opencity') {
            return new OCMigrationOpencity();
        }

        throw new Exception("Context $context not found");
    }

    final public static function discoverContext()
    {

        if (eZContentClass::classIDByIdentifier('organization')) {
            return false;
        }

        if (eZContentClass::classIDByIdentifier('opening_hours_specification')) {
            return 'opencity';
        }

        return 'comunweb';
    }

    protected function debug($message, $eol = true)
    {
        eZCLI::instance()->output($message, $eol);
    }

    protected function info($message, $eol = true)
    {
        eZCLI::instance()->warning($message, $eol);
    }

    protected function error($message, $eol = true)
    {
        eZCLI::instance()->error($message, $eol);
    }

    /**
     * @return eZContentObjectTreeNode[]
     */
    private function getNodesByClassIdentifierList(
        string &$exclusionMessage,
        array $classIdentifiers,
        array $escludePathList = [],
        array $escludeRemoteIdPrefix = [],
        array $restrictSections = [],
        bool $groupByName = false,
        int $publishedAfter = null,
        int $publishedBeforeOrAt = null
    ): array
    {
        $this->debug('Fetching ' . implode(', ', $classIdentifiers) . $exclusionMessage . ' ... ');
        $exclusionMessage .= ' *recupero dei contenuti di tipo: ' . implode(', ', $classIdentifiers);

        if (!empty($escludePathList)){
            $exclusionMessage .= ' *escludendo i percorsi: ' . implode(', ', $escludePathList);
        }
        if (!empty($escludeRemoteIdPrefix)){
            $exclusionMessage .= ' *escludendo i prefissi di remote_id: ' . implode(', ', $escludeRemoteIdPrefix);
        }

        $params = [
            'MainNodeOnly' => true,
            'ClassFilterType' => 'include',
            'ClassFilterArray' => $classIdentifiers,
            'SortBy' => [['path_string', true]],
            'Limitation' => [],
        ];

        if (!empty($restrictSections)){
            $sectionIdList = [];
            $sectionIdName = [];
            foreach ($restrictSections as $restrictSection){
                if (is_numeric($restrictSection)){
                    $sectionIdList[] = (int)$restrictSection;
                    $sectionIdName[] = (int)$restrictSection;
                }else{
                    $section = eZSection::fetchByIdentifier($restrictSection);
                    if ($section instanceof eZSection){
                        $sectionIdList[] = (int)$section->attribute('id');
                        $sectionIdName[] = $section->attribute('name');
                    }
                }
            }
            if (!empty($sectionIdList)){
                $params['AttributeFilter'] = [
                    'and',
                    ['section', 'in', $sectionIdList]
                ];
                $exclusionMessage .= ' *includendo solo le sezioni: ' . implode(', ', $sectionIdName);
            }
        }

        if ($publishedAfter && $publishedBeforeOrAt) {
            $exclusionMessage .= ' *includendo solo i contenuti pubblicati tra il ' . date('j/n/Y', $publishedAfter) . ' e il ' . date('j/n/Y', $publishedBeforeOrAt);
            if (isset($params['AttributeFilter'])){
                $params['AttributeFilter'][] = ['published', '>', $publishedAfter];
                $params['AttributeFilter'][] = ['published', '<=', $publishedBeforeOrAt];
            }else {
                $params['AttributeFilter'] = [
                    'and',
                    ['published', '>', $publishedAfter],
                    ['published', '<=', $publishedBeforeOrAt],
                ];
            }
        }

        $nodesGroupedByName = [];
        /** @var eZContentObjectTreeNode[] $nodes */
        $nodes = eZContentObjectTreeNode::subTreeByNodeID($params, 1);

        if (!empty($escludePathList) || !empty($escludeRemoteIdPrefix)){
            $this->debug(count($nodes) . ' node founds without exclusions ', false);
            $_nodes = [];
            foreach ($nodes as $node){
                $do = true;
                foreach ($escludePathList as $escludePath){
                    if (stripos($node->attribute('path_identification_string'), $escludePath) !== false){
                        $do = false;
                        break;
                    }
                }
                if ($do) {
                    foreach ($escludeRemoteIdPrefix as $remoteIdPrefix) {
                        if (strpos($node->object()->attribute('remote_id'), $remoteIdPrefix) !== false) {
                            $do = false;
                            break;
                        }
                    }
                }
                if ($do){
                    if ($node->attribute('is_hidden') || $node->attribute('is_invisible')) {
                        $do = false;
                    }
                }

                if ($do){
                    if ($groupByName){
                        if (!isset($nodesGroupedByName[$node->attribute('name')])){
                            $node->clones = [];
                            $nodesGroupedByName[$node->attribute('name')] = $node;
                        }else{
                            $nodesGroupedByName[$node->attribute('name')]->clones[] = $node;
                        }
                    }else {
                        $_nodes[] = $node;
                    }
                }
            }
            if ($groupByName){
                $nodes = array_values($nodesGroupedByName);
            }else {
                $nodes = $_nodes;
            }
        }

        $this->info(count($nodes) . ' node founds');

        return $nodes;
    }

    final public static function getAvailableClasses($namesFilter = [])
    {
        $ocmList = [];
        $classes = include 'var/autoload/ezp_extension.php';
        foreach (array_keys($classes) as $class) {
            if (strpos($class, 'ocm_') !== false && in_array('ocm_interface', class_implements($class))) {
                if (!empty($namesFilter) && !in_array($class, $namesFilter)){
                    continue;
                }
                $ocmList[] = $class;
            }
        }

        return $ocmList;
    }

    final public static function createTableIfNeeded($cli = null, $truncate = false, $drop = false)
    {
        $db = eZDB::instance();
        eZDB::setErrorHandling(eZDB::ERROR_HANDLING_EXCEPTIONS);
        if ($cli) $cli->warning("Using db " . $db->DB);

        $tableQuery = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename  like 'ocm_%';";
        $exists = array_fill_keys(array_column($db->arrayQuery($tableQuery), 'tablename'), true);

        $classes = OCMigration::getAvailableClasses();
        foreach ($classes as $class) {
            $fields = $class::definition()['fields'];
            $columnsByFields = [];
            $tableCreateSql = "CREATE TABLE $class (";
            foreach ($fields as $field => $definition) {
                $columnsByFields[] = $field;
                if ($field === '_id') {
                    $tableCreateSql .= "$field varchar(255) NOT NULL default ''";
                } else {
                    $tableCreateSql .= "$field text default '', ";
                }
            }
            $tableCreateSql .= ');';
            $tableKeySql = "ALTER TABLE ONLY $class ADD CONSTRAINT {$class}_pkey PRIMARY KEY (_id);";

            if ($drop && isset($exists[$class])){
                if ($cli) $cli->output('Drop ' . $class);
                $db->query('DROP TABLE ' . $class);
                unset($exists[$class]);
            }

            if (!isset($exists[$class])) {
                if ($cli) $cli->warning('Create table ' . $class);
                foreach ($fields as $field => $definition) {
                    if ($cli) $cli->output(' - ' . $field);
                }
                $db->query($tableCreateSql);
                $db->query($tableKeySql);
            } else {
                if ($cli) $cli->output('Table ' . $class . ' already exists');

                //check columns consistency
                $columnsQuery = "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name   = '{$class}';";
                $columns = array_column($db->arrayQuery($columnsQuery), 'column_name');
                $missingColumns = array_diff($columnsByFields, $columns);
                if (!empty($missingColumns)){
                    $appendColumnQueryParts = [];
                    if ($cli) $cli->warning('Add missing columns to table ' . $class . ': ' . PHP_EOL . ' - ' . implode(PHP_EOL . ' - ', $missingColumns));
                    foreach ($missingColumns as $missingColumn){
                        $appendColumnQueryParts[] = "ADD COLUMN $missingColumn text default ''";
                    }
                    $appendColumnQuery = "ALTER TABLE $class " . implode(', ', $appendColumnQueryParts) . ';';
                    $db->query($appendColumnQuery);
                }

                if ($truncate){
                    if ($cli) $cli->output('Truncate ' . $class);
                    $db->query('TRUNCATE TABLE ' . $class);
                }
            }
        }
    }

    public static function getMapperHelper(string $field): callable
    {
        switch ($field) {

            case 'has_logo___name':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['has_logo']['content'] ?? false;
                    return $contentValue ? $contentValue['filename'] : '';
                };

            case 'has_logo___url':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['has_logo']['content'] ?? false;
                    $url = $contentValue ? $contentValue['url'] : '';
                    eZURI::transformURI($url, false, 'full');
                    return $contentValue ? $url : '';
                };

            case 'image/name':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['image']['content'];
                    return $contentValue ? $contentValue['filename'] : '';
                };

            case 'image/url':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['image']['content'];
                    $url = $contentValue ? $contentValue['url'] : '';
                    eZURI::transformURI($url, false, 'full');
                    return $contentValue ? $url : '';
                };

            default:

                $subField = false;
                if (strpos($field, '/') !== false){
                    [$field, $subField] = explode('/', $field);
                }

                return function (
                    Content $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                ) use ($field, $subField) {
                    $localizedFields = [];
                    foreach ($content->data as $locale => $data){
                        $localizedFields[$locale] = $data[$field]['content'] ?? [];
                    }
                    if (!isset($firstLocalizedContentData[$field])){
                        return '';
                    }
                    $fieldInfo = $firstLocalizedContentData[$field];
                    $contentValue = $fieldInfo['content'];
                    $dataType = $fieldInfo['datatype'];
                    $converter = AttributeConverterLoader::load(
                        $content->metadata->classIdentifier,
                        $field,
                        $dataType
                    );

                    switch ($dataType){

                        case eZObjectRelationListType::DATA_TYPE_STRING:
                        {
                            $data = [];
                            foreach ($contentValue as $metadata) {
                                if ($field === 'topics'){
                                    $data[] = $metadata['id'] ?? $metadata['metadata']['id'];
                                }else {
                                    $data[] = isset($metadata['name']) ? $metadata['name'][$firstLocalizedContentLocale]
                                        : $metadata['metadata']['name'][$firstLocalizedContentLocale];
                                }
                            }

                            if ($field === 'topics'){
                                $data = self::filterTopics($data);
                            }

                            return implode(PHP_EOL, $data);
                        }

                        case eZDateType::DATA_TYPE_STRING:
                            return $contentValue && intval($contentValue) > 0 ? date(
                                'j/n/Y',
                                strtotime($contentValue)
                            ) : '';

                        case eZDateTimeType::DATA_TYPE_STRING;
                            return $contentValue && intval($contentValue) > 0 ? date(
                                'j/n/Y H:i',
                                strtotime($contentValue)
                            ) : '';

                        case eZBinaryFileType::DATA_TYPE_STRING:
                            $contentValue = $converter->toCSVString($contentValue, $firstLocalizedContentLocale);
                            if (!empty($contentValue)) {
                                $parts = explode('/', $contentValue);
                                $name = array_pop($parts);
                                $parts[] = urlencode($name);
                                return implode('/', $parts);
                            }
                            return '';

                        case OCMultiBinaryType::DATA_TYPE_STRING:
                            if (!empty($contentValue)) {
                                $files = [];
                                foreach ($contentValue as $file) {
                                    $fileParts = explode('/', $file['url']);
                                    $fileName = array_pop($fileParts);
                                    $fileParts[] = urlencode($fileName);
                                    $files[] = implode('/', $fileParts);
                                }
                                return implode(PHP_EOL, $files);
                            }
                            return '';

                        case eZXMLTextType::DATA_TYPE_STRING:
                            if (isset($options['remove_ezxml_embed'])) {
                                /** @var eZContentObjectAttribute $attribute */
                                $attribute = eZContentObjectAttribute::fetch($fieldInfo['id'], $fieldInfo['version']);
                                if ($attribute instanceof eZContentObjectAttribute) {
                                    /** @var eZXMLText $attributeContent */
                                    $attributeContent = $attribute->content();

                                    // remove embed
                                    $outputHandler = new eZXHTMLXMLOutput(
                                        $attributeContent->XMLData, false, $attribute
                                    );
                                    unset($outputHandler->OutputTags['embed']);
                                    $text = $outputHandler->outputText();
                                    $text = str_replace('<div class="embed"></div>', '', $text);
                                    $contentValue = $text;
                                }
                            }
                            if (isset($options['ezxml_strip_tags'])) {
                                $contentValue = strip_tags($contentValue);
                            }
                            $contentValue = str_replace('<div class=""></div>', '', $contentValue);
                            return $contentValue;

                        case eZGmapLocationType::DATA_TYPE_STRING:
                            $contentValue['address'] = str_replace('amp;', '', $contentValue['address']);
                            return json_encode($contentValue);

                        case OCEventType::DATA_TYPE_STRING:
                            if ($subField === 'ical'){
                                return $contentValue['input']['recurrencePattern'];
                            }
                            if ($subField === 'events'){
                                $events = $contentValue['events'];
                                $data = [];
                                foreach ($events as $event){
                                    $data[] = date('j/n/Y H:i', strtotime($event['start']))
                                        . ' - ' .
                                        date('j/n/Y H:i', strtotime($event['end']));
                                }
                                return implode(PHP_EOL, $data);
                            }

                            return json_encode($contentValue);

                        case eZMatrixType::DATA_TYPE_STRING:
                            if (!$subField) {
                                return json_encode($localizedFields);
                            }else{
                                return implode(PHP_EOL, array_column($contentValue, $subField));
                            }

                        default:{
                            return $converter->toCSVString($contentValue, $firstLocalizedContentLocale);
                        }
                    }
                };
        }
    }

    private static function filterTopics($idList)
    {
        $data = [];
        $topics = [
            'topic_1_agricoltura_e_alimentazione' => [],
            'topic_1_ambiente' => [],
            'topic_1_popolazione_e_societa' => [],
            'topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica' => [],
            'topic_1_economia_e_finanze' => [],
            'topic_13' => [],
            'topic_1_scienza_e_tecnologia' => [],
            'topic_1_istruzione_cultura_e_sport' => [],
            'topic_1_territorio' => [],
            'topic_1_governo_e_settore_pubblico' => [],
            '18e6e1013c2999465c05b2ad41b364cf' => [],
            'topic_1_tematiche_internazionali' => [],
            '6b9adcbc7ca00d48590c2c0122d45873' => [],
            'topic_36' => [],
            'topic_2_agricoltura' => ['topic_1_agricoltura_e_alimentazione',],
            'topic_4' => ['topic_1_agricoltura_e_alimentazione',],
            'topic_2_foreste' => ['topic_1_agricoltura_e_alimentazione',],
            '2b67071267460acb651dab78c5937290' => ['topic_1_agricoltura_e_alimentazione',],
            '303df154b15e47f7986343c30ba57637' => ['topic_1_agricoltura_e_alimentazione',],
            'topic_2' => ['topic_1_ambiente',],
            '17722a57fb20ca1210125d2bdd8323ec' => ['topic_1_ambiente',],
            'topic_17' => ['topic_1_ambiente',],
            'topic_21' => ['topic_1_ambiente',],
            'topic_31' => ['topic_1_ambiente',],
            'topic_7' => ['topic_1_popolazione_e_societa',],
            'topic_2_impiego_nella_pa' => ['topic_1_popolazione_e_societa',],
            'topic_24' => ['topic_1_popolazione_e_societa',],
            'topic_16' => ['topic_1_popolazione_e_societa',],
            'topic_10' => ['topic_1_popolazione_e_societa',],
            '520467b8e456dd71a0df06701267ec62' => ['topic_1_popolazione_e_societa',],
            'a01a8345c4dd069454bd23f4a131b8ec' => ['topic_1_popolazione_e_societa',],
            '2585c8de2079feb3db29f85d3293de15' => ['topic_1_popolazione_e_societa',],
            'a600b3fb2825c2e6c688c4bde8c3f961' => ['topic_1_popolazione_e_societa',],
            'topic_2_volontariato' => ['topic_1_popolazione_e_societa',],
            '03247490a219ea48d754b8ffe0218429' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
            'topic_3_polizia' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
            '087e4fb8eb71d06eb6edbfe6aaee6ecf' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
            'topic_30' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
            '468a42f92ac4acd1543f830f630fe1dd' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
            'topic_2_costi_bilanci_spese_dell_ente' => ['topic_1_economia_e_finanze',],
            '6df6d993b921ba5585b2c992b3ab4d5e' => ['topic_1_economia_e_finanze',],
            '9e9c6c0a4f25bad956def349e7ba7548' => ['topic_1_economia_e_finanze',],
            '91bc19e1e6201bcb0a246791bad4d888' => ['topic_1_economia_e_finanze',],
            'topic_2_tributi' => ['topic_1_economia_e_finanze',],
            '7a508ac8d8ede77941d382c758a99042' => ['topic_1_economia_e_finanze',],
            '9642f556d5f52562385a6ff83f342b78' => ['topic_1_economia_e_finanze',],
            '82f3daf9f172801c57f13d95000facfb' => ['topic_1_economia_e_finanze',],
            'f9646a846cd5c0cc94e576fe3250d502' => ['topic_1_economia_e_finanze',],
            '0dfc780404e1e86d3013c942f812e262' => ['topic_1_economia_e_finanze',],
            'topic_2_energia_rinnovabile' => ['topic_13',],
            'ff27f221bb1a1105319f758da98f1005' => ['topic_13',],
            'topic_2_risparmio_energetico' => ['topic_13',],
            '30308859ca4274ad266ae1b38666ae1e' => ['topic_1_scienza_e_tecnologia',],
            'topic_2_citta_intelligente' => ['topic_1_scienza_e_tecnologia',],
            'topic_20' => ['topic_1_scienza_e_tecnologia',],
            'dfa6ed0f6ceeddbc718c6280e53b9385' => ['topic_1_scienza_e_tecnologia',],
            'topic_2_ricerca' => ['topic_1_scienza_e_tecnologia',],
            'f85cb496baed09fae468f041ae275a37' => ['topic_1_istruzione_cultura_e_sport',],
            'f85de55bbafcd80eeed201a2d99d2351' => ['topic_1_istruzione_cultura_e_sport',],
            '6b101d7978a415884679d24a9afcec17' => ['topic_1_istruzione_cultura_e_sport',],
            'topic_1' => ['topic_1_territorio',],
            'topic_2_catasto' => ['topic_1_territorio',],
            'topic_3_lavori_pubblici' => ['topic_1_territorio',],
            'topic_39' => ['topic_1_territorio',],
            'topic_9' => ['topic_1_governo_e_settore_pubblico',],
            'topic_2_politica' => ['topic_1_governo_e_settore_pubblico',],
            'fe63739f7047ad84533d1055c9380444' => ['topic_1_governo_e_settore_pubblico',],
            'topic_26' => ['topic_1_governo_e_settore_pubblico',],
            'c60b70f9d0f4bcb8dd1c0ff34ac90d16' => ['topic_1_governo_e_settore_pubblico',],
            '0afb9385587fd6f9fdff39d9dd5e3142' => ['topic_1_governo_e_settore_pubblico',],
            'topic_2_vita_istituzionale' => ['topic_1_governo_e_settore_pubblico',],
            'topic_3_assistenza_agli_invalidi' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_3_assistenza_sociale' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_2_covid_19' => ['18e6e1013c2999465c05b2ad41b364cf',],
            '0b43588c71719126304e8aaae9e6438d' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_19' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_22' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_15' => ['18e6e1013c2999465c05b2ad41b364cf',],
            '2eb5ffa8cfbb467dee3026fd6ab7464c' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_3_protezione_delle_minoranze' => ['18e6e1013c2999465c05b2ad41b364cf',],
            'topic_2_comunita_europea' => ['topic_1_tematiche_internazionali',],
            '901ac93a6d4baaa901cc17ac155101ca' => ['topic_1_tematiche_internazionali',],
            'topic_2_gemellaggi' => ['topic_1_tematiche_internazionali',],
            'bd5192a42244b7e5a0dc4ac0830bce83' => ['6b9adcbc7ca00d48590c2c0122d45873',],
            'topic_32' => ['6b9adcbc7ca00d48590c2c0122d45873',],
            '158a858d0fe4b8a9d0fe5d50c3605cb2' => ['6b9adcbc7ca00d48590c2c0122d45873',],
            '6c62b4913df2ea2b739dae5cc04f70da' => ['6b9adcbc7ca00d48590c2c0122d45873',],
            'd5ffcb9ed91fa49cfdc42a7ee6fb4d81' => ['topic_36',],
            'topic_6' => ['topic_36',],
            '64e2943f2b139bbe825a1ec700cb24fc' => ['topic_36',],
            'topic_37' => ['topic_36',],
            'topic_35' => ['topic_36',],
            'abc9ab5b22d6f4a06ba477b75dafd075' => ['topic_36',],
            'fdfafe70890f994101c6a56d9928ef69' => ['topic_36',],
        ];

        $objects = OpenPABase::fetchObjects($idList);
        foreach ($objects as $object) {
            if (isset($topics[$object->remoteID()])) {
                $data[] = $object->name();
            }
        }

        return $data;
    }

    protected function fillByType(
        array $namesFilter, // esegui solo sulle ocm_ selezionate
        bool $isUpdate, // non toccare contenuti già creati
        string $ocmClass, // ocm class da elaborare
        array $classIdentifiers, // filtro sulle classi
        array $escludePathList = [], // escludi pat_string
        array $escludeRemoteIdPrefix = [], // escludi in base a remote_id
        array $restrictSections = [], // solo per le sezioni selezionate,
        bool $groupByName = false,
        int $restrictToLastYears = 0
    )
    {
        if (empty($namesFilter) || in_array($ocmClass, $namesFilter)) {

            $publishedAfter = null;
            $publishedBeforeOrAt = null;

            if ($restrictToLastYears > 0){
                $publishedBeforeOrAt = time();
                $publishedAfter = (new DateTime())->sub(new DateInterval('P2Y'))->format('U');
            }

            $exclusionMessage = '';
            $nodes = $this->getNodesByClassIdentifierList(
                $exclusionMessage,
                $classIdentifiers,
                $escludePathList,
                $escludeRemoteIdPrefix,
                $restrictSections,
                $groupByName,
                $publishedAfter,
                $publishedBeforeOrAt
            );
            $exclusionMessage = '<small><em>' . str_replace('*', '<br />', $exclusionMessage) . '</em></small>';

            $count = count($nodes);
            OCMigrationSpreadsheet::appendMessageToCurrentStatus([$ocmClass => [
                'status' => 'running',
                'action' => 'export',
                'update' => 'Scansione di ' . $count . ' contenuti... ' . $exclusionMessage,
            ]]);
            $rows = 0;
            foreach ($nodes as $index => $node) {
                $index++;
                $this->debug(" $index/$count " . $node->classIdentifier() . ' ', false);
                if ($this->createFromNode($node, new $ocmClass, [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->debug($node->attribute('name'));
                }else{
                    $this->debug('');
                }
                if ($index % 100 === 0){
                    OCMigrationSpreadsheet::appendMessageToCurrentStatus([$ocmClass => [
                        'status' => 'running',
                        'action' => 'export',
                        'update' => 'Evaluated ' . $index . ' of '. $count . ' contents... ' . $exclusionMessage,
                    ]]);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus([$ocmClass => [
                'status' => 'success',
                'action' => 'export',
                'update' => 'Esportati ' . $rows . ' di '. $count . ' contenuti scansionati ' . $exclusionMessage,
            ]]);
        }
    }

    protected function createFromNode(
        eZContentObjectTreeNode $node,
        ocm_interface $item,
        array $options = []
    ): ocm_interface {
        throw new Exception('Implement ' . __METHOD__);
    }
}