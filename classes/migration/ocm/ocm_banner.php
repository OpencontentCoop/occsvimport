<?php

class ocm_banner extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'description',
        'image',
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

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo del banner*';
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo del banner*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            'Nome' => $this->attribute('name'),
            'Descrizione' => $this->attribute('description'),
            'Immagine*' => $this->attribute('image'),
            'Link interno' => $this->attribute('internal_location'),
            'Link esterno' => $this->attribute('location'),
            'Colore di sfondo' => $this->attribute('background_color'),
            'Argomenti' => $this->attribute('topics'),
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