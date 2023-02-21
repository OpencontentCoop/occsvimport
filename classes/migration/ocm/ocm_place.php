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

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        if ($node->classIdentifier() === 'servizio_sul_territorio'){
            return $this->fromNode($node, $this->getComunwebServizioSulTerritorioMapper(), $options);
        }elseif ($node->classIdentifier() === 'luogo') {
            return $this->fromNode($node, $this->getComunwebLuogoMapper(), $options);
        }

        return $this->fromNode($node, [], $options);
    }

    protected function getComunwebServizioSulTerritorioMapper(): array
    {
        return [
            'name' => function(Content $content){
                return trim($content->data['ita-IT']['titolo']['content']) ?? '';
            },
            'de_name' => function(Content $content){
                return $content->data['ger-DE']['titolo']['content'] ?? '';
            },
            'caption' => function(Content $content){
                return $content->data['ita-IT']['abstract']['content'] ?? '';
            },
            'de_caption' => function(Content $content){
                return $content->data['ger-DE']['abstract']['content'] ?? '';
            },
            'description' => OCMigration::getMapperHelper('descrizione'),
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'has_address' => function (
                Content $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ){
                $address = OCMigration::getMapperHelper('geo')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                $indirizzoField = $firstLocalizedContentData['indirizzo'];
                $indirizzo = $indirizzoField['content'];
                if (!empty($indirizzo)){
                    $address = json_decode($address, true);
                    $address['address'] = $indirizzo;
                    $address = json_encode($address);
                }

                return $address;
            },
            'type' => OCMigration::getMapperHelper('tipo_servizio_sul_territorio'),
            'image' => function (
                Content $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $galleria = OCMigration::getMapperHelper('galleria')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                $imageName = OCMigration::getMapperHelper('image/name')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                $imageUrl = OCMigration::getMapperHelper('image/url')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                if (!empty($imageUrl)){
                    $image = ocm_image::instanceBy('name', $imageName, $id . ':image');
                    $image->setAttribute('image___name', $imageName);
                    $image->setAttribute('image___url', $imageUrl);
                    $image->setNodeReference($node);
                    $image->storeThis($options['is_update']);

                    $galleria = explode(PHP_EOL, $galleria);
                    $galleria[] = $imageName;
                    $galleria = implode(PHP_EOL, $galleria);
                    $galleria = trim($galleria, PHP_EOL);
                }

                return $galleria;
            },
            'help' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $className = $object->className();
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $hoursName = false;
                $orario = OCMigration::getMapperHelper('orario')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                if (!empty($ricevimento)) {
                    $hoursId = $id . ':hours';
                    $hoursName = "Orari $className $name";
                    $hours = ocm_opening_hours_specification::instanceBy('name', $hoursName, $hoursId);
                    $hours->setAttribute('stagionalita', "Unico");
                    $hours->setAttribute('note', $orario);
                    $hours->setNodeReference($node);
                    $hours->storeThis($options['is_update']);
                }

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $className $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
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

                if ($hoursName) {
                    $contacts->setAttribute('phone_availability_time', $hoursName);
                }

                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
        ];
    }

    protected function getComunwebLuogoMapper(): array
    {
        return [
            'name' => function(Content $content){
                return trim($content->data['ita-IT']['title']['content']) ?? '';
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
            'has_address' => function (
                Content $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ){
                $address = OCMigration::getMapperHelper('geo')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                $indirizzoField = $firstLocalizedContentData['indirizzo'];
                $indirizzo = $indirizzoField['content'];
                if (!empty($indirizzo)){
                    $address = json_decode($address, true);
                    $address['address'] = $indirizzo;
                    $address = json_encode($address);
                }

                return $address;
            },
            'image' => OCMigration::getMapperHelper('galleria'),
            'help' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
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
            "Descrizione estesa" => $this->attribute('description'),
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

    public static function getColumnName(): string
    {
        return 'Nome del luogo*';
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            'Descrizione breve*',
            'Descrizione estesa',
            'Descrizione estesa ',
            "Ulteriori informazioni",
        ];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*"
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Punti di contatto*' => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            'Immagini*' => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Argomento*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Orario per il pubblico" => [
                'strict' => true,
                'ref' => ocm_opening_hours_specification::getRangeRef()
            ],
            "Struttura responsabile" => [
                'strict' => true,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Tipo di luogo*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('luoghi'),
            ],
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