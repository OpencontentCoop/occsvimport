<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_event extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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
                $place = new ocm_place();
                $place->setAttribute('_id', $placeId);
                $place->setAttribute('name', $placeName);
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
            'has_public_event_typology' => OCMigration::getMapperHelper('tipo_evento'),
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
            "Appuntamenti" => $this->attribute('related_event'),
            "Persone o organizzazioni" => $this->attribute('composer'),
            "Chi presenta" => $this->attribute('performer'),
            "Servizio di traduzione curato da" => $this->attribute('translator'),
            "Lingue usate all'evento" => $this->attribute('in_language'),
            "Numero massimo di posti" => $this->attribute('maximum_attendee_capacity'),
            "Parole chiave" => $this->attribute('event_content_keyword'),
            "Valutazione dell'evento" => $this->attribute('aggregate_rating'),
        ];
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