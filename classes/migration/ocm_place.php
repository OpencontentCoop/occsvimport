<?php

class ocm_place extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'alternative_name',
        'topics',
        'type',
        'abstract',
        'description',
        'contains_place',
        'image',
        'video',
        'has_video',
        'accessibility',
        'has_address',
        'opening_hours_specification',
        'help',
        'has_office',
        'more_information',
        'identifier',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Luoghi';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore luogo*";
    }

    public function toSpreadsheet(): array
    {
        $address = json_decode($this->attribute('has_address'), true);
        return [
            "Identificatore luogo*" => $this->attribute('_id'),
            "Nome del luogo*" => $this->attribute('name'),
            "Argomento*" => $this->attribute('topics'),
            "Tipo di luogo*" => $this->attribute('type'),
            "Modalità di accesso*" => $this->attribute('accessibility'),
            "Indirizzo*" => $address['address'],
            "Latitudine e longitudine*" => $address['latitude'] . ' ' . $address['longitude'],
            "Punti di contatto*" => $this->attribute('help'),
            "Descrizione breve " => $this->attribute('abstract'),
            "Descrizione estesa " => $this->attribute('description'),
            "Immagini" => $this->attribute('image'),
            "Link video" => $this->attribute('has_video'),
            "Orario per il pubblico" => $this->attribute('opening_hours_specification'),
            "Struttura responsabile" => $this->attribute('has_office'),
            "Ulteriori informazioni" => $this->attribute('more_information'),
            "Codice luogo" => $this->attribute('identifier'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        return new static();
    }

    public static function getImportPriority(): int
    {
        return 20;
    }

    public function generatePayload(): array
    {
        return [];
    }
}