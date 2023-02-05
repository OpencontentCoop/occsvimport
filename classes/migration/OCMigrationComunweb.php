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
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_image(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_pagina_sito', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['pagina_sito'], ['classificazioni', 'amministrazione_trasparente']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_pagina_sito(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
        }

        if (empty($namesFilter) || in_array('ocm_folder', $namesFilter)) {
            $nodes = $this->getNodesByClassIdentifierList(['folder'], ['classificazioni', 'amministrazione_trasparente']);
            foreach ($nodes as $node) {
                if ($this->createFromNode($node, new ocm_folder(), [
                    'is_update' => $isUpdate,
                ])->storeThis($isUpdate)) {
                    $this->info(' - ' . $node->attribute('name'));
                    $this->hasAttachments($node);
                }
            }
        }

        $this->fillOrganigrammaData();
    }

    protected function fillOrganigrammaData()
    {
        $nodes = $this->getNodesByClassIdentifierList(['area', 'servizio', 'ufficio'], ['classificazioni', 'amministrazione_trasparente']);
        foreach ($nodes as $node){
            $this->info(' - ' . $node->attribute('name'));
            $this->hasAttachments($node);
            $this->fillFromAreaServizioUfficio($node);
        }

        $nodes = $this->getNodesByClassIdentifierList(['organo_politico'], ['amministrazione_trasparente']);
        foreach ($nodes as $node){
            $this->info(' - ' . $node->attribute('name'));
            $this->hasAttachments($node);
            $this->fillFromOrganoPolitico($node);
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

    protected function fillFromOrganoPolitico(eZContentObjectTreeNode $node)
    {
        $id = $node->attribute('class_identifier') . ':' . $node->attribute('contentobject_id');
        $name = $node->attribute('name');

        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');

        $hoursId = $id . ':hours';
        $hoursName = "Orari $name";
        $hours = new ocm_opening_hours_specification();
        $hours->setAttribute('_id', $hoursId);
        $hours->setAttribute('name', $hoursName);
        $hours->setAttribute('stagionalita', "Unico");
        $hours->setAttribute('note', $this->getAttributeContent('contatti', $dataMap));
        $hours->store();

        $contactsId = $id . ':contacts';
        $contactsName = "Contatti $name";
        $contacts = new ocm_online_contact_point();
        $contacts->setAttribute('_id', $contactsId);
        $contacts->setAttribute('name', $contactsName);
        $contacts->setAttribute('phone_availability_time', $hoursName);
        $contacts->store();

        $placeId = $id . ':place';
        $placeName = "Sede $name";
        $place = new ocm_place();
        $place->setAttribute('_id', $placeId);
        $place->setAttribute('name', $placeName);
        $place->setAttribute('type', 'Palazzo');
        $place->setAttribute('opening_hours_specification', $hoursName);
        $place->setAttribute('help', $contactsName);
        $place->store();
    }

    protected function fillFromAreaServizioUfficio(eZContentObjectTreeNode $node)
    {
        $id = $node->attribute('class_identifier') . ':' . $node->attribute('contentobject_id');
        $name = $node->attribute('name');

        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');

        $hoursId = $id . ':hours';
        $hoursName = "Orari $name";
        $hours = new ocm_opening_hours_specification();
        $hours->setAttribute('_id', $hoursId);
        $hours->setAttribute('name', $hoursName);
        $hours->setAttribute('stagionalita', "Unico");
        $hours->setAttribute('note', $this->getAttributeContent('orario', $dataMap));
        $hours->store();

        $contactsId = $id . ':contacts';
        $contactsName = "Contatti $name";
        $contacts = new ocm_online_contact_point();
        $contacts->setAttribute('_id', $contactsId);
        $contacts->setAttribute('name', $contactsName);
        $data = [];
        foreach (['telefoni', 'fax', 'email', 'email2', 'email_certificata', ] as $identifier){
            if (isset($dataMap[$identifier])){
                $type = $identifier;
                if ($identifier == 'telefoni'){
                    $type = 'Telefono';
                }elseif ($identifier == 'fax'){
                    $type = 'Fax';
                }elseif ($identifier == 'email_certificata'){
                    $type = 'PEC';
                }elseif (stripos($identifier, 'email') !== false){
                    $type = 'Email';
                }
                $data[] = [
                    'type' => $type,
                    'value' => $dataMap[$identifier]->toString(),
                    'contact' => '',
                ];
            }
        }
        $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
        $contacts->setAttribute('phone_availability_time', $hoursName);
        $contacts->store();

        $placeId = $id . ':place';
        $placeName = "Sede $name";
        $place = new ocm_place();
        $place->setAttribute('_id', $placeId);
        $place->setAttribute('name', $placeName);
        $place->setAttribute('type', 'Palazzo');
        $place->setAttribute('opening_hours_specification', $hoursName);
        $place->setAttribute('help', $contactsName);
        $place->store();

        $organization = new ocm_organization();
        $organization->setAttribute('_id', $id);
        $nodeUrl = $node->attribute('url_alias');
        eZURI::transformURI($nodeUrl, false, 'full');
        $organization->setAttribute('_original_url', $nodeUrl);
        $organization->setAttribute('_parent_name', $node->attribute('parent')->attribute('name'));
        $organization->setAttribute('legal_name', $this->getAttributeContent('titolo', $dataMap));
        $organization->setAttribute('abstract', $this->getAttributeContent('abstract', $dataMap));
        $organization->setAttribute('description', $this->getAttributeContent('description', $dataMap));
        $organization->setAttribute('image', $this->getAttributeContent('image', $dataMap));
        $organization->setAttribute('main_function', $this->getAttributeContent('competenze', $dataMap));
// @todo
//        $area = $this->getAttributeContent('area', $dataMap);
//        $servizio = $this->getAttributeContent('servizio', $dataMap);
//        $organization->setAttribute('hold_employment', $this->getAttributeContent('orario', $dataMap));

        $type = $node->attribute('class_identifier') === 'area' ? 'Area' : 'Ufficio';
        $organization->setAttribute('type', $type);
        $organization->setAttribute('has_spatial_coverage', $placeName);
        $organization->setAttribute('has_online_contact_point', $contactsId);
        $organization->setAttribute('attachments', $this->getAttributeContent(['file', 'ubicazione'], $dataMap, '|'));
        $organization->setAttribute('more_information', $this->getAttributeContent(['riferimenti_utili'], $dataMap));
        $organization->store();

    }

    protected function getAttributeContent($attributeIdentifier, $dataMap, $separator = ' ')
    {
        if (!is_array($attributeIdentifier)) {
            $attributeIdentifier = [$attributeIdentifier];
        }
        $data = [];
        foreach ($attributeIdentifier as $aid) {
            if (isset($dataMap[$aid]) && $dataMap[$aid]->hasContent()) {
                $attribute = $dataMap[$aid];
                $converter = AttributeConverterLoader::load(
                    $attribute->attribute('object')->attribute('class_identifier'),
                    $attribute->attribute('contentclass_attribute_identifier'),
                    $attribute->attribute('data_type_string')
                );
                $content = $converter->get($attribute);
                $data[] = $content['content'];
            }
        }

        return implode($separator, $data);
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