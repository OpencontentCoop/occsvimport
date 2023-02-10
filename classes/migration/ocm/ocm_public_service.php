<?php

class ocm_public_service extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static function canPush(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static $fields = [
        'type',
        'name',
        'identifier',
        'has_service_status',
        'status_note',
        'alternative_name',
        'image',
        'abstract',
        'audience',
        'applicants',
        'description',
        'has_spatial_coverage',
        'has_language',
        'how_to',
        'has_input',
        'has_module_input',
        'produces_output',
        'relation_service',
        'requires_service',
        'has_cost',
        'output_notes',
        'has_channel',
        'is_physically_available_at_how_to',
        'is_physically_available_at',
        'conditions',
        'exceptions',
        'terms_of_service',
        'has_online_contact_point',
        'holds_role_in_time',
        'has_document',
        'link',
        'news_and_updates',
        'process',
        'topics',
        'ife_event',
        'business_event',
        'service_sector',
        'service_keyword',
        'has_authentication_method',
        'has_interactivity_level',
        'has_temporal_coverage',
        'average_processing_time',
        'has_processing_time',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Servizi';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo del servizio*';
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo del servizio*" => $this->attribute('_id'),
            "Categoria del servizio*" => $this->attribute('type'),
            "Titolo del servizio*" => $this->attribute('name'),
            "Identificativo unico*" => $this->attribute('identifier'),
            "Stato del servizio*" => $this->attribute('has_service_status'),
            "Motivo dello stato" => $this->attribute('status_note'),
            "Sottotitolo" => $this->attribute('alternative_name'),
            "Immagine" => $this->attribute('image'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "A chi è rivolto" => $this->attribute('audience'),
            "Chi può fare domanda" => $this->attribute('applicants'),
            "Descrizione estesa" => $this->attribute('description'),
            "Copertura geografica" => $this->attribute('has_spatial_coverage'),
            "Lingua*" => $this->attribute('has_language'),
            "Come fare*" => $this->attribute('how_to'),
            "Cosa serve*" => $this->attribute('has_input'),
            "Cosa serve (modulistica)" => $this->attribute('has_module_input'),
            "Cosa si ottiene*" => $this->attribute('produces_output'),
            "Servizi correlati/Altri servizi" => $this->attribute('relation_service'),
            "Servizi richiesti" => $this->attribute('requires_service'),
            "Costi" => $this->attribute('has_cost'),
            "Procedure collegate all'esito" => $this->attribute('output_notes'),
            "Accedi al servizio (canale digitale)" => $this->attribute('has_channel'),
            "Istruzioni per accedere al servizio (canale fisico)" => $this->attribute('is_physically_available_at_how_to'),
            "Accedi al servizio (Canale fisico)*" => $this->attribute('is_physically_available_at'),
            "Vincoli" => $this->attribute('conditions'),
            "Casi particolari" => $this->attribute('exceptions'),
            "Condizioni di servizio" => $this->attribute('terms_of_service'),
            "Contatti*" => $this->attribute('has_online_contact_point'),
            "Unità organizzativa responsabile*" => $this->attribute('holds_role_in_time'),
            "Documenti*" => $this->attribute('has_document'),
            "Link a siti esterni" => $this->attribute('link'),
            "Notizie e aggiornamenti" => $this->attribute('news_and_updates'),
            "Tipologia di procedimento*" => $this->attribute('process'),
            "Argomenti*" => $this->attribute('topics'),
            "Life events" => $this->attribute('ife_event'),
            "Business events" => $this->attribute('business_event'),
            "Settore merceologico" => $this->attribute('service_sector'),
            "Parole chiave" => $this->attribute('service_keyword'),
            "Autenticazione" => $this->attribute('has_authentication_method'),
            "Livello di interattività" => $this->attribute('has_interactivity_level'),
            "Quando" => $this->attribute('has_temporal_coverage'),
            "Giorni medi di attesa dalla richiesta" => $this->attribute('average_processing_time'),
            "Giorni massimi di attesa dalla richiesta*" => $this->attribute('has_processing_time'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function getColumnName(): string
    {
        return 'Titolo del servizio*';
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