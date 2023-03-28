<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_event extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'event_title',
        'short_event_title',
        'event_abstract',
        'description',
        'image_file',
        'image',
        'video',
        'sub_event_of',
        'target_audience',
        'about_target_audience',
        'has_playbill',
        'attachment',
        'has_online_contact_point',
        'has_public_event_typology',
        'topics',
        'time_interval_events',
        'time_interval_ical',
        'takes_place_in',
        'attendee',
        'is_accessible_for_free',
        'cost_notes',
        'has_offer',
        'ulteriori_informazioni',
        'organizer',
        'funder',
        'sponsor',
        'related_event',
        'composer',
        'performer',
        'translator',
        'in_language',
        'maximum_attendee_capacity',
        'event_content_keyword',
        'aggregate_rating',
        'de_event_title',
        'de_short_event_title',
        'de_event_abstract',
        'de_description',
        'de_about_target_audience',
        'de_ulteriori_informazioni',
        'de_event_content_keyword'
    ];

    public static function getSortField(): string
    {
        return 'event_title';
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Eventi';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo evento*';
    }

    public static function getColumnName(): string
    {
        return "Titolo dell'evento*";
    }

    protected function getOpencityFieldMapper(): array
    {
        return [
            'event_title' => false,
            'short_event_title' => false,
            'event_abstract' => false,
            'description' => false,
            'image' => false,
            'video' => false,
            'sub_event_of' => false,
            'target_audience' => false,
            'about_target_audience' => false,
            'has_playbill' => false,
            'attachment' => false,
            'has_online_contact_point' => false,
            'has_public_event_typology' => false,
            'topics' => false,
            'time_interval_events' => OCMigration::getMapperHelper('time_interval/events'),
            'time_interval_ical' => OCMigration::getMapperHelper('time_interval/ical'),
            'takes_place_in' => false,
            'attendee' => false,
            'is_accessible_for_free' => false,
            'cost_notes' => false,
            'has_offer' => false,
            'ulteriori_informazioni' => false,
            'organizer' => false,
            'funder' => false,
            'sponsor' => false,
            'related_event' => false,
            'composer' => false,
            'performer' => false,
            'translator' => false,
            'in_language' => false,
            'maximum_attendee_capacity' => false,
            'event_content_keyword' => false,
            'aggregate_rating' => false,
            'de_event_title' => false,
            'de_short_event_title' => false,
            'de_event_abstract' => false,
            'de_description' => false,
            'de_about_target_audience' => false,
            'de_ulteriori_informazioni' => false,
            'de_event_content_keyword' => false,
        ];
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $eventToString = function ($dataMap){
            $from = $dataMap['from_time']->toString();
            $to = $dataMap['to_time']->toString();
            if (empty($to)){
                $to = $from + 3600;
            }

            return implode(' - ', [
                date('j/n/Y H:i', $from),
                date('j/n/Y H:i', $to),
            ]);
        };

        $options['time_interval_events'][$node->attribute('contentobject_id')] = [];
        $options['time_interval_events'][$node->attribute('contentobject_id')][] = $eventToString($node->dataMap());
        if (isset($node->clones)){
            foreach ($node->clones as $clone){
                $options['time_interval_events'][$node->attribute('contentobject_id')][] = $eventToString($clone->dataMap());
            }
        }

        $places = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $luogo_svolgimento = $firstLocalizedContentData['luogo_svolgimento']['content'];
            $geo = $firstLocalizedContentData['geo']['content'];
            if ($geo['latitude'] != 0 && $geo['longitude'] != 0){
                $geo['address'] = str_replace('amp;', '', $geo['address']);
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $placeId = $id . ':place';
                $placeName = $luogo_svolgimento ?? $name;
                $place = ocm_place::instanceBy('name', $placeName, $placeId);
                $place->setAttribute('has_address', json_encode($geo));

                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            }
            return '';
        };

        $mapper = [
            'event_title' => OCMigration::getMapperHelper('titolo'),
            'short_event_title' => OCMigration::getMapperHelper('short_title'),
            'event_abstract' => false,
            'description' => OCMigration::getMapperHelper('text'),
            'image_file' => OCMigration::getMapperHelper('image/url'),
            'image' => function(){return '';}, //@todo
            'video' => false,
            'sub_event_of' => OCMigration::getMapperHelper('iniziativa'),
            'target_audience' => false,
            'about_target_audience' => OCMigration::getMapperHelper('destinatari'),
            'has_playbill' => OCMigration::getMapperHelper('file'),
            'attachment' => false,
            'has_online_contact_point' => false,
            'has_public_event_typology' => false, //OCMigration::getMapperHelper('tipo_evento'),
            'topics' => false,
            'time_interval_events' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return implode(PHP_EOL, $options['time_interval_events'][$content->metadata->id]);
            },
            'takes_place_in' => $places,
            'attendee' => false,
            'is_accessible_for_free' => false,
            'cost_notes' => OCMigration::getMapperHelper('costi'),
            'has_offer' => false,
            'ulteriori_informazioni' => OCMigration::getMapperHelper('informazioni'),
            'organizer' => OCMigration::getMapperHelper('associazione'),
            'funder' => false,
            'sponsor' => false,
            'related_event' => false,
            'composer' => false,
            'performer' => false,
            'translator' => false,
            'in_language' => false,
            'maximum_attendee_capacity' => false,
            'event_content_keyword' => false,
            'aggregate_rating' => false,
        ];

        return $this->fromNode($node, $mapper, $options);
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo evento*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            "Titolo dell'evento*" => $this->attribute('event_title'),
            "Sottotitolo" => $this->attribute('short_event_title'),
            "Descrizione breve*" => $this->attribute('event_abstract'),
            "Descrizione*" => $this->attribute('description'),
            "File immagine" => $this->attribute('image_file'),
            "Immagini" => $this->attribute('image'),
            "Video" => $this->attribute('video'),
            "Iniziativa di cui fa parte" => $this->attribute('sub_event_of'),
            "Destinatari*" => $this->attribute('target_audience'),
            "A chi è rivolto" => $this->attribute('about_target_audience'),
            "Locandina" => $this->attribute('has_playbill'),
            "Allegati" => $this->attribute('attachment'),
            "Contatti*" => $this->attribute('has_online_contact_point'),
            "Tipo di evento*" => $this->attribute('has_public_event_typology'),
            "Argomenti*" => $this->attribute('topics'),
            "Date ed orari dell'evento*" => $this->attribute('time_interval_events'),
            "Ripetizioni evento (formato ical)" => $this->attribute('time_interval_ical'),
            "Luogo dell'evento" => $this->attribute('takes_place_in'),
            "Partecipano" => $this->attribute('attendee'),
            "È gratuito" => $this->attribute('is_accessible_for_free'),
            "Informazioni sui costi" => $this->attribute('cost_notes'),
            "Costo" => $this->attribute('has_offer'),
            "Ulteriori informazioni" => $this->attribute('ulteriori_informazioni'),
            "Organizzato da" => $this->attribute('organizer'),
            "Patrocinato da" => $this->attribute('funder'),
            "Sponsorizzato da" => $this->attribute('sponsor'),
            "Appuntamenti" => '',
            "Persone o organizzazioni" => $this->attribute('composer'),
            "Chi presenta" => $this->attribute('performer'),
            "Servizio di traduzione curato da" => $this->attribute('translator'),
            "Lingue usate all'evento" => $this->attribute('in_language'),
            "Numero massimo di posti" => $this->attribute('maximum_attendee_capacity'),
            "Parole chiave" => $this->attribute('event_content_keyword'),
            "Valutazione dell'evento" => $this->attribute('aggregate_rating'),
            'Veranstaltungstitel* [de]' => $this->attribute('de_event_title'),
            'Kurze Beschreibung* [de]' => $this->attribute('de_event_abstract'),
            'Beschreibung* [de]' => $this->attribute('de_description'),
            'An wen es gerichtet ist [de]' => $this->attribute('de_about_target_audience'),
            'Weitere Informationen [de]' => $this->attribute('de_ulteriori_informazioni'),
            'Stichwort [de]' => $this->attribute('de_event_content_keyword'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo evento*"]);
        $item->setAttribute('event_title', $row["Titolo dell'evento*"]);
        $item->setAttribute('short_event_title', $row["Sottotitolo"]);
        $item->setAttribute('event_abstract', $row["Descrizione breve*"]);
        $item->setAttribute('description', $row["Descrizione*"]);
        $item->setAttribute('image_file', $row["File immagine"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('video', $row["Video"]);
        $item->setAttribute('sub_event_of', $row["Iniziativa di cui fa parte"]);
        $item->setAttribute('target_audience', $row["Destinatari*"]);
        $item->setAttribute('about_target_audience', $row["A chi è rivolto"]);
        $item->setAttribute('has_playbill', $row["Locandina"]);
        $item->setAttribute('attachment', $row["Allegati"]);
        $item->setAttribute('has_online_contact_point', $row["Contatti*"]);
        $item->setAttribute('has_public_event_typology', $row["Tipo di evento*"]);
        $item->setAttribute('topics', $row["Argomenti*"]);
        $item->setAttribute('time_interval_events', $row["Date ed orari dell'evento*"]);
        $item->setAttribute('time_interval_ical', $row["Ripetizioni evento (formato ical)"]);
        $item->setAttribute('takes_place_in', $row["Luogo dell'evento"]);
        $item->setAttribute('attendee', $row["Partecipano"]);
        $item->setAttribute('is_accessible_for_free', $row["È gratuito"]);
        $item->setAttribute('cost_notes', $row["Informazioni sui costi"]);
        $item->setAttribute('has_offer', $row["Costo"]);
        $item->setAttribute('ulteriori_informazioni', $row["Ulteriori informazioni"]);
        $item->setAttribute('organizer', $row["Organizzato da"]);
        $item->setAttribute('funder', $row["Patrocinato da"]);
        $item->setAttribute('sponsor', $row["Sponsorizzato da"]);
        $item->setAttribute('related_event', $row["Appuntamenti"]);
        $item->setAttribute('composer', $row["Persone o organizzazioni"]);
        $item->setAttribute('performer', $row["Chi presenta"]);
        $item->setAttribute('translator', $row["Servizio di traduzione curato da"]);
        $item->setAttribute('in_language', $row["Lingue usate all'evento"]);
        $item->setAttribute('maximum_attendee_capacity', $row["Numero massimo di posti"]);
        $item->setAttribute('event_content_keyword', $row["Parole chiave"]);
        $item->setAttribute('aggregate_rating', $row["Valutazione dell'evento"]);

        $item->setAttribute('de_event_title', $row['Veranstaltungstitel* [de]']);
        $item->setAttribute('de_event_abstract', $row['Kurze Beschreibung* [de]']);
        $item->setAttribute('de_description', $row['Beschreibung* [de]']);
        $item->setAttribute('de_about_target_audience', $row['An wen es gerichtet ist [de]']);
        $item->setAttribute('de_ulteriori_informazioni', $row['Weitere Informationen [de]']);
        $item->setAttribute('de_event_content_keyword', $row['Stichwort [de]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('event');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->getNodeIdFromRemoteId('all-events'));
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'event_title', trim($this->attribute('event_title')));
        $payload->setData($locale, 'short_event_title', trim($this->attribute('short_event_title')));
        $payload->setData($locale, 'event_abstract', trim($this->attribute('event_abstract')));
        $payload->setData($locale, 'description', trim($this->attribute('description')));
//@todo        $payload->setData($locale, 'image_file', trim($this->attribute('image_file')));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'video', trim($this->attribute('video')));
        $payload->setData($locale, 'target_audience', $this->formatTags($this->attribute('target_audience')));
        $payload->setData($locale, 'about_target_audience', trim($this->attribute('about_target_audience')));
        $payload->setData($locale, 'has_playbill', $this->formatBinary($this->attribute('has_playbill'), false));
        $payload->setData($locale, 'attachment', $this->formatBinary($this->attribute('attachment')));
        $payload->setData($locale, 'has_online_contact_point', ocm_online_contact_point::getIdListByName($this->attribute('has_online_contact_point')));
        $payload->setData($locale, 'has_public_event_typology', $this->formatTags($this->attribute('has_public_event_typology')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));


        $recurr = $this->attribute('time_interval_ical');
        $customEvents = [];
        if (!empty($this->attribute('time_interval_events'))){
            $events = explode(PHP_EOL, $this->attribute('time_interval_events'));

            if (
                strpos($this->attribute('time_interval_events'), '-') === false
                && (count($events) === 2 || count($events) & 2 === 0)
            ){
                $startEndChunks = array_chunk($events, 2);
                foreach ($startEndChunks as $startEndChunk){
                    $start = $startEndChunk[0] ?? '';
                    $end = $startEndChunk[1] ?? '';
                    if ($start && $end) {
                        $start = self::getDateTimePayload($start, false);
                        $end = self::getDateTimePayload($end, false);
                    }
                    if ($start && $end) {
                        $customEvents[] = "{$start}-{$end}";
                    }
                }
            }else {
                foreach ($events as $event) {
                    [$start, $end] = explode('-', $event);
                    if ($start && $end) {
                        $start = self::getDateTimePayload($start, false);
                        $end = self::getDateTimePayload($end, false);
                    }
                    if ($start && $end) {
                        $customEvents[] = "{$start}-{$end}";
                    }
                }
            }
        }
        $intervalStrings = [$recurr];
        if (count($customEvents)){
            $intervalStrings[] = implode('|', $customEvents);
        }
        $intervalString = implode('#', $intervalStrings);
        if (!empty($intervalString)) {
            $payload->setData($locale, 'time_interval', $intervalString);
        }

        $payload->setData($locale, 'takes_place_in', ocm_place::getIdListByName($this->attribute('takes_place_in')));
        $payload->setData($locale, 'attendee', ocm_public_person::getIdListByName($this->attribute('attendee')));
        $payload->setData($locale, 'is_accessible_for_free', intval($this->attribute('is_accessible_for_free')));
        $payload->setData($locale, 'cost_notes', trim($this->attribute('cost_notes')));
//@todo
//        $payload->setData($locale, 'has_offer', trim($this->attribute('has_offer')));
        $payload->setData($locale, 'ulteriori_informazioni', trim($this->attribute('ulteriori_informazioni')));
        $payload->setData($locale, 'organizer', ocm_private_organization::getIdListByName($this->attribute('organizer')));
        $payload->setData($locale, 'funder', ocm_private_organization::getIdListByName($this->attribute('funder')));
        $payload->setData($locale, 'sponsor', ocm_private_organization::getIdListByName($this->attribute('sponsor')));
        $payload->setData($locale, 'composer', ocm_private_organization::getIdListByName($this->attribute('composer')));
        $payload->setData($locale, 'performer', ocm_private_organization::getIdListByName($this->attribute('performer')));
        $payload->setData($locale, 'translator', ocm_private_organization::getIdListByName($this->attribute('translator')));
        $payload->setData($locale, 'in_language', trim($this->attribute('in_language')));
        $payload->setData($locale, 'maximum_attendee_capacity', intval($this->attribute('maximum_attendee_capacity')));
        $payload->setData($locale, 'event_content_keyword', trim($this->attribute('event_content_keyword')));
        $payload->setData($locale, 'aggregate_rating', trim($this->attribute('aggregate_rating')));

        $payload = $this->appendTranslationsToPayloadIfNeeded($payload);
        $payloads = [self::getImportPriority() => $payload];
        $subEvents = ocm_event::getIdListByName($this->attribute('sub_event_of'), 'event_title');
        if (count($subEvents) > 0) {
            $payload2 = clone $payload;
            $payload2->unSetData();
            $payload2->setData($locale, 'sub_event_of', $subEvents);
            if (in_array('ger-DE', $payload->getMetadaData('languages'))){
                $payload2->setData('ger-DE', 'sub_event_of', $subEvents);
            }
            $payloads[ocm_event::getImportPriority()+1] = $payload2;
        }

        return $payloads;
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Tipo di evento*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('eventi'),
            ],
            "Argomenti*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Immagini" => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Iniziativa di cui fa parte" => [
                'strict' => false,
                'ref' => ocm_event::getRangeRef()
            ],
            "Contatti*" => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            "Luogo dell'evento" => [
                'strict' => false,
                'ref' => ocm_place::getRangeRef()
            ],
            "Partecipano" => [
                'strict' => false,
                'ref' => ocm_public_person::getRangeRef()
            ],
            "Organizzato da" => $organizations = [
                'strict' => false,
                'ref' => ocm_private_organization::getRangeRef()
            ],
            "Patrocinato da" => $organizations,
            "Sponsorizzato da" => $organizations,
            "Persone o organizzazioni" => $organizations,
            "Servizio di traduzione curato da" => $organizations,
            "Destinatari*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('popolazione'),
            ]
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*",
            "Descrizione"
        ];
    }

    public static function getImportPriority(): int
    {
        return 180;
    }

}