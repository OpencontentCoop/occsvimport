<?php

class ocm_banner extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'name',
        'description',
        'image___name',
        'image___url',
        'internal_location',
        'location',
        'background_color',
        'topics',
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
        return 'Banner';
    }

    public static function getColumnName(): string
    {
        return "Nome";
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo del banner*';
    }

    protected function getOpencityFieldMapper(): array
    {
        $mapper = array_fill_keys(static::$fields, false);
        $mapper['image___name'] = OCMigration::getMapperHelper('image/name');
        $mapper['image___url'] = OCMigration::getMapperHelper('image/url');

        return $mapper;
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo del banner*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            'Nome*' => $this->attribute('name'),
            'Descrizione' => $this->attribute('description'),
            "Nome dell'immagine" => $this->attribute('image___name'),
            'Url file immagine*' => $this->attribute('image___url'),
            'Link interno' => $this->attribute('internal_location'),
            'Link esterno' => $this->attribute('location'),
            'Colore di sfondo' => $this->attribute('background_color'),
            'Argomenti' => $this->attribute('topics'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo del banner*"]);
        $item->setAttribute('name', $row['Nome*']);
        $item->setAttribute('description', $row['Descrizione']);
        $item->setAttribute('image___name', $row["Nome dell'immagine"]);
        $item->setAttribute('image___url', $row['Url file immagine*']);
        $item->setAttribute('internal_location', $row['Link interno']);
        $item->setAttribute('location', $row['Link esterno']);
        $item->setAttribute('background_color', $row['Colore di sfondo']);
        $item->setAttribute('topics', $row['Argomenti']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('banner');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('banners'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'image', [
            'url' => $this->attribute('image___url'),
            'filename' => $this->attribute('image___name'),
        ]);
        if ($this->attribute('internal_location') && empty($this->attribute('location'))) {
            $payload->setData($locale, 'internal_location', '???');
        }
        $payload->setData($locale, 'location', $this->attribute('location'));
        $payload->setData($locale, 'background_color', OpenPABootstrapItaliaOperators::decodeBannerColorSelection($this->attribute('background_color')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));

        return $payload;
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            'Url file immagine*',
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Argomenti" => [
                'strict' => false,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Immagine*" => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef(),
            ],
        ];
    }

    public static function getImportPriority(): int
    {
        return 200;
    }

}