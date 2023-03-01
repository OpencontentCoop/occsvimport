<?php

class ocm_file extends OCMPersistentObject implements ocm_interface
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
        'description',
        'file',
        'tags',
        'percorso_univoco',
    ];

    public function toSpreadsheet(): array
    {
        return [
            'ID'=> $this->attribute('_id'),
            'Nome del file' => $this->attribute('name'),
            'Descrizione' => $this->attribute('description'),
            'File' => $this->attribute('file'),
            'Tags' => $this->attribute('tags'),
            'Percorso univoco del file ' => $this->attribute('percorso_univoco'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['ID']);
        $item->setAttribute('name', $row['Nome del file']);
        $item->setAttribute('description', $row['Descrizione']);
        $item->setAttribute('file', $row['File']);
        $item->setAttribute('tags', $row['Tags']);
        $item->setAttribute('percorso_univoco', $row['Percorso univoco del file ']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        return $this->getNewPayloadBuilderInstance();
    }

    public static function getImportPriority(): int
    {
        return -100;
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'File e file allegati';
    }

    public static function getColumnName(): string
    {
        return "Nome del file";
    }

    public static function getIdColumnLabel(): string
    {
        return 'ID';
    }


}