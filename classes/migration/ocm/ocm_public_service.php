<?php

class ocm_public_service extends OCMPersistentObject implements ocm_interface
{
    public static function canPush(): bool
    {
        return false;
    }

    public static function canExport(): bool
    {
        return false;
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

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo del servizio*"]);
        $item->setAttribute('type', $row["Categoria del servizio*"]);
        $item->setAttribute('name', $row["Titolo del servizio*"]);
        $item->setAttribute('identifier', $row["Identificativo unico*"]);
        $item->setAttribute('has_service_status', $row["Stato del servizio*"]);
        $item->setAttribute('status_note', $row["Motivo dello stato"]);
        $item->setAttribute('alternative_name', $row["Sottotitolo"]);
        $item->setAttribute('image', $row["Immagine"]);
        $item->setAttribute('abstract', $row["Descrizione breve*"]);
        $item->setAttribute('audience', $row["A chi è rivolto"]);
        $item->setAttribute('applicants', $row["Chi può fare domanda"]);
        $item->setAttribute('description', $row["Descrizione estesa"]);
        $item->setAttribute('has_spatial_coverage', $row["Copertura geografica"]);
        $item->setAttribute('has_language', $row["Lingua*"]);
        $item->setAttribute('how_to', $row["Come fare*"]);
        $item->setAttribute('has_input', $row["Cosa serve*"]);
        $item->setAttribute('has_module_input', $row["Cosa serve (modulistica)"]);
        $item->setAttribute('produces_output', $row["Cosa si ottiene*"]);
        $item->setAttribute('relation_service', $row["Servizi correlati/Altri servizi"]);
        $item->setAttribute('requires_service', $row["Servizi richiesti"]);
        $item->setAttribute('has_cost', $row["Costi"]);
        $item->setAttribute('output_notes', $row["Procedure collegate all'esito"]);
        $item->setAttribute('has_channel', $row["Accedi al servizio (canale digitale)"]);
        $item->setAttribute('is_physically_available_at_how_to', $row["Istruzioni per accedere al servizio (canale fisico)"]);
        $item->setAttribute('is_physically_available_at', $row["Accedi al servizio (Canale fisico)*"]);
        $item->setAttribute('conditions', $row["Vincoli"]);
        $item->setAttribute('exceptions', $row["Casi particolari"]);
        $item->setAttribute('terms_of_service', $row["Condizioni di servizio"]);
        $item->setAttribute('has_online_contact_point', $row["Contatti*"]);
        $item->setAttribute('holds_role_in_time', $row["Unità organizzativa responsabile*"]);
        $item->setAttribute('has_document', $row["Documenti*"]);
        $item->setAttribute('link', $row["Link a siti esterni"]);
        $item->setAttribute('news_and_updates', $row["Notizie e aggiornamenti"]);
        $item->setAttribute('process', $row["Tipologia di procedimento*"]);
        $item->setAttribute('topics', $row["Argomenti*"]);
        $item->setAttribute('ife_event', $row["Life events"]);
        $item->setAttribute('business_event', $row["Business events"]);
        $item->setAttribute('service_sector', $row["Settore merceologico"]);
        $item->setAttribute('service_keyword', $row["Parole chiave"]);
        $item->setAttribute('has_authentication_method', $row["Autenticazione"]);
        $item->setAttribute('has_interactivity_level', $row["Livello di interattività"]);
        $item->setAttribute('has_temporal_coverage', $row["Quando"]);
        $item->setAttribute('average_processing_time', $row["Giorni medi di attesa dalla richiesta"]);
        $item->setAttribute('has_processing_time', $row["Giorni massimi di attesa dalla richiesta*"]);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getColumnName(): string
    {
        return 'Titolo del servizio*';
    }

    public function generatePayload()
    {
        return $this->getNewPayloadBuilderInstance();
    }

    public static function getImportPriority(): int
    {
        return 150;
    }

}