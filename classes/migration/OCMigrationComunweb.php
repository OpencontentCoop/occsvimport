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
        $this->fillOrganigrammaData();
    }

    protected function fillOrganigrammaData()
    {
        return; //@todo
        $nodes = $this->getNodesByClassIdentifierList(['area', 'servizio', 'ufficio']);
        foreach ($nodes as $node){
            $this->info(' - ' . $node->attribute('name'));
            $this->fillOrganigrammaItem($node);
        }
    }

    protected function fillOrganigrammaItem(eZContentObjectTreeNode $node)
    {
        $id = $node->attribute('class_identifier') . ':' . $node->attribute('contentobject_id');
        $name = $node->attribute('name');

        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');

        $hoursId = $id . ':hours';
        $hours = new ocm_opening_hours_specification();
        $hours->setAttribute('_id', $hoursId);
        $hours->setAttribute('name', "Orari $name");
        $hours->setAttribute('stagionalita', "Unico");
        $hours->setAttribute('note', $this->getAttributeContent('orario', $dataMap));
        $hours->store();

        $contactsId = $id . ':contacts';
        $contacts = new ocm_online_contact_point();
        $contacts->setAttribute('_id', $contactsId);
        $contacts->setAttribute('name', "Contatti $name");
        $data = [];
        foreach (['telefoni', 'fax', 'email', 'email2', 'email_certificata', ] as $identifier){
            if (isset($dataMap[$identifier])){
                $data[] = eZStringUtils::implodeStr([
                    $dataMap[$identifier]->attribute('contentclass_attribute_name'),
                    str_replace(['&', '|'], '', $dataMap[$identifier]->toString()),
                    ''
                ], '|' );
            }
        }
        $contacts->setAttribute('contact', eZStringUtils::implodeStr( $data, '&' ));
        $contacts->setAttribute('phone_availability_time', $hoursId);
        $contacts->store();

        $placeId = $id . ':place';
        $place = new ocm_place();
        $place->setAttribute('_id', $placeId);
        $place->setAttribute('name', "Sede $name");
        $place->setAttribute('type', 'Palazzo');
        $place->setAttribute('opening_hours_specification', $hoursId);
        $place->setAttribute('help', $contactsId);
        $place->store();

        $organization = new ocm_organization();
        $organization->setAttribute('_id', $id);
        $organization->setAttribute('legal_name', $this->getAttributeContent('titolo', $dataMap));
        $organization->setAttribute('abstract', $this->getAttributeContent('abstract', $dataMap));
        $organization->setAttribute('image', $this->getAttributeContent('image', $dataMap));
        $organization->setAttribute('main_function', $this->getAttributeContent('competenze', $dataMap));
// @todo
//        $area = $this->getAttributeContent('area', $dataMap);
//        $servizio = $this->getAttributeContent('servizio', $dataMap);
//        $organization->setAttribute('hold_employment', $this->getAttributeContent('orario', $dataMap));

        $type = $node->attribute('class_identifier') === 'area' ? 'Area' : 'Ufficio';
        $organization->setAttribute('type', $type);
        $organization->setAttribute('has_spatial_coverage', $placeId);
        $organization->setAttribute('has_online_contact_point', $contactsId);
        $organization->setAttribute('attachments', $this->getAttributeContent(['file', 'ubicazione'], $dataMap, '|'));
        $organization->setAttribute('more_information', $this->getAttributeContent(['descrizione', 'riferimenti_utili'], $dataMap));
        $organization->store();

    }

    protected function getAttributeContent($attributeIdentifier, $dataMap, $separator = ' ')
    {
        if (!is_array($attributeIdentifier)) {
            $attributeIdentifier = [$attributeIdentifier];
        }
        $data = [];
        foreach ($attributeIdentifier as $aid) {
            if (isset($dataMap[$aid])) {
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
}