<?php

class ocm_folder extends OCMPersistentObject implements ocm_interface
{
    public static function canImport()
    {
        return false;
    }

    public static function canPull()
    {
        return false;
    }

    public static function canPush()
    {
        return OCMigration::discoverContext() === 'comunweb';
    }

    public static function canExport()
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

    public static function getSpreadsheetTitle()
    {
        return 'Cartelle';
    }

    public static function getColumnName()
    {
        return "Nome";
    }

    public static function getIdColumnLabel()
    {
        return "ID";
    }

    public function toSpreadsheet()
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

    public static function fromSpreadsheet($row) 
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

    public static function getRangeValidationHash()
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

    public static function getImportPriority()
    {
        return -110;
    }


}