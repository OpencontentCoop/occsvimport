<?php

class ocm_folder extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static function canImport(): bool
    {
        return false;
    }

    public static function canPull(): bool
    {
        return false;
    }

    public static function canPush(): bool
    {
        return OCMigration::discoverContext() === 'comunweb';
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'comunweb';
    }

    public static $fields = [
        'name',
        'short_name',
        'short_description',
        'description',
        'tags',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Cartelle';
    }

    public static function getColumnName(): string
    {
        return "Nome";
    }

    public static function getIdColumnLabel(): string
    {
        return "ID";
    }

    public function toSpreadsheet(): array
    {
        return [
            'ID'=> $this->attribute('_id'),
            'Nome' => $this->attribute('name'),
            'Nome breve' => $this->attribute('short_name'),
            'Descrizione breve' => $this->attribute('short_description'),
            'Descrizione' => $this->attribute('description'),
            'Parole chiave' => $this->attribute('tags'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Rimappare in" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('content-type'),
            ],
            "Tipo di contenuto" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('content-type'),
            ],
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
        return -110;
    }


}