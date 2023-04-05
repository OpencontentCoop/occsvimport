<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_private_organization extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'legal_name',
        'alt_name',
        'acronym',
        'description',
        'business_objective',
        'has_logo',
        'image',
        'has_spatial_coverage',
        'has_online_contact_point',
        'foundation_date',
        'has_private_org_activity_type',
        'topics',
        'attachments',
        'more_information',
        'holds_role_in_time',
        'tax_code',
        'vat_code',
        'rea_number',
        'private_organization_category',
        'legal_status_code',
        'identifier',
        'de_legal_name',
        'de_alt_name',
        'de_acronym',
        'de_description',
        'de_business_objective',
        'de_more_information',
    ];

    protected function getComunwebFieldMapper(): array
    {
        $places = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $gps = $firstLocalizedContentData['gps']['content'];
            if ($gps['latitude'] != 0 && $gps['longitude'] != 0) {
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $contactsName = "Contatti $name";
                $placeName = "Sede $name";
                $place = ocm_place::instanceBy('name', $placeName, $placeId);
                $place->setAttribute('help', $contactsName);
                $place->setAttribute('has_address', json_encode($gps));
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            }
            return '';
        };

        $contacts = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
            $name = $content->metadata['name']['ita-IT'];
            $object = eZContentObject::fetch((int)$content->metadata['id']);
            $dataMap = $object->dataMap();
            $contactsId = $id . ':contacts';
            $contactsName = "Contatti $name";
            $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
            $data = [];
            foreach (['telefono', ' numero_telefono1 ', 'email', 'fax', 'casella_postale', 'referente_telefono', 'referente_fax' ] as $identifier){
                if (isset($dataMap[$identifier])){
                    $type = ucfirst(str_replace('_', '', $identifier));
                    if (strpos($identifier, 'telefono')){
                        $type = 'Telefono';
                    }elseif ($identifier == 'cellulare'){
                        $type = 'Cellulare';
                    }elseif (strpos($identifier, 'fax')){
                        $type = 'Fax';
                    }elseif (stripos($identifier, 'email') !== false){
                        $type = 'Email';
                    }
                    $value = $dataMap[$identifier]->toString();
                    if (!empty($value)) {
                        $data[] = [
                            'type' => $type,
                            'value' => $value,
                            'contact' => '',
                        ];
                    }
                }
            }
            $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
            $node = $object->mainNode();
            $contacts->setNodeReference($node);
            $contacts->storeThis($options['is_update']);

            return $contactsName;
        };

        return [
            'legal_name' => OCMigration::getMapperHelper('titolo'),
            'alt_name' => false,
            'acronym' => false,
            'description' => OCMigration::getMapperHelper('abstract'),
            'business_objective' => false,
            'has_logo' => false,
            'image' => false,
            'has_online_contact_point' => $contacts,
            'has_spatial_coverage' => $places,
            'foundation_date' => false,
            'has_private_org_activity_type' => false,
            'topics' => false,
            'attachments' => false,
            'more_information' => OCMigration::getMapperHelper('scheda'),
            'holds_role_in_time' => false,
            'tax_code' => false,
            'vat_code' => false,
            'rea_number' => false,
            'private_organization_category' => false,
            'legal_status_code' => false,
            'identifier' => false,
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Organizzazioni private';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo*';
    }

    public static function getSortField(): string
    {
        return 'legal_name';
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            "Nome*" => $this->attribute('legal_name'),
            "Nome alternativo" => $this->attribute('alt_name'),
            "Acronimo" => $this->attribute('acronym'),
            "Descrizione*" => $this->attribute('description'),
            "Oggetto sociale" => $this->attribute('business_objective'),
            "Logo" => $this->attribute('has_logo'),
            "Immagini" => $this->attribute('image'),
            "Sedi" => $this->attribute('has_spatial_coverage'),
            "Punti di contatto" => $this->attribute('has_online_contact_point'),
            "Data di costituzione" => $this->attribute('foundation_date'),
            "Tipo di attività" => $this->attribute('has_private_org_activity_type'),
            "Argomenti" => $this->attribute('topics'),
            "Allegati" => $this->attribute('attachments'),
            "Ulteriori informazioni" => $this->attribute('more_information'),
            "Riveste un ruolo nel tempo" => $this->attribute('holds_role_in_time'),
            "Codice fiscale" => $this->attribute('tax_code'),
            "Partita IVA*" => $this->attribute('vat_code'),
            "REA" => $this->attribute('rea_number'),
            "Categoria di organizzazione privata" => $this->attribute('private_organization_category'),
            "Forma giuridica" => $this->attribute('legal_status_code'),
            "Identificativo univoco interno" => $this->attribute('identifier'),
            'Name* [de]' => $this->attribute('de_legal_name'),
            'Alternativer Name [de]' => $this->attribute('de_alt_name'),
            'Akronym [de]' => $this->attribute('de_acronym'),
            'Beschreibung* [de]' => $this->attribute('de_description'),
            'Unternehmenszweck [de]' => $this->attribute('de_business_objective'),
            'Weitere Informationen [de]' => $this->attribute('de_more_information'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo*"]);
        $item->setAttribute('legal_name', $row["Nome*"]);
        $item->setAttribute('alt_name', $row["Nome alternativo"]);
        $item->setAttribute('acronym', $row["Acronimo"]);
        $item->setAttribute('description', $row["Descrizione*"]);
        $item->setAttribute('business_objective', $row["Oggetto sociale"]);
        $item->setAttribute('has_logo', $row["Logo"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('has_spatial_coverage', $row["Sedi"]);
        $item->setAttribute('has_online_contact_point', $row["Punti di contatto"]);
        $item->setAttribute('foundation_date', $row["Data di costituzione"]);
        $item->setAttribute('has_private_org_activity_type', $row["Tipo di attività"]);
        $item->setAttribute('topics', $row["Argomenti"]);
        $item->setAttribute('attachments', $row["Allegati"]);
        $item->setAttribute('more_information', $row["Ulteriori informazioni"]);
        $item->setAttribute('holds_role_in_time', $row["Riveste un ruolo nel tempo"]);
        $item->setAttribute('tax_code', $row["Codice fiscale"]);
        $item->setAttribute('vat_code', $row["Partita IVA*"]);
        $item->setAttribute('rea_number', $row["REA"]);
        $item->setAttribute('private_organization_category', $row["Categoria di organizzazione privata"]);
        $item->setAttribute('legal_status_code', $row["Forma giuridica"]);
        $item->setAttribute('identifier', $row["Identificativo univoco interno"]);
        $item->setAttribute('de_legal_name', $row['Name* [de]']);
        $item->setAttribute('de_alt_name', $row['Alternativer Name [de]']);
        $item->setAttribute('de_acronym', $row['Akronym [de]']);
        $item->setAttribute('de_description', $row['Beschreibung* [de]']);
        $item->setAttribute('de_business_objective', $row['Unternehmenszweck [de]']);
        $item->setAttribute('de_more_information', $row['Weitere Informationen [de]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('private_organization');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->discoverParentNode());
        $payload->setLanguages([$locale]);
        $payload->setData($locale, 'legal_name', trim($this->attribute('legal_name')));
        $payload->setData($locale, 'alt_name', trim($this->attribute('alt_name')));
        $payload->setData($locale, 'acronym', trim($this->attribute('acronym')));
        $payload->setData($locale, 'description', trim($this->attribute('description')));
        $payload->setData($locale, 'business_objective', trim($this->attribute('business_objective')));
        $payload->setData($locale, 'has_logo', trim($this->attribute('has_logo')));
        if (!empty($this->attribute('has_logo')) && (strpos($this->attribute('has_logo'), 'http') !== false)) {
            $logoUrl = $this->attribute('has_logo');
            if (strpos($logoUrl, 'http') === false){
                $baseUrl = parse_url($this->attribute('original_url'), PHP_URL_HOST);
                $logoUrl = 'https://' . $baseUrl . $logoUrl;
            }
            $payload->setData($locale, 'has_logo', [
                'url' => $logoUrl,
                'filename' => basename($this->attribute('has_logo')),
            ]);
        }
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'has_spatial_coverage', ocm_place::getIdListByName($this->attribute('has_spatial_coverage')));
        $payload->setData($locale, 'has_online_contact_point', ocm_online_contact_point::getIdListByName($this->attribute('has_online_contact_point')));
        $payload->setData($locale, 'foundation_date', $this->formatDate($this->attribute('foundation_date')));
        $payload->setData($locale, 'has_private_org_activity_type', $this->formatTags($this->attribute('has_private_org_activity_type')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'attachments', ocm_document::getIdListByName($this->attribute('attachments')));
        $payload->setData($locale, 'more_information', trim($this->attribute('more_information')));
        $payload->setData($locale, 'tax_code', trim($this->attribute('tax_code')));
        $payload->setData($locale, 'vat_code', trim($this->attribute('vat_code')));
        $payload->setData($locale, 'rea_number', trim($this->attribute('rea_number')));
        $payload->setData($locale, 'private_organization_category', $this->formatTags($this->attribute('private_organization_category')));
        $payload->setData($locale, 'legal_status_code', $this->formatTags($this->attribute('legal_status_code')));
        $payload->setData($locale, 'identifier', trim($this->attribute('identifier')));

        return $this->appendTranslationsToPayloadIfNeeded($payload);
    }

    protected function discoverParentNode(): int
    {
        return $this->getNodeIdFromRemoteId('10742bd28e405f0e83ae61223aea80cb');
    }

    public static function getDateValidationHeaders(): array
    {
        return [
            "Data di costituzione"
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Punti di contatto' => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            'Immagini' => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Argomenti" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Allegati" => [
                'strict' => true,
                'ref' => ocm_document::getRangeRef()
            ],
            "Sedi" => [
                'strict' => true,
                'ref' => ocm_place::getRangeRef()
            ],
            "Tipo di attività" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('attivita'),
            ],
            "Categoria di organizzazione privata" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('organizzazioni-private'),
            ],
            "Forma giuridica" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('giuridica'),
            ],
        ];
    }

    public static function getColumnName(): string
    {
        return 'Nome*';
    }

    public static function getImportPriority(): int
    {
        return 100;
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        return parent::getIdListByName($name, 'legal_name', $tryWithPrefix);
    }

}