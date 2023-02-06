<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_organization extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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
        }
        return null;
    }

    protected function getComunwebAmministrativaFieldMapper(): array
    {
        return [
            'legal_name' => OCMigrationOpencity::getMapperHelper('titolo'),
            'alt_name' => false,
            'topics' => false,
            'abstract' => OCMigrationOpencity::getMapperHelper('abstract'),
            'description' => OCMigrationOpencity::getMapperHelper('description'),
            'image' => false,
            'main_function' => OCMigrationOpencity::getMapperHelper('competenze'),
            'type' => function(Content $content){
                return $content->metadata['classIdentifier'] === 'area' ? 'Area' : 'Ufficio';
            },
            'has_online_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];

                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $hoursId = $id . ':hours';
                $hoursName = "Orari $name";
                $hours = new ocm_opening_hours_specification();
                $hours->setAttribute('_id', $hoursId);
                $hours->setAttribute('name', $hoursName);
                $hours->setAttribute('stagionalita', "Unico");
                $hours->setAttribute('note', OCMigrationOpencity::getMapperHelper('orario')($content, $firstLocalizedContentData, $firstLocalizedContentLocale));
                $hours->store();

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
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
                $contacts->store();

                return $contactsName;
            },
            'has_spatial_coverage' => function(Content $content){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $hoursName = "Orari $name";
                $contactsName = "Contatti $name";
                $placeName = "Sede $name";
                $place = new ocm_place();
                $place->setAttribute('_id', $placeId);
                $place->setAttribute('name', $placeName);
                $place->setAttribute('type', 'Palazzo');
                $place->setAttribute('opening_hours_specification', $hoursName);
                $place->setAttribute('help', $contactsName);
                $place->store();

                return $placeName;
            },
            'attachments' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $data = [];
//                $file = OCMigrationOpencity::getMapperHelper('file')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($file)){
//                    $data[] = $file;
//                }
//                $ubicazione = OCMigrationOpencity::getMapperHelper('ubicazione')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($ubicazione)){
//                    $data[] = $ubicazione;
//                }

                return implode(PHP_EOL, $data);
            },
            'more_information' => OCMigrationOpencity::getMapperHelper('riferimenti_utili'),
            'identifier' => false,
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => OCMigrationOpencity::getMapperHelper('image/name'),
            'has_logo___url' => OCMigrationOpencity::getMapperHelper('image/url'),
        ];
    }

    protected function getComunwebPoliticaFieldMapper(): array
    {
        return [
            'legal_name' => OCMigrationOpencity::getMapperHelper('titolo'),
            'alt_name' => false,
            'topics' => false,
            'abstract' => OCMigrationOpencity::getMapperHelper('abstract'),
            'description' => OCMigrationOpencity::getMapperHelper('descrizione'),
            'image' => false,
            'main_function' => OCMigrationOpencity::getMapperHelper('competenze'),
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
            'has_online_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $hoursId = $id . ':hours';
                $hoursName = "Orari $name";
                $hours = new ocm_opening_hours_specification();
                $hours->setAttribute('_id', $hoursId);
                $hours->setAttribute('name', $hoursName);
                $hours->setAttribute('stagionalita', "Unico");
                $hours->setAttribute('note', OCMigrationOpencity::getMapperHelper('contatti')($content, $firstLocalizedContentData, $firstLocalizedContentLocale));
                $hours->store();

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
                $contacts->setAttribute('phone_availability_time', $hoursName);
                $contacts->store();

                return $contactsName;
            },
            'has_spatial_coverage' => function(Content $content){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $hoursName = "Orari $name";
                $contactsName = "Contatti $name";
                $placeName = "Sede $name";
                $place = new ocm_place();
                $place->setAttribute('_id', $placeId);
                $place->setAttribute('name', $placeName);
                $place->setAttribute('type', 'Palazzo');
                $place->setAttribute('opening_hours_specification', $hoursName);
                $place->setAttribute('help', $contactsName);
                $place->store();

                return $placeName;
            },
            'attachments' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $data = [];
//                $file = OCMigrationOpencity::getMapperHelper('curriculum')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
//                if (!empty($file)){
//                    $data[] = $file;
//                }
                return implode(PHP_EOL, $data);
            },
            'more_information' => OCMigrationOpencity::getMapperHelper('nota'),
            'has_logo___name' => OCMigrationOpencity::getMapperHelper('image/name'),
            'has_logo___url' => OCMigrationOpencity::getMapperHelper('image/url'),
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
            'alt_name' => OCMigrationOpencity::getMapperHelper('org_acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigrationOpencity::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Area'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigrationOpencity::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigrationOpencity::getMapperHelper('ipacode'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
        ];
    }

    protected function getOpencityFieldMapperFromOrganizationalArea(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => OCMigrationOpencity::getMapperHelper('org_acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigrationOpencity::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Area'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigrationOpencity::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigrationOpencity::getMapperHelper('a_o_o_identifier'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
        ];
    }

    protected function getOpencityFieldMapperFromOffice(): array
    {
        return [
            'legal_name' => false,
            'alt_name' => OCMigrationOpencity::getMapperHelper('acronym'),
            'topics' => false,
            'abstract' => false,
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => OCMigrationOpencity::getMapperHelper('is_part_of'),
            'type' => function(){ return 'Ufficio'; },
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => OCMigrationOpencity::getMapperHelper('allegati'),
            'more_information' => false,
            'identifier' => OCMigrationOpencity::getMapperHelper('office_identifier'),
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
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
            'type' => OCMigrationOpencity::getMapperHelper('type_political_body'),
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => false,
            'more_information' => false,
            'identifier' => false,
            'tax_code_e_invoice_service' => false,
            'has_logo___name' => false,
            'has_logo___url' => false,
        ];
    }

    public function toSpreadsheet(): array
    {
        return [
            'Identificativo unità organizzativa*' => $this->attribute('_id'),
            'Nome dell\'unità organizzativa*' => $this->attribute('legal_name'),
            'Descrizione breve*' => $this->attribute('abstract'),
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
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        return new static();
    }

    public static function getImportPriority(): int
    {
        return 30;
    }

    public function generatePayload(): array
    {
        return [];
    }
}