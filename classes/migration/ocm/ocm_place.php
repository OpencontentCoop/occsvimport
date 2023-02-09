<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_place extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'alternative_name',
        'topics',
        'type',
        'abstract',
        'description',
        'contains_place',
        'image',
        'video',
        'has_video',
        'accessibility',
        'has_address',
        'opening_hours_specification',
        'help',
        'has_office',
        'more_information',
        'identifier',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Luoghi';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore luogo*";
    }

    protected function getComunwebFieldMapper(): array
    {
        return [
            'name' => function(Content $content){
                return $content->data['ita-IT']['title']['content'] ?? '';
            },
            'de_name' => function(Content $content){
                return $content->data['ger-DE']['title']['content'] ?? '';
            },
            'caption' => function(Content $content){
                return $content->data['ita-IT']['abstract']['content'] ?? '';
            },
            'de_caption' => function(Content $content){
                return $content->data['ger-DE']['abstract']['content'] ?? '';
            },
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'geo' => false,
            'image' => OCMigration::getMapperHelper('galleria'),
            'help' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
                $data = [];
                foreach (['telefono', 'url', 'email',] as $identifier){
                    if (isset($dataMap[$identifier])){
                        $type = $identifier;
                        if ($identifier == 'telefono'){
                            $type = 'Telefono';
                        }elseif (stripos($identifier, 'email') !== false){
                            $type = 'Email';
                        }elseif ($identifier == 'url'){
                            $type = 'Sito web';
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
            },
        ];
    }

    public function toSpreadsheet(): array
    {
        $address = json_decode($this->attribute('has_address'), true);
        return [
            "Identificatore luogo*" => $this->attribute('_id'),
            "Nome del luogo*" => $this->attribute('name'),
            "Argomento*" => $this->attribute('topics'),
            "Tipo di luogo*" => $this->attribute('type'),
            "Modalità di accesso*" => $this->attribute('accessibility'),
            "Indirizzo*" => $address['address'],
            "Latitudine e longitudine*" => $address['latitude'] . ' ' . $address['longitude'],
            "Punti di contatto*" => $this->attribute('help'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "Descrizione estesa " => $this->attribute('description'),
            "Immagini*" => $this->attribute('image'),
            "Link video" => $this->attribute('has_video'),
            "Orario per il pubblico" => $this->attribute('opening_hours_specification'),
            "Struttura responsabile" => $this->attribute('has_office'),
            "Ulteriori informazioni" => $this->attribute('more_information'),
            "Codice luogo" => $this->attribute('identifier'),
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
        return 20;
    }

    public function generatePayload(): array
    {
        return [];
    }
}