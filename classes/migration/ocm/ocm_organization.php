<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_organization extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'legal_name',
        'alt_name',
        'topics',
        'abstract',
        'description',
        'image',
        'main_function',
        'hold_employment',
        'type',
        'has_spatial_coverage',
        'has_online_contact_point',
        'attachments',
        'more_information',
        'identifier',
        'tax_code_e_invoice_service',
        'has_logo___name',
        'has_logo___url',
        'de_legal_name',
        'de_abstract',
        'de_main_function',
        'de_alt_name',
        'de_more_information',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Unità organizzative';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificativo unità organizzativa*";
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        if (in_array($node->classIdentifier(), ['area', 'servizio', 'ufficio'])){
            return $this->fromNode($node, $this->getComunwebAmministrativaFieldMapper(), $options);
        }elseif (in_array($node->classIdentifier(), ['organo_politico'])){
            return $this->fromNode($node, $this->getComunwebPoliticaFieldMapper(), $options);
        }elseif (in_array($node->classIdentifier(), ['sindaco'])){
            return $this->fromNode($node, $this->getComunwebSindacoFieldMapper(), $options);
        }

        return null;
    }

    protected function getComunwebSindacoFieldMapper(): array
    {
        return []; //@todo
    }

    protected function getComunwebAmministrativaFieldMapper(): array
    {
        return [
            'legal_name' => OCMigration::getMapperHelper('titolo'),
            'alt_name' => false,
            'topics' => false,
            'abstract' => OCMigration::getMapperHelper('abstract'),
            'description' => OCMigration::getMapperHelper('description'),
            'image' => false,
            'main_function' => OCMigration::getMapperHelper('competenze'),
            'type' => function(Content $content){
                return $content->metadata['classIdentifier'] === 'area' ? 'Area' : 'Ufficio';
            },
            'has_online_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $className = $object->className();

                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $hoursId = $id . ':hours';
                $hoursName = "Orari $className $name";
                $hours = ocm_opening_hours_specification::instanceBy('name', $hoursName, $hoursId);
                $hours->setAttribute('stagionalita', "Unico");
                $hours->setAttribute('note', OCMigration::getMapperHelper('orario')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options));
                $hours->setNodeReference($node);
                $hours->storeThis($options['is_update']);

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $className $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
                $data = [];
                foreach (['telefoni', 'fax', 'email', 'email2', 'email_certificata', ] as $identifier){
                    if (isset($dataMap[$identifier])){
                        $type = $identifier;
                        if ($identifier == 'telefoni'){
                            $type = 'Telefono';
                        }elseif ($identifier == 'fax'){
                            $type = 'Fax';
                        }elseif ($identifier == 'email_certificata'){
                            $type = 'PEC';
                        }elseif (stripos($identifier, 'email') !== false){
                            $type = 'Email';
                        }
                        $data[] = [
                            'type' => $type,
                            'value' => $dataMap[$identifier]->toString(),
                            'contact' => '',
                        ];
                    }
                }
                $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
                $contacts->setAttribute('phone_availability_time', $hoursName);
                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'has_spatial_coverage' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $hoursName = "Orari $name";
                $contactsName = "Contatti $name";
                $placeName = "Sede $name";
                $place = ocm_place::instanceBy('name', $placeName, $placeId);
                $place->setAttribute('type', 'Palazzo');
                $place->setAttribute('opening_hours_specification', $hoursName);

                $place->setAttribute('help', $contactsName);

                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            },
            'attachments' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $data = [];
//                $file = OCMigration::getMapperHelper('file')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($file)){
//                    $data[] = $file;
//                }
//                $ubicazione = OCMigration::getMapperHelper('ubicazione')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($ubicazione)){
//                    $data[] = $ubicazione;
//                }

                return implode(PHP_EOL, $data);
            },
            'more_information' => OCMigration::getMapperHelper('riferimenti_utili'),
            'identifier' => false,
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => OCMigration::getMapperHelper('image/name'),
            'has_logo___url' => OCMigration::getMapperHelper('image/url'),
        ];
    }

    protected function getComunwebPoliticaFieldMapper(): array
    {
        return [
            'legal_name' => OCMigration::getMapperHelper('titolo'),
            'alt_name' => false,
            'topics' => false,
            'abstract' => OCMigration::getMapperHelper('abstract'),
            'description' => OCMigration::getMapperHelper('descrizione'),
            'image' => false,
            'main_function' => OCMigration::getMapperHelper('competenze'),
            'type' => function(Content $content){
                $name = $content->metadata['name']['ita-IT'];
                $type = 'Struttura politica';
                if (stripos($name, 'sindaco') !== false){
                    $type = 'Sindaco';
                }
                if (stripos($name, 'consiglio') !== false){
                    $type = 'Consiglio comunale';
                }
                if (stripos($name, 'giunta') !== false){
                    $type = 'Giunta comunale';
                }
                if (stripos($name, 'commiss') !== false){
                    $type = 'Commissione';
                }
                return $type;
            },
            'has_online_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $className = $object->className();

                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $hoursId = $id . ':hours';
                $hoursName = "Orari $className $name";
                $hours = ocm_opening_hours_specification::instanceBy('name', $hoursName, $hoursId);
                $hours->setAttribute('stagionalita', "Unico");
                $hours->setAttribute('note', OCMigration::getMapperHelper('contatti')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options));
                $hours->setNodeReference($node);
                $hours->storeThis($options['is_update']);

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $className $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
                $contacts->setAttribute('phone_availability_time', $hoursName);
                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'has_spatial_coverage' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $hoursName = "Orari $name";
                $contactsName = "Contatti $name";
                $placeName = "Sede $name";
                $place = ocm_place::instanceBy('name', $placeName, $placeId);
                $place->setAttribute('type', 'Palazzo');
                $place->setAttribute('opening_hours_specification', $hoursName);
                $place->setAttribute('help', $contactsName);
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            },
            'attachments' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $data = [];
