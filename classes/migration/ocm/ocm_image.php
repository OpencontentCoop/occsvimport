<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_image extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'de_name',
        'caption',
        'de_caption',
        'image___name',
        'image___url',
        'tags',
        'license',
        'proprietary_license',
        'proprietary_license_source',
        'author',
        'de_author',
    ];

    protected function getOpencityFieldMapper(): array
    {
        return [
            'name' => function(Content $content){
                return $content->data['ita-IT']['name']['content'] ?? '';
            },
            'de_name' => function(Content $content){
                return $content->data['ger-DE']['name']['content'] ?? '';
            },
            'caption' => function(Content $content){
                return $content->data['ita-IT']['caption']['content'] ?? '';
            },
            'de_caption' => function(Content $content){
                return $content->data['ger-DE']['caption']['content'] ?? '';
            },
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'tags' => false,
            'license' => false,
            'proprietary_license' => false,
            'proprietary_license_source' => false,
            'author' => function(Content $content){
                return $content->data['ita-IT']['author']['content'] ?? '';
            },
            'de_author' => function(Content $content){
                return $content->data['ger-DE']['author']['content'] ?? '';
            },
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Immagini';
    }

    public static function getIdColumnLabel(): string
    {
        return "ID*";
    }

    public function toSpreadsheet(): array
    {
        return [
            'ID*' => $this->attribute('_id'),
            'Nome*' => $this->attribute('name'),
            'Name* [de]' => $this->attribute('de_name'),
            'Didascalia' => $this->attribute('caption'),
            'Didascalia [de]' => $this->attribute('caption'),
            'Nome del file' => $this->attribute('image___name'),
            'Url al file*' => $this->attribute('image___url'),
            'Tags' => $this->attribute('tags'),
            'Licenza di utilizzo*' => $this->attribute('license'),
            'Licenza proprietaria' => $this->attribute('proprietary_license'),
            'Fonte della licenza proprietaria' => $this->attribute('proprietary_license_source'),
            'Autore*' => $this->attribute('author'),
            'Autor* [de]' => $this->attribute('de_author'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['ID*']);
        $item->setAttribute('name', $row['Nome*']);
        $item->setAttribute('name', $row['Name* [de]']);
        $item->setAttribute('caption', $row['Didascalia']);
        $item->setAttribute('de_caption', $row['Didascalia [de]']);
        $item->setAttribute('image___name', $row['Nome del file']);
        $item->setAttribute('image___url', $row['Url al file*']);
        $item->setAttribute('tags', $row['Tags']);
        $item->setAttribute('license', $row['Licenza di utilizzo*']);
        $item->setAttribute('proprietary_license', $row['Licenza proprietaria']);
        $item->setAttribute('proprietary_license_source', $row['Fonte della licenza proprietaria']);
        $item->setAttribute('author', $row['Autore*']);
        $item->setAttribute('author', $row['Autor* [de]']);

        return $item;
    }

    public static function getImportPriority(): int
    {
        return -1;
    }

    public function generatePayload(): array
    {
        return [];
    }
}