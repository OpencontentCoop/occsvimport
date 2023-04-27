<?php

class ocm_channel extends OCMPersistentObject implements ocm_interface
{

    public static $fields = [
        'object',
        'has_channel_type',
        'abstract',
        'description',
        'channel_url',
        'has_cost',
        'image',
        'files',
    ];

    public static function canPush(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Canali di erogazione';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo canale*';
    }

    public static function getColumnName(): string
    {
        return 'Funzione del canale*';
    }

    public static function getSortField(): string
    {
        return 'object';
    }

    public function toSpreadsheet(): array
    {
        $costs = json_decode($this->attribute('has_cost'), true);
        $url = $this->attribute('channel_url');
        [$link, $name] = explode('|', $url);
        return [
            "Identificativo canale*" => $this->attribute('_id'),
            "Pagina contenitore" => $this->attribute('_parent_name'),
            "Url originale" => $this->attribute('_original_url'),
            "Funzione del canale*" => $this->attribute('object'),
            "Tipo di canale di erogazione" => $this->attribute('has_channel_type'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "Descrizione" => $this->attribute('description'),
            "Valore del canale (url)*" => $link,
            "Etichetta del canale*" => $name,
            "Costi - Tipo di spesa" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'characteristic')) : '',
            "Costi - Descrizione" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'description')) : '',
            "Costi - Importo" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'value')) : '',
            "Costi - Valuta" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'currency')) : '',
            "Costi - Tipo di spesa [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'characteristic')) : '',
            "Costi - Descrizione [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'description')) : '',
            "Costi - Importo [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'value')) : '',
            "Costi - Valuta [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'currency')) : '',
            "Immagini" => $this->attribute('image'),
            "Files" => $this->attribute('files'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo canale*"]);
        $item->setAttribute('_parent_name', $row["Pagina contenitore"]);
        $item->setAttribute('_original_url', $row["Url originale"]);
        $item->setAttribute('object', $row["Funzione del canale*"]);
        $item->setAttribute('has_channel_type', $row["Tipo di canale di erogazione"]);
        $item->setAttribute('abstract', $row["Descrizione breve*"]);
        $item->setAttribute('description', $row["Descrizione"]);
        $item->setAttribute('channel_url', $row["Valore del canale (url)*"].'|'.$row["Etichetta del canale*"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('files', $row["Files"]);

        $costs = [
            'ita-IT' => [],
            'get-DE' => [],
        ];
        $characteristic = explode(PHP_EOL, $row["Costi - Tipo di spesa"]);
        $description = explode(PHP_EOL, $row["Costi - Descrizione"]);
        $value = explode(PHP_EOL, $row["Costi - Importo"]);
        $currency = explode(PHP_EOL, $row["Costi - Valuta"]);
        if (!OCMigration::isEmptyArray($currency)){
            foreach ($currency as $index => $c){
                $costs['ita-IT'][] = [
                    'characteristic' => $characteristic['characteristic'] ?? '',
                    'description' => $description['description'] ?? '',
                    'value' => $value['value'] ?? '',
                    'currency' => $c,
                ];
            }
        }
        $characteristic = explode(PHP_EOL, $row["Costi - Tipo di spesa [de]"]);
        $description = explode(PHP_EOL, $row["Costi - Descrizione [de]"]);
        $value = explode(PHP_EOL, $row["Costi - Importo [de]"]);
        $currency = explode(PHP_EOL, $row["Costi - Valuta [de]"]);
        if (!OCMigration::isEmptyArray($currency)){
            foreach ($currency as $index => $c){
                $costs['get-DE'][] = [
                    'characteristic' => $characteristic['characteristic'] ?? '',
                    'description' => $description['description'] ?? '',
                    'value' => $value['value'] ?? '',
                    'currency' => $c,
                ];
            }
        }
        $item->setAttribute('has_cost', json_encode($costs));

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('channel');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('3bb4f45279e3c4efe2ac84630e53a7b4'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'object', $this->attribute('object'));
        $payload->setData($locale, 'has_channel_type', $this->formatTags($this->attribute('has_channel_type')));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'channel_url', $this->attribute('channel_url'));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'files', $this->formatBinary($this->attribute('files')));

        $hasCost = json_decode($this->attribute('has_cost'), true);
        $payload->setData($locale, 'has_cost', $hasCost['ita-IT']);

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);
        if (!empty($hasCost['ger-DE'])) {
            $payload->setData($locale, 'has_cost', $hasCost['ger-DE']);
        }

        return $payload;
    }

    public static function getImportPriority(): int
    {
        return 200;
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Tipo di canale di erogazione' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_channel'),
            ],
        ];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*",
            "Kurze Beschreibung* [de]",
        ];
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            'Valore del canale (url)*',
        ];
    }

    public function avoidNameDuplication()
    {
        return false;
    }
}