//                $file = OCMigration::getMapperHelper('curriculum')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($file)){
//                    $data[] = $file;
//                }
                return implode(PHP_EOL, $data);
            },
            'more_information' => OCMigration::getMapperHelper('nota'),
            'has_logo___name' => OCMigration::getMapperHelper('image/name'),
            'has_logo___url' => OCMigration::getMapperHelper('image/url'),
        ];
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        if ($node->classIdentifier() === 'administrative_area'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromAdministrativeArea(), $options);
        }
        if ($node->classIdentifier() === 'homogeneous_organizational_area'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromOrganizationalArea(), $options);
        }
        if ($node->classIdentifier() === 'office'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromOffice(), $options);
        }
        if ($node->classIdentifier() === 'political_body'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromPoliticalBody(), $options);
        }

        return null;
    }

    protected function getOpencityFieldMapperFromAdministrativeArea(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => OCMigration::getMapperHelper('org_acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigration::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Area'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigration::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigration::getMapperHelper('ipacode'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
            'de_legal_name' => false,
            'de_abstract' => false,
            'de_main_function' => false,
            'de_alt_name' => false,
            'de_more_information' => false,
        ];
    }

    protected function getOpencityFieldMapperFromOrganizationalArea(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => OCMigration::getMapperHelper('org_acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigration::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Area'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigration::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigration::getMapperHelper('a_o_o_identifier'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
            'de_legal_name' => false,
            'de_abstract' => false,
            'de_main_function' => false,
            'de_alt_name' => false,
            'de_more_information' => false,
        ];
    }

    protected function getOpencityFieldMapperFromOffice(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => OCMigration::getMapperHelper('acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigration::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Ufficio'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigration::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigration::getMapperHelper('office_identifier'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
            'de_legal_name' => false,
            'de_abstract' => false,
            'de_main_function' => false,
            'de_alt_name' => false,
            'de_more_information' => false,
        ];
    }

    protected function getOpencityFieldMapperFromPoliticalBody(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => false,
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => false,
            'type' => OCMigration::getMapperHelper('type_political_body'),
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => false,
            'more_information' => false,
            'identifier' => false,
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
            'de_legal_name' => false,
            'de_abstract' => false,
            'de_main_function' => false,
            'de_alt_name' => false,
            'de_more_information' => false,
        ];
    }

    public static function getSortField(): string
    {
        return 'legal_name';
    }

    public function toSpreadsheet(): array
    {
        return [
            'Identificativo unità organizzativa*' => $this->attribute('_id'),
            'Nome dell\'unità organizzativa*' => $this->attribute('legal_name'),
            'Descrizione breve*' => $this->convertToMarkdown($this->attribute('abstract')),
            'Descrizione' => $this->attribute('description'),
            'Competenze*' => $this->attribute('main_function'),
            'Tipo di organizzazione*' => $this->attribute('type'),
            'Sede/i*' => $this->attribute('has_spatial_coverage'),
            'Contatti*' => $this->attribute('has_online_contact_point'),
            'Argomenti' => $this->attribute('topics'),
            'Immagine' => $this->attribute('image'),
            'Unità organizzativa genitore' => $this->attribute('hold_employment'),
            'Allegati' => $this->attribute('attachments'),
            'Ulteriori informazioni' => $this->attribute('more_information'),
            'Nome alternativo' => $this->attribute('alt_name'),
            'Identificatore univoco interno' => $this->attribute('identifier'),
            'Codice fiscale servizio di fatturazione elettronica' => $this->attribute('tax_code_e_invoice_service'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            'Organisationseinheit* [de]' => $this->attribute('de_legal_name'),
            'Kurze Beschreibung* [de]' => $this->attribute('de_abstract'),
            'Kompetenzen* [de]' => $this->attribute('de_main_function'),
            'Alternativer Name [de]' => $this->attribute('de_alt_name'),
            'Weitere Informationen [de]' => $this->attribute('de_more_information'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['Identificativo unità organizzativa*']);
        $item->setAttribute('legal_name', $row['Nome dell\'unità organizzativa*']);
        $item->setAttribute('abstract', $row['Descrizione breve*']);
        $item->setAttribute('description', $row['Descrizione']);
        $item->setAttribute('main_function', $row['Competenze*']);
        $item->setAttribute('type', $row['Tipo di organizzazione*']);
        $item->setAttribute('has_spatial_coverage', $row['Sede/i*']);
        $item->setAttribute('has_online_contact_point', $row['Contatti*']);
        $item->setAttribute('topics', $row['Argomenti']);
        $item->setAttribute('image', $row['Immagine']);
        $item->setAttribute('hold_employment', $row['Unità organizzativa genitore']);
        $item->setAttribute('attachments', $row['Allegati']);
        $item->setAttribute('more_information', $row['Ulteriori informazioni']);
        $item->setAttribute('alt_name', $row['Nome alternativo']);
        $item->setAttribute('identifier', $row['Identificatore univoco interno']);
        $item->setAttribute('tax_code_e_invoice_service', $row['Codice fiscale servizio di fatturazione elettronica']);
        $item->setAttribute('alt_name', $row['Nome alternativo']);
        $item->setAttribute('de_legal_name', $row['Organisationseinheit* [de]']);
        $item->setAttribute('de_abstract', $row['Kurze Beschreibung* [de]']);
        $item->setAttribute('de_main_function', $row['Kompetenzen* [de]']);
        $item->setAttribute('de_alt_name', $row['Alternativer Name [de]']);
        $item->setAttribute('de_more_information', $row['Weitere Informationen [de]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    protected function discoverParentNode(): int
    {
        $isPol = count(array_diff(
            $this->formatTags($this->attribute('type')), [
            'Commissione',
            'Consiglio comunale',
            'Giunta comunale',
        ])) === 0;

        if ($isPol){
            return $this->getNodeIdFromRemoteId('organi_politici');
        }

        if (in_array('Area', $this->formatTags($this->attribute('type')))){
            $this->getNodeIdFromRemoteId('899b1ac505747c0d8523dfe12751eaae'); //aree
        }

        return $this->getNodeIdFromRemoteId('a9d783ef0712ac3e37edb23796990714'); // Uffici
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('organization');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->discoverParentNode());
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'legal_name', $this->attribute('legal_name'));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
//        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'main_function', $this->attribute('main_function'));
        $payload->setData($locale, 'type', $this->formatTags($this->attribute('type')));
        $payload->setData($locale, 'has_spatial_coverage', ocm_place::getIdListByName($this->attribute('has_spatial_coverage')));
        $payload->setData($locale, 'has_online_contact_point', ocm_online_contact_point::getIdListByName($this->attribute('has_online_contact_point')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'more_information', $this->attribute('more_information'));
        $payload->setData($locale, 'alt_name', $this->attribute('alt_name'));
        $payload->setData($locale, 'identifier', $this->attribute('identifier'));
        $payload->setData($locale, 'tax_code_e_invoice_service', $this->attribute('tax_code_e_invoice_service'));

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);
        $payloads = [self::getImportPriority() => $payload];
        $holdEmployments = ocm_organization::getIdListByName($this->attribute('hold_employment'), 'legal_name');
        $attachments = ocm_document::getIdListByName($this->attribute('attachments'));

        if (count($holdEmployments) > 0 || count($attachments) > 0) {
            $payload2 = clone $payload;
            $payload2->unSetData();
            $payload2->setData($locale, 'attachments', $attachments);
            $payload2->setData($locale, 'hold_employment', $holdEmployments);
            if (in_array('ger-DE', $payload->getMetadaData('languages'))){
                $payload2->setData('ger-DE', 'attachments', $attachments);
                $payload2->setData('ger-DE', 'hold_employment', $holdEmployments);
            }
            $payloads[ocm_banner::getImportPriority()+1] = $payload2;
        }

        return $payloads;
    }

    public static function getColumnName(): string
    {
        return 'Nome dell\'unità organizzativa*';
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*"
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            'Descrizione breve*',
            'Descrizione',
            'Competenze*',
            'Ulteriori informazioni',
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Contatti*' => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            'Immagine' => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Argomenti" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Sede/i*" => [
                'strict' => true,
                'ref' => ocm_place::getRangeRef()
            ],
            "Tipo di organizzazione*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('organizzazioni'),
            ],
            "Unità organizzativa genitore" => [
                'strict' => true,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Allegati" => [
                'strict' => true,
                'ref' => ocm_document::getRangeRef()
            ],
        ];
    }

    public static function getImportPriority(): int
    {
        return 30;
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        return parent::getIdListByName($name, 'legal_name', $tryWithPrefix); // TODO: Change the autogenerated stub
    }


}