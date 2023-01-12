<?php

class ocm_image extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'name',
        'caption',
        'image___name',
        'image___url',
        'tags',
        'license',
        'proprietary_license',
        'proprietary_license_source',
        'author',
    ];

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
            'Didascalia' => $this->attribute('caption'),
            'Nome del file' => $this->attribute('image___name'),
            'Url al file*' => $this->attribute('image___url'),
            'Tags' => $this->attribute('tags'),
            'Licenza di utilizzo*' => $this->attribute('license'),
            'Licenza proprietaria' => $this->attribute('proprietary_license'),
            'Fonte della licenza proprietaria' => $this->attribute('proprietary_license_source'),
            'Autore*' => $this->attribute('author'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row['ID*']);
        $item->setAttribute('name', $row['Nome*']);
        $item->setAttribute('caption', $row['Didascalia']);
        $item->setAttribute('image___name', $row['Nome del file']);
        $item->setAttribute('image___url', $row['Url al file*']);
        $item->setAttribute('tags', $row['Tags']);
        $item->setAttribute('license', $row['Licenza di utilizzo*']);
        $item->setAttribute('proprietary_license', $row['Licenza proprietaria']);
        $item->setAttribute('proprietary_license_source', $row['Fonte della licenza proprietaria']);
        $item->setAttribute('author', $row['Autore*']);

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