<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_place extends OCMPersistentObject implements ocm_interface
{
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
        'de_name',
        'de_accessibility',
        'de_has_address',
        'de_abstract',
        'de_description',
        'en_name',
        'en_accessibility',
        'en_has_address',
        'en_abstract',
        'en_description',
    ];

    public static function getSpreadsheetTitle()
    {
        return 'Luoghi';
    }

    public static function getIdColumnLabel()
    {
        return "Identificatore luogo*";
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = [])
    {
        if ($node->classIdentifier() === 'servizio_sul_territorio') {
            return $this->fromNode($node, $this->getComunwebServizioSulTerritorioMapper(), $options);
        } elseif ($node->classIdentifier() === 'luogo') {
            return $this->fromNode($node, $this->getComunwebLuogoMapper(), $options);
        }

        return $this->fromNode($node, [], $options);
    }

    protected function getComunwebServizioSulTerritorioMapper()
    {
        return [
            'name' => function (Content $content) {
                return isset($content->data['ita-IT']['titolo']['content']) ? trim(
                    $content->data['ita-IT']['titolo']['content']
                ) : '';
            },
            'de_name' => function (Content $content) {
                return isset($content->data['ger-DE']['titolo']['content']) ? $content->data['ger-DE']['titolo']['content'] : '';
            },
            'caption' => function (Content $content) {
                return isset($content->data['ita-IT']['abstract']['content']) ? $content->data['ita-IT']['abstract']['content'] : '';
            },
            'de_caption' => function (Content $content) {
                return isset($content->data['ger-DE']['abstract']['content']) ? $content->data['ger-DE']['abstract']['content'] : '';
            },
            'en_name' => function (Content $content) {
                return isset($content->data['eng-GB']['titolo']['content']) ? $content->data['eng-GB']['titolo']['content'] : '';
            },
            'en_caption' => function (Content $content) {
                return isset($content->data['eng-GB']['abstract']['content']) ? $content->data['eng-GB']['abstract']['content'] : '';
            },
            'description' => OCMigration::getMapperHelper('descrizione'),
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'has_address' => function (
                Content $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ) {
                $h = OCMigration::getMapperHelper('geo');
                $address = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
                $indirizzoField = $firstLocalizedContentData['indirizzo'];
                $indirizzo = $indirizzoField['content'];
                if (!empty($indirizzo)) {
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
            ) {
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $h = OCMigration::getMapperHelper('galleria');
                $galleria = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
                $h = OCMigration::getMapperHelper('image/name');
                $imageName = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
                $h = OCMigration::getMapperHelper('image/url');
                $imageUrl = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
                if (!empty($imageUrl)) {
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
            'help' => function (Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options) {
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $className = $object->className();
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $hoursName = false;
                $h = OCMigration::getMapperHelper('orario');
                $orario = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
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
                foreach (['telefono', 'url', 'email',] as $identifier) {
                    if (isset($dataMap[$identifier])) {
                        $type = $identifier;
                        if ($identifier == 'telefono') {
                            $type = 'Telefono';
                        } elseif (stripos($identifier, 'email') !== false) {
                            $type = 'Email';
                        } elseif ($identifier == 'url') {
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

    protected function getComunwebLuogoMapper()
    {
        return [
            'name' => function (Content $content) {
                return isset($content->data['ita-IT']['title']['content']) ? trim(
                    $content->data['ita-IT']['title']['content']
                ) : '';
            },
            'de_name' => function (Content $content) {
                return isset($content->data['ger-DE']['title']['content']) ? $content->data['ger-DE']['title']['content'] : '';
            },
            'caption' => function (Content $content) {
                return isset($content->data['ita-IT']['abstract']['content']) ? $content->data['ita-IT']['abstract']['content'] : '';
            },
            'de_caption' => function (Content $content) {
                return isset($content->data['ger-DE']['abstract']['content']) ? $content->data['ger-DE']['abstract']['content'] : '';
            },
            'en_name' => function (Content $content) {
                return isset($content->data['eng-GB']['titolo']['content']) ? $content->data['eng-GB']['titolo']['content'] : '';
            },
            'en_caption' => function (Content $content) {
                return isset($content->data['eng-GB']['abstract']['content']) ? $content->data['eng-GB']['abstract']['content'] : '';
            },
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'has_address' => function (
                Content $content,
                $firstLocalizedContentData,
                $firstLocalizedContentLocale,
                $options
            ) {
                $h = OCMigration::getMapperHelper('geo');
                $address = $h(
                    $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale,
                    $options
                );
                $indirizzoField = $firstLocalizedContentData['indirizzo'];
                $indirizzo = $indirizzoField['content'];
                if (!empty($indirizzo)) {
                    $address = json_decode($address, true);
                    $address['address'] = $indirizzo;
                    $address = json_encode($address);
                }

                return $address;
            },
            'image' => OCMigration::getMapperHelper('galleria'),
            'help' => function (Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options) {
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $dataMap = $object instanceof eZContentObject ? $object->dataMap() : [];
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
                $data = [];
                foreach (['telefono', 'url', 'email',] as $identifier) {
                    if (isset($dataMap[$identifier])) {
                        $type = $identifier;
                        if ($identifier == 'telefono') {
                            $type = 'Telefono';
                        } elseif (stripos($identifier, 'email') !== false) {
                            $type = 'Email';
                        } elseif ($identifier == 'url') {
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

    public function toSpreadsheet()
    {
        $address = json_decode($this->attribute('has_address'), true);
        $deAddress = json_decode($this->attribute('de_has_address'), true);
        $enAddress = json_decode($this->attribute('en_has_address'), true);
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

            'Ortsname* [de]' => $this->attribute('de_name'),
            'Zugriffsmodus* [de]' => $this->attribute('de_accessibility'),
            'Adresse* [de]' => empty($deAddress['address']) ? $address['address'] : $deAddress['address'],
            'Kurze Beschreibung* [de]' => $this->attribute('de_abstract'),
            'Erweiterte Beschreibung [de]' => $this->attribute('de_description'),

            'Name* [en]' => $this->attribute('en_name'),
            'Accessibility* [en]' => $this->attribute('en_accessibility'),
            'Address* [en]' => empty($enAddress['address']) ? $address['address'] : $enAddress['address'],
            'Abstarct* [en]' => $this->attribute('en_abstract'),
            'Description [en]' => $this->attribute('en_description'),
        ];
    }

    public static function fromSpreadsheet($row) 
    {
        $address = '';
        list($latitude, $longitude) = explode(' ', $row["Latitudine e longitudine*"]);
        if (($latitude + $longitude) > 0) {
            $address = json_encode([
                'address' => $row['Indirizzo*'],
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }

        $item = new static();
        $item->setAttribute('_id', $row["Identificatore luogo*"]);
        $item->setAttribute('name', $row["Nome del luogo*"]);
        $item->setAttribute('topics', $row["Argomento*"]);
        $item->setAttribute('type', $row["Tipo di luogo*"]);
        $item->setAttribute('accessibility', $row["Modalità di accesso*"]);
        $item->setAttribute('help', $row["Punti di contatto*"]);
        $item->setAttribute('abstract', $row["Descrizione breve*"]);
        if (isset($row["Descrizione estesa "])) {
            $item->setAttribute('description', $row["Descrizione estesa "]);
        }
        if (isset($row["Descrizione estesa"])) {
            $item->setAttribute('description', $row["Descrizione estesa"]);
        }
        $item->setAttribute('image', $row["Immagini*"]);
        $item->setAttribute('has_address', $address);
        $item->setAttribute('has_video', $row["Link video"]);
        $item->setAttribute('opening_hours_specification', $row["Orario per il pubblico"]);
        $item->setAttribute('has_office', $row["Struttura responsabile"]);
        $item->setAttribute('more_information', $row["Ulteriori informazioni"]);
        $item->setAttribute('identifier', $row["Codice luogo"]);

        $item->setAttribute('de_name', $row['Ortsname* [de]']);
        $item->setAttribute('de_accessibility', $row['Zugriffsmodus* [de]']);
        $item->setAttribute('de_has_address', $row['Adresse* [de]']);
        $item->setAttribute('de_abstract', $row['Kurze Beschreibung* [de]']);
        $item->setAttribute('de_description', $row['Erweiterte Beschreibung [de]']);

        $item->setAttribute('en_name', $row['Name* [en]']);
        $item->setAttribute('en_accessibility', $row['Accessibility* [en]']);
        $item->setAttribute('en_address', $row['Address* [en]']);
        $item->setAttribute('en_abstract', $row['Abstarct* [en]']);
        $item->setAttribute('en_description', $row['Description [en]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('place');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode(
            $this->getNodeIdFromRemoteId('all-places')
        );
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'alternative_name', $this->attribute('alternative_name'));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'type', $this->attributeArray('type'));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'accessibility', $this->attribute('accessibility'));
        $payload->setData($locale, 'contains_place', ocm_online_contact_point::getIdListByName($this->attribute('contains_place')));
        $images = ocm_image::getIdListByName($this->attribute('image'));
        if (empty($images) || strtolower($this->attribute('image')) === 'default'){
            $images = ocm_image::getDefaultImage('place');
        }
        $payload->setData($locale, 'image', $images);
        $payload->setData($locale, 'video', $this->attribute('video'));
        $payload->setData($locale, 'has_video', $this->attribute('has_video'));
        $payload->setData($locale, 'has_address', json_decode($this->attribute('has_address'), true));
        $payload->setData($locale, 'opening_hours_specification', ocm_opening_hours_specification::getIdListByName($this->attribute('opening_hours_specification')));
        $payload->setData($locale, 'help', ocm_online_contact_point::getIdListByName($this->attribute('help')));
        $payload->setData($locale, 'more_information', $this->attribute('more_information'));
        $payload->setData($locale, 'identifier', $this->attribute('identifier'));
        $deAddress = json_decode($this->attribute('has_address'), true);
        $deAddress['address'] = $this->attribute('de_has_address');
        $enAddress['address'] = $this->attribute('en_has_address');
        $payload = $this->appendTranslationsToPayloadIfNeeded($payload, ['de_has_address' => $deAddress, 'en_has_address' => $enAddress]);
        $payloads = [self::getImportPriority() => $payload];

        $offices = ocm_organization::getIdListByName($this->attribute('has_office'), 'legal_name');
        if (count($offices) > 0) {
            $payload2 = clone $payload;
            $payload2->unSetData();
            $payload2->setData($locale, 'has_office', $offices);
            if (in_array('ger-DE', $payload->getMetadaData('languages'))){
                $payload2->setData('ger-DE', 'has_office', $offices);
            }
            if (in_array('eng-GB', $payload->getMetadaData('languages'))){
                $payload2->setData('eng-GB', 'has_office', $offices);
            }
            $payloads[ocm_organization::getImportPriority()+1] = $payload2;
        }

        return $payloads;
    }

    public static function getColumnName()
    {
        return 'Nome del luogo*';
    }

    public static function getInternalLinkConditionalFormatHeaders()
    {
        return [
            'Descrizione breve*',
            'Descrizione estesa',
            'Descrizione estesa ',
            "Ulteriori informazioni",
        ];
    }

    public static function getMax160CharConditionalFormatHeaders()
    {
        return [
            "Descrizione breve*",
        ];
    }

    public static function getRangeValidationHash()
    {
        return [
            'Punti di contatto*' => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef(),
            ],
            'Immagini*' => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef(),
            ],
            "Argomento*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Orario per il pubblico" => [
                'strict' => true,
                'ref' => ocm_opening_hours_specification::getRangeRef(),
            ],
            "Struttura responsabile" => [
                'strict' => true,
                'ref' => ocm_organization::getRangeRef(),
            ],
            "Tipo di luogo*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('luoghi'),
            ],
        ];
    }

    public static function getImportPriority()
    {
        return 20;
    }
}