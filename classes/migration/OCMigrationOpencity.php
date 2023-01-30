<?php

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\AttributeConverter\Base;
use Opencontent\Opendata\Api\AttributeConverterLoader;

class OCMigrationOpencity extends OCMigration implements OCMigrationInterface
{
    public function __construct()
    {
        /** @var eZUser $user */
        $user = eZUser::fetchByName('admin');
        eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'), eZUser::NO_SESSION_REGENERATE);
        parent::__construct();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function fillData(array $namesFilter = [], $isUpdate = false)
    {
        if (empty($namesFilter) || in_array('ocm_image', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['image']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_image(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_opening_hours_specification', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['opening_hours_specification']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_opening_hours_specification(), [
                    'matrix_converter' => 'multiline',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_online_contact_point', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['online_contact_point']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_online_contact_point(), [
                    'matrix_converter' => 'json',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_document', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['document']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_document(), [
                    'matrix_converter' => 'json',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_place', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['place']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_place(), [
                    'matrix_converter' => 'json',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_organization', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList([
                'administrative_area',
                'homogeneous_organizational_area',
                'office',
                'political_body',
            ]);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_organization(), [
                    'matrix_converter' => 'json',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_public_person', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList([
                'employee',
                'politico',
            ]);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_public_person(), [
                    'matrix_converter' => 'json',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_time_indexed_role', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['time_indexed_role']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_time_indexed_role(), [
                    'matrix_converter' => 'multiline',
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                }
            }
        }
    }

    /**
     * @param eZContentObjectTreeNode $node
     * @param $item
     * @param array $options
     * @return ocm_interface
     * @throws Exception
     */
    protected function createFromNode(
        eZContentObjectTreeNode $node,
        ocm_interface $item,
        array $options = []
    ): ocm_interface {
        return $item->fromOpencityNode($node, $options);
    }

    public static function getMapperHelper(string $field): callable
    {
        switch ($field) {

            case 'image/name':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['image']['content'];
                    return $contentValue ? $contentValue['filename'] : '';
                };

            case 'image/url':
                return function (Content $content, $firstLocalizedContentData) {
                    $contentValue = $firstLocalizedContentData['image']['content'];
                    $url = $contentValue ? $contentValue['url'] : '';
                    eZURI::transformURI($url, false, 'full');
                    return $contentValue ? $url : '';
                };

            default:

                return function (
                    Content $content,
                    $firstLocalizedContentData,
                    $firstLocalizedContentLocale
                ) use ($field) {

                    $field = $firstLocalizedContentData[$field];
                    $contentValue = $field['content'];
                    $dataType = $field['datatype'];
                    $converter = AttributeConverterLoader::load(
                        $content->metadata->classIdentifier,
                        $field,
                        $dataType
                    );

                    switch ($dataType){

                        case eZDateType::DATA_TYPE_STRING:
                            return $contentValue && intval($contentValue) > 0 ? date(
                                    'j/n/Y',
                                    strtotime($contentValue)
                                ) : '';

                        case eZDateTimeType::DATA_TYPE_STRING;
                            return $contentValue && intval($contentValue) > 0 ? date(
                                'j/n/Y H:i',
                                strtotime($contentValue)
                            ) : '';

                        case eZBinaryFileType::DATA_TYPE_STRING:
                            $contentValue = $converter->toCSVString($contentValue, $firstLocalizedContentLocale);
                            if (!empty($contentValue)) {
                                $parts = explode('/', $contentValue);
                                $name = array_pop($parts);
                                $parts[] = urlencode($name);
                                return implode('/', $parts);
                            }
                            return '';

                        case OCMultiBinaryType::DATA_TYPE_STRING:
                            if (!empty($contentValue)) {
                                $files = [];
                                foreach ($contentValue as $file) {
                                    $fileParts = explode('/', $file['url']);
                                    $fileName = array_pop($fileParts);
                                    $fileParts[] = urlencode($fileName);
                                    $files[] = implode('/', $fileParts);
                                }
                                return implode(PHP_EOL, $files);
                            }
                            return '';

                        case eZXMLTextType::DATA_TYPE_STRING:
                            return $contentValue;

                        case eZGmapLocationType::DATA_TYPE_STRING:
                            return json_encode($contentValue);
                            
                        default:{
                            return $converter->toCSVString($contentValue, $firstLocalizedContentLocale);
                        }
                    }
                };
        }
    }
}