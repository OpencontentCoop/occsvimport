<?php

class ocm_pagina_sito extends eZPersistentObject implements ocm_interface
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
        'abstract',
        'description',
        'image___name',
        'image___url',
        'gps',
        'riferimento',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Pagine del sito';
    }

    public static function getColumnName(): string
    {
        return 'Nome';
    }

    public static function getIdColumnLabel(): string
    {
        return "ID";
    }

    public function toSpreadsheet(): array
    {
        $address = json_decode($this->attribute('gps'), true);
        return [
            'ID'=> $this->attribute('_id'),
            'Nome' => $this->attribute('name'),
            'Nome breve' => $this->attribute('short_name'),
            'Abstract (Descrizione breve)' => $this->attribute('abstract'),
            'Descrizione' => $this->attribute('description'),
            'Nome immagine' => $this->attribute('image___name'),
            'File immagine' => $this->attribute('image___url'),
            'Indirizzo' => $address['address'],
            "Latitudine e longitudine" => $address['latitude'] . ' ' . $address['longitude'],
            'Riferimento' => $this->attribute('riferimento'),
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
        return -120;
    }


}