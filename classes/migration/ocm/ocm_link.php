<?php

class ocm_link extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'short_name',
        'abstract',
        'location',
        'internal_location',
        'descrizione',
        'image',
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

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo del link*" => $this->attribute('_id'),
            'Nome' => $this->attribute('name'),
            'Nome breve' => $this->attribute('short_name'),
            'Descrizione breve' => $this->attribute('abstract'),
            'Link esterno' => $this->attribute('location'),
            'Link interno' => $this->attribute('internal_location'),
            'Descrizione' => $this->attribute('descrizione'),
            'Immagine*' => $this->attribute('image'),
            'Data di archiviazione' => $this->attribute('data_archiviazione'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        // TODO: Implement fromSpreadsheet() method.
    }

    public function generatePayload(): array
    {
        // TODO: Implement generatePayload() method.
    }

    public static function getImportPriority(): int
    {
        // TODO: Implement getImportPriority() method.
    }

}