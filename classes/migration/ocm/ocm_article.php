<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_article extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'title',
        'de_title',
        'content_type',
        'abstract',
        'de_abstract',
        'published',
        'dead_line',
        'id_comunicato',
        'topics',
        'image',
        'image_file',
        'body',
        'de_body',
        'people',
        'location',
        'video',
        'author',
        'attachment',
        'files',
        'dataset',
        'reading_time',
        'related_service',
    ];

    public static function getSortField(): string
    {
        return 'title';
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Avvisi - Notizie - Comunicati';
    }

    public static function getColumnName(): string
    {
        return "Titolo della notizia*";
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo dell\'articolo*';
    }

    protected function getComunwebFieldMapper(): array
    {
        $attachments = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $data = [];
            $object = eZContentObject::fetch($content->metadata['id']);
            if ($object instanceof eZContentObject){

                $attributes = ['file', ];
                foreach ($attributes as $attribute) {
                    $fileByAttribute = OCMigrationComunweb::getFileAttributeUrl($object, $attribute);
                    if ($fileByAttribute) {
                        $data[] = $fileByAttribute;
                    }
                }

                /** @var eZContentObject[] $embedList */
                $embedList = $object->relatedContentObjectList();
                foreach ($embedList as $embed){
                    if (in_array($embed->contentClassIdentifier(), ['file', 'file_pdf'])){
                        ocm_file::removeById($embed->attribute('remote_id'));
                        $url = OCMigrationComunweb::getFileAttributeUrl($embed);
                        if ($url){
                            $data[] = $url;
                        }
                    }
                }
            }
            $attachments = OCMigrationComunweb::getAttachmentsByNode($object->mainNode());
            foreach ($attachments as $attachment){
                ocm_file::removeById($attachment->object()->attribute('remote_id'));
                $url = OCMigrationComunweb::getFileAttributeUrl($attachment);
                if ($url){
                    $data[] = $url;
                }
            }

            return implode(PHP_EOL, $data);
        };

        $places = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $gps = $firstLocalizedContentData['gps']['content'];
            if ($gps['latitude'] != 0 && $gps['longitude'] != 0){
                $gps['address'] = str_replace('amp;', '', $gps['address']);
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $placeId = $id . ':place';
                $placeName = $gps['address'];
                $place = ocm_place::instanceBy('name', $placeName, $placeId);
                $place->setAttribute('has_address', json_encode($gps));

                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            }
            return '';
        };

        return [
            'title' => OCMigration::getMapperHelper('titolo'),
            'content_type' => function(){
                return 'Notizia';
            },
            'abstract' => OCMigration::getMapperHelper('titolo'),
            'published' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $published = OCMigration::getMapperHelper('data_iniziopubblicazione')(
                    $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options
                );
                if (empty($published)){
                    $published = date('j/n/Y', strtotime($content->metadata['published']));
                }
                return $published;
            },
            'dead_line' => OCMigration::getMapperHelper('data_archiviazione'),
            'id_comunicato' => false,
            'topics' => false,
            'image' => function(){
                return '';
            },
            'image_file' => OCMigration::getMapperHelper('image/url'),
            'body' => OCMigration::getMapperHelper('descrizione'),
            'people' => false,
            'location' => $places,
            'video' => false,
            'author' => false,
            'attachment' => OCMigration::getMapperHelper('riferimento'),
            'files' => $attachments,
            'dataset' => false,
            'reading_time' => false,
            'related_service' => false,
        ];
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $options['remove_ezxml_embed'] = true;
        return $this->fromNode($node, $this->getComunwebFieldMapper(), $options);
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo dell'articolo*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            "Titolo della notizia*" => $this->attribute('title'),
            "Nachrichtentitel* [de]" => $this->attribute('de_title'),
            "Tipo di notizia*" => $this->attribute('content_type'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "Kurze Beschreibung* [de]" => $this->attribute('de_abstract'),
            "Data della notizia*" => $this->attribute('published'),
            "Data di scadenza" => $this->attribute('dead_line'),
            "Numero progressivo comunicato stampa" => $this->attribute('id_comunicato'),
            "Argomenti*" => $this->attribute('topics'),
            "Immagini" => $this->attribute('image'),
            "File immagine" => $this->attribute('image_file'),
            "Testo completo della notizia*" => $this->attribute('body'),
            "Haupttext der Nachricht* [de]" => $this->attribute('de_body'),
            "Persone" => $this->attribute('people'),
            "Luoghi" => $this->attribute('location'),
            "Video" => $this->attribute('video'),
            "A cura di*" => $this->attribute('author'),
            "Documenti allegati" => $this->attribute('attachment'),
            "File allegati" => $this->attribute('files'),
            "Dataset" => $this->attribute('dataset'),
            "Tempo di lettura" => $this->attribute('reading_time'),
            "Riferimento al servizio pubblico" => $this->attribute('related_service'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo dell'articolo*"]);
        $item->setAttribute('title', $row["Titolo della notizia*"]);
        $item->setAttribute('de_title', $row["Nachrichtentitel* [de]"]);
        $item->setAttribute('content_type', $row["Tipo di notizia*"]);
        $item->setAttribute('abstract', $row["Descrizione breve*"]);
        $item->setAttribute('de_abstract', $row["Kurze Beschreibung* [de]"]);
        $item->setAttribute('published', $row["Data della notizia*"]);
        $item->setAttribute('dead_line', $row["Data di scadenza"]);
        $item->setAttribute('id_comunicato', $row["Numero progressivo comunicato stampa"]);
        $item->setAttribute('topics', $row["Argomenti*"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('image_file', $row["File immagine"]);
        $item->setAttribute('body', $row["Testo completo della notizia*"]);
        $item->setAttribute('de_body', $row["Haupttext der Nachricht* [de]"]);
        $item->setAttribute('people', $row["Persone"]);
        $item->setAttribute('location', $row["Luoghi"]);
        $item->setAttribute('video', $row["Video"]);
        $item->setAttribute('author', $row["A cura di*"]);
        $item->setAttribute('attachment', $row["Documenti allegati"]);
        $item->setAttribute('files', $row["File allegati"]);
        $item->setAttribute('dataset', $row["Dataset"]);
        $item->setAttribute('reading_time', $row["Tempo di lettura"]);
        $item->setAttribute('related_service', $row["Riferimento al servizio pubblico"]);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('article');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->discoverParentNode());
        $payload->setLanguages([$locale]);
        $payload->setData($locale, 'title', trim($this->attribute('title')));
        $payload->setData($locale, 'content_type', $this->formatTags($this->attribute('content_type')));
        $payload->setData($locale, 'abstract', trim($this->attribute('abstract')));
        $payload->setData($locale, 'published', $this->formatDate($this->attribute('published')));
        $payload->setData($locale, 'dead_line', $this->formatDate($this->attribute('dead_line')));
        $payload->setData($locale, 'id_comunicato', trim($this->attribute('id_comunicato')));
        $payload->setData($locale, 'topics', OCMigration::getTopicsIdListFromString($this->attribute('topics')));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
//todo
//        $payload->setData($locale, 'image_file', trim($this->attribute('image_file')));
        $payload->setData($locale, 'body', trim($this->attribute('body')));
        $payload->setData($locale, 'people', ocm_public_person::getTypedPersonIdListByName($this->attribute('people')));
        $payload->setData($locale, 'location', ocm_place::getIdListByName($this->attribute('location')));
        $payload->setData($locale, 'video', trim($this->attribute('video')));
        $payload->setData($locale, 'author', ocm_organization::getIdListByName($this->attribute('author')));
        $payload->setData($locale, 'attachment', ocm_document::getIdListByName($this->attribute('attachment')));
        $payload->setData($locale, 'files', $this->formatBinary($this->attribute('files')));
//@todo
//        $payload->setData($locale, 'dataset', trim($this->attribute('dataset')));
        $payload->setData($locale, 'reading_time', intval($this->attribute('reading_time')));


//@todo da impostare in seconda battuta quando impostati i servizi
//        $payload->setData($locale, 'related_service', ocm_public_service::getIdListByName($this->attribute('related_service')));

        return $this->appendTranslationsToPayloadIfNeeded($payload);
    }

    protected function discoverParentNode(): int
    {
        if (in_array('Avviso', $this->formatTags($this->attribute('type')))){
            return $this->getNodeIdFromRemoteId('9a1756e11164d0d550ee950657154db8');
        }

        if (in_array('Comunicato stampa', $this->formatTags($this->attribute('type')))){
            return $this->getNodeIdFromRemoteId('16a65071f99a1be398a677e5e4bef93f');
        }

        return $this->getNodeIdFromRemoteId('ea708fa69006941b4dc235a348f1431d');
    }

    public static function getDateValidationHeaders(): array
    {
        return [
            "Data della notizia*",
            "Data di scadenza"
        ];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*",
            "Kurze Beschreibung* [de]",
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Tipo di notizia*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('notizie'),
            ],
            "Argomenti*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('argomenti'),
            ],
            "Immagini" => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
            "Persone" => [
                'strict' => false,
                'ref' => ocm_public_person::getRangeRef()
            ],
            "Luoghi" => [
                'strict' => false,
                'ref' => ocm_place::getRangeRef()
            ],
            "A cura di*" => [
                'strict' => false,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Documenti allegati" => [
                'strict' => false,
                'ref' => ocm_document::getRangeRef()
            ],
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve*",
            "Testo completo della notizia*"
        ];
    }

    public static function getImportPriority(): int
    {
        return 110;
    }

}