<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_online_contact_point extends OCMPersistentObject implements ocm_interface
{
    public static function canPush()
    {
        return true;
    }

    public static function canExport()
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static $fields = [
        'name',
        'de_name',
        'en_name',
        'contact',
        'phone_availability_time',
        'note',
        'de_note',
        'en_note',
    ];

    protected function getOpencityFieldMapper()
    {
        return [
            'name' => function(Content $content){
                return isset($content->data['ita-IT']['name']['content']) ? $content->data['ita-IT']['name']['content'] : '';
            },
            'de_name' => function(Content $content){
                return isset($content->data['ger-DE']['name']['content']) ? $content->data['ger-DE']['name']['content'] : '';
            },
            'en_name' => function(Content $content){
                return isset($content->data['eng-GB']['name']['content']) ? $content->data['eng-GB']['name']['content'] : '';
            },
            'contact' => OCMigration::getMapperHelper('contact'),
            'phone_availability_time' => OCMigration::getMapperHelper('phone_availability_time'),
            'note' => false,
            'de_note' => function(Content $content){
                return isset($content->data['ger-DE']['note']['content']) ? $content->data['ger-DE']['note']['content'] : '';
            },
            'en_note' => function(Content $content){
                return isset($content->data['eng-GB']['note']['content']) ? $content->data['eng-GB']['note']['content'] : '';
            },
        ];
    }

    public static function getSpreadsheetTitle()
    {
        return 'Punti di contatto';
    }

    public static function getIdColumnLabel()
    {
        return "Identificatore punto di contatto*";
    }

    public static function getColumnName()
    {
        return "Titolo punto di contatto*";
    }

    public function toSpreadsheet()
    {
        $contacts = json_decode($this->attribute('contact'), true);
        $data = [
            'Identificatore punto di contatto*' => $this->attribute('_id'),
            'Titolo punto di contatto*' => $this->attribute('name'),
            'Kontakttitel* [de]' => $this->attribute('de_name'),
            'Contact title* [en]' => $this->attribute('en_name'),
            'Orari disponibilità telefonica' => $this->attribute('phone_availability_time'),
        ];

        for ($x = 0; $x <= 5; $x++) {
            $indexLabel = $x + 1;
            $indexLabelRequired = $indexLabel;
            if ($indexLabel === 1) $indexLabelRequired = "1*";
            $data['Tipologia di contatto ' . $indexLabelRequired] = isset($contacts['ita-IT'][$x]['type']) ? $contacts['ita-IT'][$x]['type'] : '';
            $data['Contatto ' . $indexLabelRequired] = isset($contacts['ita-IT'][$x]['value']) ? $this->formatContentValue($contacts['ita-IT'][$x]['value']) : '';
            $data['Tipo di contatto ' . $indexLabel] = isset($contacts['ita-IT'][$x]['contact']) ? $contacts['ita-IT'][$x]['contact'] : '';
            $data['Kontakt ' . $indexLabelRequired . ' [de]'] = isset($contacts['ger-DE'][$x]['value']) ? $contacts['ger-DE'][$x]['value'] : $data['Contatto ' . $indexLabelRequired];
            $data['Contact ' . $indexLabelRequired . ' [en]'] = isset($contacts['eng_GB'][$x]['value']) ? $contacts['eng_GB'][$x]['value'] : $data['Contatto ' . $indexLabelRequired];
        }

        $data['Note'] = $this->attribute('note');
        $data['Hinweise (de)'] = $this->attribute('de_note');
        $data['Notes (en)'] = $this->attribute('en_note');

        $data['Pagina contenitore'] = $this->attribute('_parent_name');
        $data['Url originale'] = $this->attribute('_original_url');

        return $data;
    }

    public static function fromSpreadsheet($row) 
    {
        $item = new static();
        $item->setAttribute('_id', $row['Identificatore punto di contatto*']);
        $item->setAttribute('name', $row['Titolo punto di contatto*']);
        $item->setAttribute('de_name', $row['Kontakttitel* [de]']);
        $item->setAttribute('en_name', $row['Contact title* [en]']);
        $item->setAttribute('phone_availability_time', $row['Orari disponibilità telefonica']);

        $typeMap = [
            'ita-IT' => [
                'Telefono' => 'Telefono',
                'E-mail' => 'E-mail',
                'Fax' => 'Fax',
                'PEC' => 'PEC',
                'Sito web' => 'Sito web',
                'Cellulare' => 'Cellulare',
            ],
            'ger-DE' => [
                'Telefono' => 'Telefon',
                'E-mail' => 'E-Mail',
                'Fax' => 'Fax',
                'PEC' => 'PEC',
                'Sito web' => 'Web',
                'Cellulare' => 'Mobiltelefon',
            ],
            'eng-GB' => [
                'Telefono' => 'Phone',
                'E-mail' => 'E-mail',
                'Fax' => 'Fax',
                'PEC' => 'PEC',
                'Sito web' => 'Website',
                'Cellulare' => 'Mobile phone',
            ],
        ];

        $contacts = [];
        for ($x = 0; $x <= 5; $x++) {
            $indexLabel = $x + 1;
            $indexLabelRequired = $indexLabel;
            if ($indexLabel === 1) $indexLabelRequired = "1*";
            if (
                isset(
                    $row['Tipologia di contatto ' . $indexLabelRequired],
                    $row['Contatto ' . $indexLabelRequired],
                    $row['Tipo di contatto ' . $indexLabel]
                ) && (
                    !empty($row['Tipologia di contatto ' . $indexLabelRequired])
                    || !empty($row['Contatto ' . $indexLabelRequired])
                    || !empty($row['Tipo di contatto ' . $indexLabel])
                )
            ) {
                $contact = [
                    'type' => $row['Tipologia di contatto ' . $indexLabelRequired],
                    'value' => $row['Contatto ' . $indexLabelRequired],
                    'contact' => $row['Tipo di contatto ' . $indexLabel],
                ];
                $contacts['ita-IT'][$x] = $contacts['ger-DE'][$x] = $contacts['eng-GB'][$x] = $contact;
                if (isset($row['Kontakt ' . $indexLabelRequired . ' [de]'])) {
                    if (isset($typeMap['ger-DE'][$contacts['ger-DE'][$x]['type']])) {
                        $contacts['ger-DE'][$x]['type'] = $typeMap['ger-DE'][$contacts['ger-DE'][$x]['type']];
                    }
                    $contacts['ger-DE'][$x]['value'] = $row['Kontakt ' . $indexLabelRequired . ' [de]'];
                }
                if (isset($row['Contact ' . $indexLabelRequired . ' [en]'])) {
                    if (isset($typeMap['eng-GB'][$contacts['eng-GB'][$x]['type']])) {
                        $contacts['eng-GB'][$x]['type'] = $typeMap['eng-GB'][$contacts['eng-GB'][$x]['type']];
                    }
                    $contacts['eng-GB'][$x]['value'] = $row['Contact ' . $indexLabelRequired . ' [en]'];
                }
            }
        }
        $item->setAttribute('contact', json_encode($contacts));

        $item->setAttribute('note', isset($row['Note']) ? $row['Note'] : '');
        $item->setAttribute('de_note', isset($row['Hinweise (de)']) ? $row['Hinweise (de)'] : '');
        $item->setAttribute('en_note', isset($row['Notes (de)']) ? $row['Notes (de)'] : '');

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getRangeValidationHash()
    {
        $contactType = [
            'strict' => true,
            'ref' => self::getVocabolaryRangeRef('contatti'),
        ];
        $contact = [
            'strict' => true,
            'ref' => self::getVocabolaryRangeRef('tipi-contatto'),
        ];
        return [
            'Orari disponibilità telefonica' => [
                'strict' => false,
                'ref' => ocm_opening_hours_specification::getRangeRef()
            ],
            'Tipologia di contatto 1*' => $contactType,
            'Tipologia di contatto 2' => $contactType,
            'Tipologia di contatto 3' => $contactType,
            'Tipologia di contatto 4' => $contactType,
            'Tipologia di contatto 5' => $contactType,
            'Tipo di contatto 1' => $contact,
            'Tipo di contatto 2' => $contact,
            'Tipo di contatto 3' => $contact,
            'Tipo di contatto 4' => $contact,
            'Tipo di contatto 5' => $contact,
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders()
    {
        return [];
    }

    private function formatContentValue($value)
    {
        $string = preg_replace('/\s+/', '', $value);
        $string = str_replace('.', '', $string);
        $string = str_replace('/', '', $string);
        if (is_numeric($string)){
            $value = '(+39) ' . $value;
        }
        if (strpos($string, '+49') === 0){
            $value = str_replace('+49', '(+49)', $value);
        }
        return $value;
    }

    public static function getImportPriority()
    {
        return 10;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('online_contact_point');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('punti_di_contatto'));
        $payload->setLanguages([$locale]);
        $payload->setData($locale, 'name', $this->attribute('name'));
        $openingHours = ocm_opening_hours_specification::getIdListByName($this->attribute('phone_availability_time'));
        if (!empty($openingHours)) {
            $payload->setData($locale, 'phone_availability_time', $openingHours);
        }

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);
        $contacts = json_decode($this->attribute('contact'), true);
        if (isset($contacts['ita-IT'])) {
            $payload->setData($locale, 'contact', $contacts['ita-IT']);
            if (isset($contacts['ger-DE']) && in_array('ger-DE', $payload->getMetadaData('languages'))){
                $payload->setData('ger-DE', 'contact', $contacts['ger-DE']);
            }
            if (isset($contacts['eng-GB']) && in_array('eng-GB', $payload->getMetadaData('languages'))){
                $payload->setData('eng-GB', 'contact', $contacts['eng-GB']);
            }
        } elseif (!empty($contacts)){
            $payload->setData($locale, 'contact', $contacts);
        }

        return $payload;
    }

    public static function getIdListByName($name, $field = 'name',$tryWithPrefix = null)
    {
        return parent::getIdListByName($name, $field, $tryWithPrefix);
    }

}