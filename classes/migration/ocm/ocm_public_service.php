<?php

class ocm_public_service extends OCMPersistentObject implements ocm_interface
{
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
        'de_name',
        'de_status_note',
        'de_alternative_name',
        'de_abstract',
        'de_audience',
        'de_applicants',
        'de_description',
        'de_how_to',
        'de_has_input',
        'de_output_notes',
        'de_is_physically_available_at_how_to',
        'de_conditions',
        'de_exceptions',
        'de_service_keyword',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Servizi';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo del servizio*';
    }

    protected function getOpencityFieldMapper(): array
    {
        $mapper = array_fill_keys(static::$fields, false);
        $mapper['has_spatial_coverage'] = function ($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            if (!isset($firstLocalizedContentData['has_spatial_coverage'])) {
                return '';
            }
            $fieldInfo = $firstLocalizedContentData['has_spatial_coverage'];
            $contentValue = $fieldInfo['content'];
            return implode(PHP_EOL, $contentValue);
        };
        return $mapper;
    }

    public function toSpreadsheet(): array
    {
        $costs = json_decode($this->attribute('has_cost'), true);
        $links = json_decode($this->attribute('link'), true);
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
            "A chi è rivolto*" => $this->attribute('audience'),
            "Chi può fare domanda" => $this->attribute('applicants'),
            "Descrizione estesa" => $this->attribute('description'),
            "Copertura geografica*" => $this->attribute('has_spatial_coverage'),
            "Lingua*" => $this->attribute('has_language'),
            "Come fare*" => $this->attribute('how_to'),
            "Cosa serve*" => $this->attribute('has_input'),
            "Cosa serve (modulistica)" => $this->attribute('has_module_input'),
            "Cosa si ottiene*" => $this->attribute('produces_output'),
            "Servizi correlati/Altri servizi" => $this->attribute('relation_service'),
            "Servizi richiesti" => $this->attribute('requires_service'),
            "Costi - Tipo di spesa" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'characteristic')) : '',
            "Costi - Descrizione" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'description')) : '',
            "Costi - Importo" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'value')) : '',
            "Costi - Valuta" => isset($costs['ita-IT']) ? implode(PHP_EOL, array_column($costs['ita-IT'], 'currency')) : '',
            "Costi - Tipo di spesa [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'characteristic')) : '',
            "Costi - Descrizione [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'description')) : '',
            "Costi - Importo [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'value')) : '',
            "Costi - Valuta [de]" => isset($costs['ger-DE']) ? implode(PHP_EOL, array_column($costs['ger-DE'], 'currency')) : '',
            "Procedure collegate all'esito" => $this->attribute('output_notes'),
            "Accedi al servizio (canale digitale)*" => $this->attribute('has_channel'),
            "Istruzioni per accedere al servizio (canale fisico)" => $this->attribute('is_physically_available_at_how_to'),
            "Accedi al servizio (Canale fisico)*" => $this->attribute('is_physically_available_at'),
            "Vincoli" => $this->attribute('conditions'),
            "Casi particolari" => $this->attribute('exceptions'),
            "Condizioni di servizio*" => $this->attribute('terms_of_service'),
            "Contatti*" => $this->attribute('has_online_contact_point'),
            "Unità organizzativa responsabile*" => $this->attribute('holds_role_in_time'),
            "Documenti" => $this->attribute('has_document'),
            "Titolo link a siti esterni" => isset($links['ita-IT']) ? implode(PHP_EOL, array_column($links['ita-IT'], 'nome_sito')) : '',
            "Link a siti esterni" => isset($links['ita-IT']) ? implode(PHP_EOL, array_column($links['ita-IT'], 'link')) : '',
            "Titolo link a siti esterni [de]" => isset($links['ger-DE']) ? implode(PHP_EOL, array_column($links['ger-DE'], 'nome_sito')) : '',
            "Link a siti esterni [de]" => isset($links['ger-DE']) ? implode(PHP_EOL, array_column($links['ger-DE'], 'link')) : '',
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

            "Titel des Dienstes* [de]" => $this->attribute('de_name'),
            "Grund für den Status [de]" => $this->attribute('de_status_note'),
            "Alternativer Titel/Untertitel [de]" => $this->attribute('de_alternative_name'),
            "Kurze Beschreibung* [de]" => $this->attribute('de_abstract'),
            "Beschreibung der Personengruppen, die diese Leistung in Anspruch nehmen [de]" => $this->attribute('de_audience'),
            "Wer kann das Ansuchen stellen [de]" => $this->attribute('de_applicants'),
            "Ausführliche Beschreibung [de]" => $this->attribute('de_description'),
            "So geht's* [de]" => $this->attribute('de_how_to'),
            "Dokumente, die bei der Antragsstellung vorzulegen sind* [de]" => $this->attribute('de_has_input'),
            "Verfahren und damit verbundenes Ergebnis [de]" => $this->attribute('de_output_notes'),
            "Anweisungen für den Zugriff auf den Dienst (physischer Kanal) [de]" => $this->attribute('de_is_physically_available_at_how_to'),
            "Bedingungen [de]" => $this->attribute('de_conditions'),
            "Sonderfälle [de]" => $this->attribute('de_exceptions'),
            "Schlüsselwort [de]" => $this->attribute('de_service_keyword'),

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
        $item->setAttribute('has_spatial_coverage', $row["Copertura geografica*"]);
        $item->setAttribute('has_language', $row["Lingua*"]);
        $item->setAttribute('how_to', $row["Come fare*"]);
        $item->setAttribute('has_input', $row["Cosa serve*"]);
        $item->setAttribute('has_module_input', $row["Cosa serve (modulistica)"]);
        $item->setAttribute('produces_output', $row["Cosa si ottiene*"]);
        $item->setAttribute('relation_service', $row["Servizi correlati/Altri servizi"]);
        $item->setAttribute('requires_service', $row["Servizi richiesti"]);
        $item->setAttribute('output_notes', $row["Procedure collegate all'esito"]);
        $item->setAttribute('has_channel', $row["Accedi al servizio (canale digitale)*"]);
        $item->setAttribute('is_physically_available_at_how_to', $row["Istruzioni per accedere al servizio (canale fisico)"]);
        $item->setAttribute('is_physically_available_at', $row["Accedi al servizio (Canale fisico)*"]);
        $item->setAttribute('conditions', $row["Vincoli"]);
        $item->setAttribute('exceptions', $row["Casi particolari"]);
        $item->setAttribute('terms_of_service', $row["Condizioni di servizio*"]);
        $item->setAttribute('has_online_contact_point', $row["Contatti*"]);
        $item->setAttribute('holds_role_in_time', $row["Unità organizzativa responsabile*"]);
        $item->setAttribute('has_document', $row["Documenti"]);
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

        $item->setAttribute('de_name', $row["Titel des Dienstes* [de]"]);
        $item->setAttribute('de_status_note', $row["Grund für den Status [de]"]);
        $item->setAttribute('de_alternative_name', $row["Alternativer Titel/Untertitel [de]"]);
        $item->setAttribute('de_abstract', $row["Kurze Beschreibung* [de]"]);
        $item->setAttribute('de_audience', $row["Beschreibung der Personengruppen, die diese Leistung in Anspruch nehmen [de]"]);
        $item->setAttribute('de_applicants', $row["Wer kann das Ansuchen stellen [de]"]);
        $item->setAttribute('de_description', $row["Ausführliche Beschreibung [de]"]);
        $item->setAttribute('de_how_to', $row["So geht's* [de]"]);
        $item->setAttribute('de_has_input', $row["Dokumente, die bei der Antragsstellung vorzulegen sind* [de]"]);
        $item->setAttribute('de_output_notes', $row["Verfahren und damit verbundenes Ergebnis [de]"]);
        $item->setAttribute('de_is_physically_available_at_how_to', $row["Anweisungen für den Zugriff auf den Dienst (physischer Kanal) [de]"]);
        $item->setAttribute('de_conditions', $row["Bedingungen [de]"]);
        $item->setAttribute('de_exceptions', $row["Sonderfälle [de]"]);
        $item->setAttribute('de_service_keyword', $row["Schlüsselwort [de]"]);

        $costs = [
            'ita-IT' => [],
            'get-DE' => [],
        ];
        $characteristic = explode(PHP_EOL, $row["Costi - Tipo di spesa"]);
        $description = explode(PHP_EOL, $row["Costi - Descrizione"]);
        $value = explode(PHP_EOL, $row["Costi - Importo"]);
        $currency = explode(PHP_EOL, $row["Costi - Valuta"]);
        if (!OCMigration::isEmptyArray($currency)){
            foreach ($currency as $index => $c){
                $costs['ita-IT'][] = [
                    'characteristic' => $characteristic[$index] ?? '',
                    'description' => $description[$index] ?? '',
                    'value' => $value[$index] ?? '',
                    'currency' => $c,
                ];
            }
        }
        $characteristic = explode(PHP_EOL, $row["Costi - Tipo di spesa [de]"]);
        $description = explode(PHP_EOL, $row["Costi - Descrizione [de]"]);
        $value = explode(PHP_EOL, $row["Costi - Importo [de]"]);
        $currency = explode(PHP_EOL, $row["Costi - Valuta [de]"]);
        if (!OCMigration::isEmptyArray($currency)){
            foreach ($currency as $index => $c){
                $costs['get-DE'][] = [
                    'characteristic' => $characteristic[$index] ?? '',
                    'description' => $description[$index] ?? '',
                    'value' => $value[$index] ?? '',
                    'currency' => $c,
                ];
            }
        }
        $item->setAttribute('has_cost', json_encode($costs));

        $links = [
            'ita-IT' => [],
            'get-DE' => [],
        ];
        $link = explode(PHP_EOL, $row["Link a siti esterni"]);
        $name = explode(PHP_EOL, $row["Titolo link a siti esterni"]);
        if (!OCMigration::isEmptyArray($link)) {
            foreach ($link as $i => $l) {
                $links['ita-IT'][] = [
                    'nome_sito' => $name[$i] ?? '',
                    'link' => $l ?? '',
                ];
            }
        }
        $link = explode(PHP_EOL, $row["Link a siti esterni [de]"]);
        $name = explode(PHP_EOL, $row["Titolo link a siti esterni [de]"]);
        if (!OCMigration::isEmptyArray($link)) {
            foreach ($link as $i => $l) {
                $links['ger-DE'][] = [
                    'nome_sito' => $name[$i] ?? '',
                    'link' => $l ?? '',
                ];
            }
        }
        $item->setAttribute('link', json_encode($links));

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getColumnName(): string
    {
        return 'Titolo del servizio*';
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('public_service');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('all-services'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'type', $this->formatTags($this->attribute('type')));
        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'identifier', $this->attribute('identifier'));
        $payload->setData($locale, 'has_service_status', $this->formatTags($this->attribute('has_service_status')));
        $payload->setData($locale, 'status_note', strip_tags($this->attribute('status_note')));
        $payload->setData($locale, 'alternative_name', $this->attribute('alternative_name'));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'abstract', strip_tags($this->attribute('abstract')));
        $payload->setData($locale, 'audience', $this->attribute('audience'));
        $payload->setData($locale, 'applicants', $this->attribute('applicants'));
        $payload->setData($locale, 'description', $this->attribute('description'));
        $payload->setData($locale, 'has_spatial_coverage', $this->attribute('has_spatial_coverage'));
        $payload->setData($locale, 'has_language', $this->formatTags($this->attribute('has_language')));
        $payload->setData($locale, 'how_to', $this->attribute('how_to'));
        $payload->setData($locale, 'has_input', $this->attribute('has_input'));
        $payload->setData($locale, 'has_module_input', ocm_document::getIdListByName($this->attribute('has_module_input')));
        $payload->setData($locale, 'produces_output', ocm_output::getIdListByName($this->attribute('produces_output')));
        $payload->setData($locale, 'output_notes', $this->attribute('output_notes'));
        $payload->setData($locale, 'has_channel', ocm_channel::getIdListByName($this->attribute('has_channel'), 'object'));
        $payload->setData($locale, 'is_physically_available_at_how_to', strip_tags($this->attribute('is_physically_available_at_how_to')));
        $payload->setData($locale, 'is_physically_available_at', ocm_place::getIdListByName($this->attribute('is_physically_available_at')));
        $payload->setData($locale, 'conditions', $this->attribute('conditions'));
        $payload->setData($locale, 'exceptions', $this->attribute('exceptions'));
        $payload->setData($locale, 'terms_of_service', $this->formatBinary($this->attribute('terms_of_service')));
        $payload->setData($locale, 'has_online_contact_point', ocm_online_contact_point::getIdListByName($this->attribute('has_online_contact_point')));
        $payload->setData($locale, 'holds_role_in_time', ocm_organization::getIdListByName($this->attribute('holds_role_in_time'), 'legal_name'));
        $payload->setData($locale, 'has_document', ocm_document::getIdListByName($this->attribute('has_document')));
        $payload->setData($locale, 'process', $this->formatTags($this->attribute('process')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'ife_event', $this->formatTags($this->attribute('ife_event')));
        $payload->setData($locale, 'business_event', $this->formatTags($this->attribute('business_event')));
        $payload->setData($locale, 'service_sector', $this->formatTags($this->attribute('service_sector')));
        $payload->setData($locale, 'service_keyword', explode(PHP_EOL, $this->attribute('service_keyword')));
        $payload->setData($locale, 'has_authentication_method', $this->formatTags($this->attribute('has_authentication_method')));
        $payload->setData($locale, 'has_interactivity_level', $this->formatTags($this->attribute('has_interactivity_level')));
        $payload->setData($locale, 'has_temporal_coverage', ocm_opening_hours_specification::getIdListByName($this->attribute('has_temporal_coverage')));
        $payload->setData($locale, 'average_processing_time', (int)$this->attribute('average_processing_time'));
        $payload->setData($locale, 'has_processing_time', (int)$this->attribute('has_processing_time'));

        $hasCost = json_decode($this->attribute('has_cost'), true);
        $payload->setData($locale, 'has_cost', $hasCost['ita-IT']);

        $links = json_decode($this->attribute('link'), true);
        $payload->setData($locale, 'link', $links['ita-IT']);

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);
        if (!empty($hasCost['ger-DE'])) {
            $payload->setData('ger-DE', 'has_cost', $hasCost['ger-DE']);
        }
        if (!empty($links['ger-DE'])) {
            $payload->setData('ger-DE', 'link', $links['ger-DE']);
        }

        $payloads = [self::getImportPriority() => $payload];

        $relationServices = self::getIdListByName($this->attribute('relation_service'));
        $requiresServices = self::getIdListByName($this->attribute('requires_service'));

        if (count($relationServices) > 0 || count($requiresServices) > 0) {
            $payload2 = clone $payload;
            $payload2->unSetData();
            $payload2->setData($locale, 'relation_service', $relationServices);
            $payload2->setData($locale, 'requires_service', $requiresServices);
            if (in_array('ger-DE', $payload->getMetadaData('languages'))){
                $payload2->setData('ger-DE', 'relation_service', $relationServices);
                $payload2->setData('ger-DE', 'requires_service', $requiresServices);
            }
            $payloads[ocm_banner::getImportPriority()+1] = $payload2;
        }

        return $payloads;
    }

    public static function getImportPriority(): int
    {
        return 220;
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Stato del servizio*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_status'),
            ],
            'Categoria del servizio*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_category'),
            ],
            'Livello di interattività' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_interaction'),
            ],
            'Settore merceologico' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_nace'),
            ],
            'Autenticazione' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_auth'),
            ],
            'Tipologia di procedimento*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('service_procedure'),
            ],
            'Lingua*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('lingue'),
            ],
            "Life events" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('life-events'),
            ],
            "Business events" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('business-events'),
            ],

            "Immagine" => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Cosa serve (modulistica)" => [
                'strict' => false,
                'ref' => ocm_document::getRangeRef()
            ],
            "Cosa si ottiene*" => [
                'strict' => false,
                'ref' => ocm_output::getRangeRef()
            ],
            "Quando" => [
                'strict' => false,
                'ref' => ocm_opening_hours_specification::getRangeRef()
            ],
            "Servizi correlati/Altri servizi" => [
                'strict' => false,
                'ref' => ocm_public_service::getRangeRef()
            ],
            "Servizi richiesti" => [
                'strict' => false,
                'ref' => ocm_public_service::getRangeRef()
            ],
            "Accedi al servizio (canale digitale)*" => [
                'strict' => false,
                'ref' => ocm_channel::getRangeRef()
            ],
            "Accedi al servizio (Canale fisico)*" => [
                'strict' => false,
                'ref' => ocm_place::getRangeRef()
            ],
            "Contatti*" => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            "Unità organizzativa responsabile*" => [
                'strict' => false,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Documenti" => [
                'strict' => false,
                'ref' => ocm_document::getRangeRef()
            ],
            "Argomenti*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
        ];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve"
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve",
            "A chi è rivolto",
            "Chi può fare domanda",
            "Descrizione estesa",
            "Come fare",
            "Cosa serve",
            "Procedure collegate all'esito",
            "Vincoli",
            "Casi particolari",
        ];
    }

    public static function getUrlValidationHeaders(): array
    {
        return [];
    }
}