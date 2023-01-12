<?php

class ocm_online_contact_point extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'contact',
        'phone_availability_time',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Punti di contatto';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore punto di contatto*";
    }

    public function toSpreadsheet(): array
    {
        $contacts = json_decode($this->attribute('contact'), true);
        $data = [
            'Identificatore punto di contatto*' => $this->attribute('_id'),
            'Titolo punto di contatto*' => $this->attribute('name'),
            'Orari disponibilità telefonica' => $this->attribute('phone_availability_time'),
        ];

        for ($x = 0; $x <= 5; $x++) {
            $indexLabel = $x + 1;
            $indexLabelRequired = $indexLabel;
            if ($indexLabel === 1) $indexLabelRequired = "1*";
            $data['Tipologia di contatto ' . $indexLabelRequired] = $contacts[$x]['type'] ?? '';
            $data['Contatto ' . $indexLabelRequired] = isset($contacts[$x]['value']) ? $this->formatContentValue($contacts[$x]['value']) : '';
            $data['Tipo di contatto ' . $indexLabel] = $contacts[$x]['contact'] ?? '';
        }

        return $data;
    }

    private function formatContentValue($value)
    {
        $string = preg_replace('/\s+/', '', $value);
        $string = str_replace('.', '', $string);
        $string = str_replace('/', '', $string);
        if (is_numeric($string)){
            $value = '(+39) ' . $value;
        }
        return $value;
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['Identificatore punto di contatto*']);
        $item->setAttribute('name', $row['Titolo punto di contatto*']);
        $item->setAttribute('phone_availability_time', $row['Orari disponibilità telefonica']);

        $contacts = [];
        for ($x = 0; $x <= 5; $x++) {
            $indexLabel = $x + 1;
            $indexLabelRequired = $indexLabel;
            if ($indexLabel === 1) $indexLabelRequired = "1*";
            if (
                isset($row['Tipologia di contatto ' . $indexLabelRequired], $row['Contatto ' . $indexLabelRequired], $row['Tipo di contatto ' . $indexLabel]) &&
                (!empty($row['Tipologia di contatto ' . $indexLabelRequired]) || !empty($row['Contatto ' . $indexLabelRequired]) || !empty($row['Tipo di contatto ' . $indexLabel]))
            ) {
                $contact = [
                    'type' => $row['Tipologia di contatto ' . $indexLabelRequired],
                    'value' => $row['Contatto ' . $indexLabelRequired],
                    'contact' => $row['Tipo di contatto ' . $indexLabel],
                ];
                $contacts[$x] = $contact;
            }
        }
        $item->setAttribute('contact', json_encode($contacts));

        return $item;
    }

    public static function getImportPriority(): int
    {
        return 10;
    }

    public function generatePayload(): array
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('online_contact_point');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('punti_di_contatto'));
        $payload->setLanguages([$locale]);
        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'contact', json_decode($this->attribute('contact'), true));
        $openingHours = [];
        $openingHoursNames = explode(PHP_EOL, $this->attribute('phone_availability_time'));
        if (!$this->isEmptyArray($openingHoursNames)){
            $list = ocm_opening_hours_specification::fetchObjectList(
                ocm_opening_hours_specification::definition(), ['_id'],
                ['trim(name)' => [$openingHoursNames]]
            );
            $openingHours = array_column($list, '_id');
        }

        if (!empty($openingHours)) {
            $payload->setData($locale, 'phone_availability_time', $openingHours);
        }

        return $payload->getArrayCopy();
    }
}