<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_pagina_sito extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static function canImport(): bool
    {
        return false;
    }

    public static function canPull(): bool
    {
        return false;
    }

    public static function canPush(): bool
    {
        return OCMigration::discoverContext() === 'comunweb';
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'comunweb';
    }

    public static $fields = [
        'name',
        'short_name',
        'abstract',
        'description',
        'image___name',
        'image___url',
        'gps',
        'riferimento',
        'files',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Pagine del sito';
    }

    public static function getColumnName(): string
    {
        return 'Nome';
    }

    public static function getIdColumnLabel(): string
    {
        return "ID";
    }

    protected function getComunwebFieldMapper(): array
    {
        $attachments = function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
            $data = [];
            $object = eZContentObject::fetch($content->metadata['id']);
            if ($object instanceof eZContentObject){

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

        $mapper = array_fill_keys(static::$fields, false);
        $mapper['files'] = $attachments;

        return $mapper;
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $options['remove_ezxml_embed'] = true;
//        $options['ezxml_strip_tags'] = true;
        return $this->fromNode($node, $this->getComunwebFieldMapper(), $options);
    }

    public function toSpreadsheet(): array
    {
        $address = json_decode($this->attribute('gps'), true);
        return [
            'ID'=> $this->attribute('_id'),
            'Nome' => $this->attribute('name'),
            'Nome breve' => $this->attribute('short_name'),
            'Abstract (Descrizione breve)' => $this->attribute('abstract'),
            'Descrizione' => $this->attribute('description'),
            'Nome immagine' => $this->attribute('image___name'),
            'File immagine' => $this->attribute('image___url'),
            'Indirizzo' => $address['address'],
            "Latitudine e longitudine" => $address['latitude'] . ' ' . $address['longitude'],
            'Riferimento' => $this->attribute('riferimento'),
            "File allegati" => $this->attribute('files'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function getRangeValidationHash(): array
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
        return -120;
    }


}