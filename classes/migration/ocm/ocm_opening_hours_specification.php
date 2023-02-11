<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_opening_hours_specification extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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
        'valid_from',
        'valid_through',
        'note',
        'de_note',
        'stagionalita',
        'closure___reason',
        'closure___day',
        'opening_hours___monday',
        'opening_hours___tuesday',
        'opening_hours___wednesday',
        'opening_hours___thursday',
        'opening_hours___friday',
        'opening_hours___saturday',
        'opening_hours___sunday',
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
            'valid_from' => false,
            'valid_through' => false,
            'note' => function(Content $content){
                return $content->data['ita-IT']['note']['content'] ?? '';
            },
            'de_note' => function(Content $content){
                return $content->data['ger-DE']['note']['content'] ?? '';
            },
            'stagionalita' => false,
            'closure___reason' => OCMigration::getMapperHelper('closure/reason'),
            'closure___day' => OCMigration::getMapperHelper('closure/day'),
            'opening_hours___monday' => OCMigration::getMapperHelper('opening_hours/monday'),
            'opening_hours___tuesday' => OCMigration::getMapperHelper('opening_hours/tuesday'),
            'opening_hours___wednesday' => OCMigration::getMapperHelper('opening_hours/wednesday'),
            'opening_hours___thursday' => OCMigration::getMapperHelper('opening_hours/thursday'),
            'opening_hours___friday' => OCMigration::getMapperHelper('opening_hours/friday'),
            'opening_hours___saturday' => OCMigration::getMapperHelper('opening_hours/saturday'),
            'opening_hours___sunday' => OCMigration::getMapperHelper('opening_hours/sunday'),
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Orari';
    }

    public function getStagionalita()
    {
        $var = $this->attribute('stagionalita');
        if (empty($var)){
            $var = 'Unico';
        }

        return $var;
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore*";
    }

    public static function getColumnName(): string
    {
        return "Nome*";
    }

    public function toSpreadsheet(): array
    {
        return [
            'Identificatore*' => $this->attribute('_id'),
            'Nome*' => $this->attribute('name'),
            'Name* [de]' => $this->attribute('de_name'),
            'Valido dal*' => $this->attribute('valid_from'),
            'Valido fino al' => $this->attribute('valid_through'),
            'Note' => $this->attribute('note'),
            'Stagionalità*' => $this->getStagionalita(),
            'Lunedì' => $this->attribute('opening_hours___monday'),
            'Martedì' => $this->attribute('opening_hours___tuesday'),
            'Mercoledì' => $this->attribute('opening_hours___wednesday'),
            'Giovedì' => $this->attribute('opening_hours___thursday'),
            'Venerdì' => $this->attribute('opening_hours___friday'),
            'Sabato' => $this->attribute('opening_hours___saturday'),
            'Domenica' => $this->attribute('opening_hours___sunday'),
            'Giorni di chiusura' => $this->attribute('closure___day'),
            'Motivo di chiusura' => $this->attribute('closure___reason'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function getDateValidationHeaders(): array
    {
        return [
            'Valido dal*',
            'Valido fino al',
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            'Note',
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Stagionalità*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('stagionalita'),
            ],
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['Identificatore*']);
        $item->setAttribute('name', $row['Nome*']);
        $item->setAttribute('valid_from', $row['Valido dal*']);
        $item->setAttribute('valid_through', $row['Valido fino al']);
        $item->setAttribute('note', $row['Note']);
        $item->setAttribute('stagionalita', $row['Stagionalità*']);
        $item->setAttribute('opening_hours___monday', $row['Lunedì']);
        $item->setAttribute('opening_hours___tuesday', $row['Martedì']);
        $item->setAttribute('opening_hours___wednesday', $row['Mercoledì']);
        $item->setAttribute('opening_hours___thursday', $row['Giovedì']);
        $item->setAttribute('opening_hours___friday', $row['Venerdì']);
        $item->setAttribute('opening_hours___saturday', $row['Sabato']);
        $item->setAttribute('opening_hours___sunday', $row['Domenica']);
        $item->setAttribute('closure___day', $row['Giorni di chiusura']);
        $item->setAttribute('closure___reason', $row['Motivo di chiusura']);

        return $item;
    }

    public static function getImportPriority(): int
    {
        return 0;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function generatePayload(): array
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('opening_hours_specification');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->discoverParentNode());
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        if ($this->formatDate($this->attribute('valid_from'))) {
            $payload->setData($locale, 'valid_from', $this->formatDate($this->attribute('valid_from')));
        }else{
            $payload->setData($locale, 'valid_from', date('c'));
        }
        if ($this->formatDate($this->attribute('valid_through'))) {
            $payload->setData($locale, 'valid_through', $this->formatDate($this->attribute('valid_through')));
        }
        $payload->setData($locale, 'note', $this->attribute('note'));
        if (empty($this->attribute('stagionalita'))){
            $payload->setData($locale, 'stagionalita', 'Unico');
        }else{
            $payload->setData($locale, 'stagionalita', $this->attribute('stagionalita'));
        }


        $monday = explode(PHP_EOL, $this->attribute('opening_hours___monday'));
        $tuesday = explode(PHP_EOL, $this->attribute('opening_hours___tuesday'));
        $wednesday = explode(PHP_EOL, $this->attribute('opening_hours___wednesday'));
        $thursday = explode(PHP_EOL, $this->attribute('opening_hours___thursday'));
        $friday = explode(PHP_EOL, $this->attribute('opening_hours___friday'));
        $saturday = explode(PHP_EOL, $this->attribute('opening_hours___saturday'));
        $sunday = explode(PHP_EOL, $this->attribute('opening_hours___sunday'));
        $rowCount = max(
            count($monday),
            count($tuesday),
            count($wednesday),
            count($thursday),
            count($friday),
            count($saturday),
            count($sunday)
        );
        $contacts = [];
        for ($x = 0; $x <= $rowCount; $x++) {
            $contact = [
                'monday' => $monday[$x] ?? '',
                'tuesday' => $tuesday[$x] ?? '',
                'wednesday' => $wednesday[$x] ?? '',
                'thursday' => $thursday[$x] ?? '',
                'friday' => $friday[$x] ?? '',
                'saturday' => $saturday[$x] ?? '',
                'sunday' => $sunday[$x] ?? '',
            ];
            if (!$this->isEmptyArray($contact)) {
                $contacts[$x] = $contact;
            }
        }
        if (!empty($contacts)) {
            $payload->setData($locale, 'opening_hours', $contacts);
        }

        $closureDay = explode(PHP_EOL, $this->attribute('closure___day'));
        $closureReason = explode(PHP_EOL, $this->attribute('closure___reason'));
        $rowCount = max(
            count($closureDay),
            count($closureReason)
        );

        $closures = [];
        for ($x = 0; $x <= $rowCount; $x++) {
            $closure = [
                'day' => $closureDay[$x] ?? '',
                'reason' => $closureReason[$x] ?? '',
            ];
            if (!$this->isEmptyArray($contact)) {
                $closures[$x] = $closure;
            }
        }
        if (!empty($closures)) {
            $payload->setData($locale, 'closure', $closures);
        }

        return $payload->getArrayCopy();
    }

    /**
     * @return int
     * @throws Exception
     */
    private function discoverParentNode(): int
    {
        return $this->getOrariStruttureParentNode(); //@todo
    }

    /**
     * @return int
     * @throws Exception
     */
    private function getOrariStruttureParentNode(): int
    {
        return $this->getNodeIdFromRemoteId('fa52241c11d26c8aa24ab93813995e10');
    }

    /**
     * @return int
     * @throws Exception
     */
    private function getOrariServiziParentNode(): int
    {
        return $this->getNodeIdFromRemoteId('f10a85a4ddd1810f10f655785dd84e75');
    }
}