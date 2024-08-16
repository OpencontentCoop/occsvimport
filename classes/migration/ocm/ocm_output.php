<?php

class ocm_output extends OCMPersistentObject implements ocm_interface
{

    public static $fields = [
        'name',
        'short_name',
        'abstract',
        'description',
        'image',
        'has_service_input_output_type',
        'topics',
        'files',
        'de_name',
        'de_short_name',
        'de_abstract',
        'de_description',
        'en_name',
        'en_short_name',
        'en_abstract',
        'en_description',
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
        return 'Output del servizio';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo output*';
    }

    public static function getColumnName(): string
    {
        return 'Nome*';
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo output*" => $this->attribute('_id'),
            "Pagina contenitore" => $this->attribute('_parent_name'),
            "Url originale" => $this->attribute('_original_url'),
            "Nome*" => $this->attribute('name'),
            "Nome breve" => $this->attribute('short_name'),
            "Abstract (Descrizione breve)" => $this->attribute('abstract'),
            "Descrizione" => $this->attribute('description'),
            "Immagini" => $this->attribute('image'),
            "Tipologia di output/esito*" => $this->attribute('has_service_input_output_type'),
            "Argomento" => $this->attribute('topics'),
            "File di esempio" => $this->attribute('files'),
            "Name* [de]" => $this->attribute('de_name'),
            "Kurzer Name [de]" => $this->attribute('de_short_name'),
            "Abstract (Kurze Beschreibung) [de]" => $this->attribute('de_abstract'),
            "Beschreibung [de]" => $this->attribute('de_description'),
            "Name* [en]" => $this->attribute('en_name'),
            "Short Name [en]" => $this->attribute('en_short_name'),
            "Abstract [en]" => $this->attribute('en_abstract'),
            "Description [en]" => $this->attribute('en_description'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo output*"]);
        $item->setAttribute('name', $row["Nome*"]);
        $item->setAttribute('short_name', $row["Nome breve"]);
        $item->setAttribute('abstract', $row["Abstract (Descrizione breve)"]);
        $item->setAttribute('description', $row["Descrizione"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('has_service_input_output_type', $row["Tipologia di output/esito*"]);
        $item->setAttribute('topics', $row["Argomento"]);
        $item->setAttribute('files', $row["File di esempio"]);
        $item->setAttribute('de_name', $row["Name* [de]"]);
        $item->setAttribute('de_short_name', $row["Kurzer Name [de]"]);
        $item->setAttribute('de_abstract', $row["Abstract (Kurze Beschreibung) [de]"]);
        $item->setAttribute('de_description', $row["Beschreibung [de]"]);
        $item->setAttribute('en_name', $row["Name* [en]"]);
        $item->setAttribute('en_short_name', $row["Short Name [en]"]);
        $item->setAttribute('en_abstract', $row["Abstract [en]"]);
        $item->setAttribute('en_description', $row["Description [en]"]);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('output');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('f1c1c3e7404f162fa27d7accbe742f3d'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'short_name', $this->attribute('short_name'));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'has_service_input_output_type', $this->formatTags($this->attribute('has_service_input_output_type')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'files', $this->formatBinary($this->attribute('files')));

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);

        return $payload;
    }

    public static function getImportPriority(): int
    {
        return 210;
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Tipologia di output/esito*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_io'),
            ],
        ];
    }

    public function avoidNameDuplication()
    {
        return false;
    }


}
