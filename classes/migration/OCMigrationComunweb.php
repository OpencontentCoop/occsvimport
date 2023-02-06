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
        if (empty($namesFilter) || in_array('ocm_image', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['image'], ['classificazioni']);
            $rows = 0;
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_image(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus(['ocm_image' => [
                'status' => 'success',
                'update' => $rows,
            ]]);
        }

        if (empty($namesFilter) || in_array('ocm_pagina_sito', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['pagina_sito'], ['classificazioni', 'amministrazione_trasparente']);
            $rows = 0;
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_pagina_sito(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus(['ocm_pagina_sito' => [
                'status' => 'success',
                'update' => $rows,
            ]]);
        }

        if (empty($namesFilter) || in_array('ocm_folder', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['folder'], ['classificazioni', 'amministrazione_trasparente']);
            $rows = 0;
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_folder(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus(['ocm_folder' => [
                'status' => 'success',
                'update' => $rows,
            ]]);
        }

        if (empty($namesFilter) || in_array('ocm_place', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['luogo'], ['classificazioni', 'amministrazione_trasparente']);
            $rows = 0;
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_place(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus(['ocm_place' => [
                'status' => 'success',
                'update' => $rows,
            ]]);
        }

        if (empty($namesFilter) || in_array('ocm_organization', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['area', 'servizio', 'ufficio'], ['classificazioni', 'amministrazione_trasparente']);
            $rows = 0;
            foreach ($nodes as $node){
                if ($this->createFromNode($node, new ocm_organization(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }

            $nodes = $this->getNodesByClassIdentifierList(['organo_politico'], ['amministrazione_trasparente']);
            foreach ($nodes as $node){
                if ($this->createFromNode($node, new ocm_organization(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $rows++;
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
            OCMigrationSpreadsheet::appendMessageToCurrentStatus(['ocm_organization' => [
                'status' => 'success',
                'update' => $rows,
            ]]);
        }
    }

    private function hasAttachments(eZContentObjectTreeNode $node)
    {
        $count = $node->subTreeCount([
            'Depth' => 1,
            'DepthOperator' => 'eq',
            'ClassFilterType' => 'include',
            'ClassFilterArray' => ['file_pdf'],
        ]);
        if ($count > 0) {
            $this->info('  - ' . $count . ' attachments');
        }

        return $count > 0;
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
        return $item->fromComunwebNode($node, $options);
    }
}