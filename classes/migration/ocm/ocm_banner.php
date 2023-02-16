<?php

class ocm_banner extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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