<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_private_organization extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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
                $place = new ocm_place();
                $place->setAttribute('_id', $placeId);
                $place->setAttribute('name', $placeName);
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
            $contacts = new ocm_online_contact_point();
            $contacts->setAttribute('_id', $contactsId);
            $contacts->setAttribute('name', $contactsName);
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
        ];
    }

    public static function getColumnName(): string
    {
        return 'Nome*';
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        // TODO: Implement fromSpreadsheet() method.
    }

    public function generatePayload(): array
    {
        // TODO: Implement generatePayload() method.
    }

    public static function getImportPriority(): int
    {
        // TODO: Implement getImportPriority() method.
    }

}