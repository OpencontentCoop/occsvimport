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
        $this->fillByType($namesFilter, $isUpdate, 'ocm_image', ['image']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_opening_hours_specification', ['opening_hours_specification']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_online_contact_point', ['online_contact_point']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_document', ['document'], [], ['at_'], ['standard']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_place', ['place']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_organization', [
            'administrative_area',
            'homogeneous_organizational_area',
            'office',
            'political_body',
        ]);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_public_person', [
            'employee',
            'politico',
        ]);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_time_indexed_role', ['time_indexed_role']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_article', ['article']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_banner', ['banner']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_event', ['event']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_link', ['link']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_private_organization', ['private_organization']);
        $this->fillByType($namesFilter, $isUpdate, 'ocm_public_organization', ['public_organization']);
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
        return $item->fromOpencityNode($node, $options);
    }

}