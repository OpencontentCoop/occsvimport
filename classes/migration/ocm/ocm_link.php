<?php

class ocm_link extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'name',
        'short_name',
        'abstract',
        'location',
        'internal_location',
        'descrizione',
        'image___name',
        'image___url',
        'data_archiviazione',
        'de_name',
        'de_short_name',
        'de_abstract',
        'de_descrizione',
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
        return 'Link';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo del link*';
    }

    public static function getColumnName(): string
    {
        return "Nome";
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
            "Identificativo del link*" => $this->attribute('_id'),
            'Nome*' => $this->attribute('name'),
            'Nome breve' => $this->attribute('short_name'),
            'Descrizione breve' => $this->attribute('abstract'),
            'Link esterno' => $this->attribute('location'),
            'Link interno' => $this->attribute('internal_location'),
            'Descrizione' => $this->attribute('descrizione'),
            "Nome dell'immagine" => $this->attribute('image___name'),
            'Url file immagine' => $this->attribute('image___url'),
            'Data di archiviazione' => $this->attribute('data_archiviazione'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),

            'Name* [de]' => $this->attribute('de_name'),
            'Kurzname [de]' => $this->attribute('de_short_name'),
            'Kurze Beschreibung [de]' => $this->attribute('de_abstract'),
            'Beschreibung [de]' => $this->attribute('de_descrizione'),

        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo del link*"]);
        $item->setAttribute('name', $row['Nome*']);
        $item->setAttribute('short_name', $row['Nome breve']);
        $item->setAttribute('abstract', $row['Descrizione breve']);
        $item->setAttribute('location', $row['Link esterno']);
        $item->setAttribute('internal_location', $row['Link interno']);
        $item->setAttribute('descrizione', $row['Descrizione']);
        $item->setAttribute('image___name', $row["Nome dell'immagine"]);
        $item->setAttribute('image___url', $row['Url file immagine']);
        $item->setAttribute('data_archiviazione', $row['Data di archiviazione']);

        $item->setAttribute('de_name', $row['Name* [de]']);
        $item->setAttribute('de_short_name', $row['Kurzname [de]']);
        $item->setAttribute('de_abstract', $row['Kurze Beschreibung [de]']);
        $item->setAttribute('de_descrizione', $row['Beschreibung [de]']);


        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('link');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('banners'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'short_name', $this->attribute('short_name'));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
        $payload->setData($locale, 'descrizione', $this->attribute('descrizione'));
        if (!empty($this->attribute('image___url'))) {
            $payload->setData($locale, 'image', [
                'url' => $this->attribute('image___url'),
                'filename' => $this->attribute('image___name'),
            ]);
        }
        if ($this->attribute('internal_location') && empty($this->attribute('location'))) {
            $payload->setData($locale, 'internal_location', [OCMigration::getObjectRemoteIdByName($this->attribute('internal_location'))]);
        }
        $payload->setData($locale, 'location', $this->attribute('location'));
        $payload->setData($locale, 'data_archiviazione', $this->formatDate($this->attribute('data_archiviazione')));

        return $this->appendTranslationsToPayloadIfNeeded($payload);
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            'Url file immagine',
        ];
    }

    public static function getImportPriority(): int
    {
        return 190;
    }

}