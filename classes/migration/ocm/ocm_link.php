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

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            'Url file immagine',
        ];
    }

    public function generatePayload()
    {
        return $this->getNewPayloadBuilderInstance();
    }

    public static function getImportPriority(): int
    {
        return 190;
    }

}