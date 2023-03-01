<?php

class ocm_folder extends OCMPersistentObject implements ocm_interface
{
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

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['ID']);
        $item->setAttribute('name', $row['Nome']);
        $item->setAttribute('short_name', $row['Nome breve']);
        $item->setAttribute('short_description', $row['Descrizione breve']);
        $item->setAttribute('description', $row['Descrizione']);
        $item->setAttribute('tags', $row['Parole chiave']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
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


    public function generatePayload()
    {
        return $this->getNewPayloadBuilderInstance();
    }

    public static function getImportPriority(): int
    {
        return -110;
    }


}