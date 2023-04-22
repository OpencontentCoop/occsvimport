<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_online_contact_point extends OCMPersistentObject implements ocm_interface
{
    public static function canPush(): bool
    {
        return true;
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static $fields = [
        'name',
        'de_name',
        'contact',
        'phone_availability_time',
        'note',
        'de_note',
    ];

    protected function getOpencityFieldMapper(): array
    {
        return [
            'name' => function(Content $content){
                return $content->data['ita-IT']['name']['content'] ?? '';
            },
            'de_name' => function(Content $content){
                return $content->data['ger-DE']['name']['content'] ?? '';
            },
            'contact' => OCMigration::getMapperHelper('contact'),
            'phone_availability_time' => OCMigration::getMapperHelper('phone_availability_time'),
            'note' => false,
            'de_note' => function(Content $content){
                return $content->data['ger-DE']['note']['content'] ?? '';
            },
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Punti di contatto';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore punto di contatto*";
    }

    public static function getColumnName(): string
    {
        return "Titolo punto di contatto*";
    }

    public function toSpreadsheet(): array
    {
        $contacts = json_decode($this->attribute('contact'), true);
        $data = [
            'Identificatore punto di contatto*' => $this->attribute('_id'),
            'Titolo punto di contatto*' => $this->attribute('name'),
            'Kontakttitel* [de]' => $this->attribute('de_name'),
            'Orari disponibilità telefonica' => $this->attribute('phone_availability_time'),
        ];

        for ($x = 0; $x <= 5; $x++) {
            $indexLabel = $x + 1;
            $indexLabelRequired = $indexLabel;
            if ($indexLabel === 1) $indexLabelRequired = "1*";
            $data['Tipologia di contatto ' . $indexLabelRequired] = $contacts['ita-IT'][$x]['type'] ?? '';
            $data['Contatto ' . $indexLabelRequired] = isset($contacts['ita-IT'][$x]['value']) ? $this->formatContentValue($contacts['ita-IT'][$x]['value']) : '';
            $data['Tipo di contatto ' . $indexLabel] = $contacts['ita-IT'][$x]['contact'] ?? '';
            $data['Kontakt ' . $indexLabelRequired . ' [de]'] = $contacts['ger-DE'][$x]['value'] ?? $data['Contatto ' . $indexLabelRequired];
        }

        $data['Note'] = $this->attribute('note');
        $data['Hinweise (de)'] = $this->attribute('de_note');

        $data['Pagina contenitore'] = $this->attribute('_parent_name');
        $data['Url originale'] = $this->attribute('_original_url');

        return $data;
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['Identificatore punto di contatto*']);
        $item->setAttribute('name', $row['Titolo punto di contatto*']);
        $item->setAttribute('de_name', $row['Kontakttitel* [de]']);
        $item->setAttribute('phone_availability_time', $row['Orari disponibilità telefonica']);

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
                $contacts['ita-IT'][$x] = $contacts['ger-DE'][$x] = $contact;
                if (isset($row['Kontakt ' . $indexLabelRequired . ' [de]'])) {
                    $contacts['ger-DE'][$x]['value'] = $row['Kontakt ' . $indexLabelRequired . ' [de]'];
                }
            }
        }
        $item->setAttribute('contact', json_encode($contacts));

        $item->setAttribute('note', $row['Note'] ?? '');
        $item->setAttribute('de_note', $row['Hinweise (de)'] ?? '');

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getRangeValidationHash(): array
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

    public static function getInternalLinkConditionalFormatHeaders(): array
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

    public static function getImportPriority(): int
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
        } elseif (!empty($contacts)){
            $payload->setData($locale, 'contact', $contacts);
        }

        return $payload;
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        return parent::getIdListByName($name, $field, $tryWithPrefix);
    }

}