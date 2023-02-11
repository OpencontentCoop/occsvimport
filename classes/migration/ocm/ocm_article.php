<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_article extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'title',
        'content_type',
        'abstract',
        'published',
        'dead_line',
        'id_comunicato',
        'topics',
        'image',
        'image_file',
        'body',
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
                $place = new ocm_place();
                $place->setAttribute('_id', $placeId);
                $place->setAttribute('name', $placeName);
                $place->setAttribute('has_address', json_encode($gps));

                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $place->setNodeReference($node);
                $place->storeThis($options['is_update']);

                return $placeName;
            }
            return '';
        };

        $options['remove_ezxml_embed'] = true;

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

    public function toSpreadsheet(): array
    {
        return [
            "Identificativo dell'articolo*" => $this->attribute('_id'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            "Titolo della notizia*" => $this->attribute('title'),
            "Tipo di notizia*" => $this->attribute('content_type'),
            "Descrizione breve*" => $this->attribute('abstract'),
            "Data della notizia*" => $this->attribute('published'),
            "Data di scadenza" => $this->attribute('dead_line'),
            "Numero progressivo comunicato stampa" => $this->attribute('id_comunicato'),
            "Argomenti*" => $this->attribute('topics'),
            "Immagini" => $this->attribute('image'),
            "File immagine" => $this->attribute('image_file'),
            "Testo completo della notizia*" => $this->attribute('body'),
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

    public static function getDateValidationHeaders(): array
    {
        return [
            "Data della notizia*",
            "Data di scadenza"
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