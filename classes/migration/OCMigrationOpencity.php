<?php

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
    protected function createFromNode(eZContentObjectTreeNode $node, ocm_interface $item, array $options = []): ocm_interface
    {
        return $item->fromOpencityNode($node, $options);
    }

}