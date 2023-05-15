<?php

use Opencontent\Opendata\Api\AttributeConverterLoader;

class OCMigrationComunweb extends OCMigration implements OCMigrationInterface
{
    public function __construct()
    {
        /** @var eZUser $user */
        $user = eZUser::fetchByName('admin');
        eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'), eZUser::NO_SESSION_REGENERATE);
        parent::__construct();
    }

    public function fillData(array $namesFilter = [], $isUpdate = false)
    {
        $escludePathList = [
            'classificazioni',
            'amministrazione_trasparente'
        ];
        $escludeRemoteIdPrefix = [
            'at_',
            'riva_',
            'ledro_',
            'levico_',
            'mori_',
        ];
        $this->fillByType($namesFilter, $isUpdate, 'ocm_image', ['image'], $escludePathList);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_file', ['file', 'file_pdf'], $escludePathList);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_pagina_sito', ['pagina_sito'], $escludePathList);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_folder', ['folder'], $escludePathList);
        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_place',
            ['luogo', 'servizio_sul_territorio'],
            $escludePathList
        );
        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_organization',
            ['area', 'servizio', 'ufficio', 'organo_politico', 'sindaco'],
            $escludePathList
        );
        $this->fillByType($namesFilter, $isUpdate, 'ocm_public_person', ['dipendente', 'politico',], $escludePathList);
        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_time_indexed_role',
            ['dipendente', 'politico', 'ruolo'],
            $escludePathList
        );
        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_document',
            [
                'accordo',
                'bilancio_di_settore',
                'bando',
                'circolare',
                'concorso',
                'concessioni',
                'convenzione',
                'decreto_sindacale',
                'deliberazione',
                'determinazione',
                'documento',
                'graduatoria',
                'interpellanza',
                'interrogazione',
                'modello',
                'modulo',
                'modulistica',
                'mozione',
                'normativa',
                'ordinanza',
                'ordine_del_giorno',
                'parere',
                'piano_progetto',
                'pubblicazione',
                'procedura',
                'protocollo',
                'rapporto',
                'regolamento',
                'statuto',
                'trattamento',
            ],
            $escludePathList,
            $escludeRemoteIdPrefix
        );

        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_article',
            ['avviso',],
            $escludePathList,
            $escludeRemoteIdPrefix,
            ['standard']
        );

        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_event',
            ['event',],
            $escludePathList,
            $escludeRemoteIdPrefix,
            ['standard'],
            true,
            2
        );

        $this->fillByType(
            $namesFilter,
            $isUpdate,
            'ocm_private_organization',
            ['associazione',],
            $escludePathList,
            [],
            ['standard']
        );

        //servizio_sul_territorio
        //procedimento
    }

    /**
     * @param eZContentObject|eZContentObjectTreeNode $nodeOrObject
     * @return ?string
     */
    public static function getFileAttributeUrl($nodeOrObject, $attributeIdentifier = 'file'): ?string
    {
        $dataMap = $nodeOrObject->dataMap();
        if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()){
            $attribute = $dataMap[$attributeIdentifier];
            /** @var \eZBinaryFile $file */
            $file = $attribute->content();
            $url = 'content/download/' . $attribute->attribute('contentobject_id')
                . '/' . $attribute->attribute('id')
                . '/' . $attribute->attribute('version')
                . '/' . urlencode($file->attribute('original_filename'));
            eZURI::transformURI($url, true, 'full');
            $url = str_replace('http://', 'https://', $url);
            return $url;
        }

        return null;
    }

    /**
     * @param eZContentObjectTreeNode $node
     * @params bool $onlyChild
     * @return eZContentObjectTreeNode[]
     */
    public static function getAttachmentsByNode($node, $onlyChild = true): array
    {
        if (!$node instanceof eZContentObjectTreeNode){
            return [];
        }
        $params = [
            'Depth' => 1,
            'DepthOperator' => 'eq',
            'ClassFilterType' => 'include',
            'ClassFilterArray' => ['file', 'file_pdf'],
        ];
        if (!$onlyChild){
            unset($params['Depth']);
            unset($params['DepthOperator']);
        }
        return $node->subTree($params);
    }

    /**
     * @param eZContentObjectTreeNode $node
     * @param $item
     * @param array $options
     * @return ocm_interface
     * @throws Exception
     */
    public function createFromNode(
        eZContentObjectTreeNode $node,
        ocm_interface $item,
        array $options = []
    ): ocm_interface {
        return $item->fromComunwebNode($node, $options);
    }
}