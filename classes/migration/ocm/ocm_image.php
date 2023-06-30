<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_image extends OCMPersistentObject implements ocm_interface
{
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

    public function avoidNameDuplication()
    {
        return false;
    }

    protected function getOpencityFieldMapper(): array
    {
        return [
            'name' => false,
            'de_name' => false,
            'caption' => false,
            'de_caption' => false,
            'image___name' => OCMigration::getMapperHelper('image/name'),
            'image___url' => OCMigration::getMapperHelper('image/url'),
            'tags' => false,
            'license' => false,
            'proprietary_license' => false,
            'proprietary_license_source' => false,
            'author' => false,
            'de_author' => false,
        ];
    }

    protected function getComunwebFieldMapper(): array
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
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Immagini';
    }

    public static function getColumnName(): string
    {
        return "Nome*";
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
            'Untertitel [de]' => $this->attribute('caption'),
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
        $item->setAttribute('de_name', $row['Name* [de]']);
        $item->setAttribute('caption', $row['Didascalia']);
        $item->setAttribute('de_caption', $row['Untertitel [de]']);
        $item->setAttribute('image___name', $row['Nome del file']);
        $item->setAttribute('image___url', $row['Url al file*']);
        $item->setAttribute('tags', $row['Tags']);
        $item->setAttribute('license', $row['Licenza di utilizzo*']);
        $item->setAttribute('proprietary_license', $row['Licenza proprietaria']);
        $item->setAttribute('proprietary_license_source', $row['Fonte della licenza proprietaria']);
        $item->setAttribute('author', $row['Autore*']);
        $item->setAttribute('de_author', $row['Autor* [de]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('image');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode(51);
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'name', $this->attribute('name'));
        $payload->setData($locale, 'caption', $this->attribute('caption'));
        $filename = $this->attribute('image___name');
        if (empty($filename)){
            $filename = basename($this->attribute('image___url'));
        }
        $payload->setData($locale, 'image', [
            'url' => $this->attribute('image___url'),
            'filename' => $filename,
        ]);
        $payload->setData($locale, 'tags', $this->attribute('tags'));
        $license = 'Creative Commons Attribution 4.0 International (CC BY 4.0)';
        if (!empty($this->attribute('license'))){
            $license = $this->attribute('license');
        }
        $payload->setData($locale, 'license', $this->formatTags($license));
        $payload->setData($locale, 'proprietary_license', $this->attribute('proprietary_license'));
        $payload->setData($locale, 'proprietary_license_source', $this->attribute('proprietary_license_source'));
        $payload->setData($locale, 'author', $this->attribute('author'));

        return $this->appendTranslationsToPayloadIfNeeded($payload);
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            'Url al file*'
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Licenza di utilizzo*' => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('licenze'),
            ],
        ];
    }


    public static function getImportPriority(): int
    {
        return -1;
    }

    public static function importSingleImage($url)
    {
        $image = new ocm_image();
        $name = OpenPABootstrapItaliaOperators::cleanFileName(basename($url));
        $image->setAttribute('_id', 'ocm_autoimage_' . md5($url));
        $image->setAttribute('name', $name);
        $image->setAttribute('image___name', basename($url));
        $image->setAttribute('image___url', $url);
        $image->setAttribute('author', $name);
        $image->setAttribute('de_author', $name);
        OCMPayload::create(
            $image->attribute('_id'),
            'ocm_image',
            ocm_image::getImportPriority(),
            $image->generatePayload()->getArrayCopy()
        );
        return $image->id();
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        $data = [];
        $names = explode(PHP_EOL, $name);
        if (!self::isEmptyArray($names)){

            foreach ($names as $maybeAnUrl){
                if (strpos($name, '/var/') !== false){
                    if (strpos($maybeAnUrl, 'http') === false){
                        $url = $maybeAnUrl;

                        $anImageQuery = ocm_image::fetchObjectList(
                            ocm_image::definition(), null, ['_original_url' => ['like', 'http%']], null, ['limit' => 1]
                        );
                        $anImage = $anImageQuery[0] ?? false;
                        while ($anImage === false){
                            foreach (OCMigration::getAvailableClasses() as $class){
                                $anImageQuery = $class::fetchObjectList(
                                    $class::definition(), null, ['_original_url' => ['like', 'http%']], null, ['limit' => 1]
                                );
                                $anImage = $anImageQuery[0] ?? false;
                                if ($anImage){
                                    break;
                                }
                            }
                        }
                        if ($anImage instanceof OCMPersistentObject){
                            $baseUrl = parse_url($anImage->attribute('_original_url'), PHP_URL_HOST);
                            $url = 'https://' . $baseUrl . $url;
                        }

                        $image = new ocm_image();
                        $name = OpenPABootstrapItaliaOperators::cleanFileName(basename($url));
                        $image->setAttribute('_id', 'ocm_autoimage_' . md5($url));
                        $image->setAttribute('name', $name);
                        $image->setAttribute('image___name', basename($url));
                        $image->setAttribute('image___url', $url);
                        $image->setAttribute('author', $name);
                        $image->setAttribute('de_author', $name);
                        OCMPayload::create(
                            $image->attribute('_id'),
                            'ocm_image',
                            ocm_image::getImportPriority(),
                            $image->generatePayload()->getArrayCopy()
                        );
                        $data[] = $image->id();
                    }
                }
            }

            $names = self::trimArray($names);
            /** @var ocm_image[] $list */
            $list = ocm_image::fetchObjectList(
                ocm_image::definition(), null,
                ['trim(' . $field . ')' => [$names]]
            );
            foreach ($list as $item){
                $data[] = $item->id();
                OCMPayload::create(
                    $item->attribute('_id'),
                    'ocm_image',
                    ocm_image::getImportPriority(),
                    $item->generatePayload()->getArrayCopy()
                );
            }
        }

        return $data;
    }

    public function storePayload(): int
    {
        return 0;
    }

    public function forceStorePayload(): int
    {
        return parent::storePayload();
    }

    public static function checkPayloadGeneration(): bool
    {
        return false;
    }

    public static function getDefaultImage($context)
    {
        $image = new ocm_image();
        $url = 'https://s3.eu-west-1.amazonaws.com/static.opencity.opencontent.it/migration/default_' . $context . '.jpg';
        $name = 'Immagine predefinita';
        $image->setAttribute('_id', 'ocm_default_image_' . md5($url));
        $image->setAttribute('name', $name);
        $image->setAttribute('image___name', basename($url));
        $image->setAttribute('image___url', $url);
        $image->setAttribute('author', 'Assistente migrazione');
        OCMPayload::create(
            $image->attribute('_id'),
            'ocm_image',
            ocm_image::getImportPriority(),
            $image->generatePayload()->getArrayCopy()
        );
        return [$image->id()];
    }
}