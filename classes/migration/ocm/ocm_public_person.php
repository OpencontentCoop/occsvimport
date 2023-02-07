<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_public_person extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'given_name',
        'family_name',
        'abstract',
        'image',
        'bio',
        'has_contact_point',
        'curriculum',
        'situazione_patrimoniale',
        'dichiarazioni_patrimoniali_soggetto',
        'dichiarazioni_patrimoniali_parenti',
        'dichiarazione_redditi',
        'spese_elettorali',
        'spese_elettorali_files',
        'variazioni_situazione_patrimoniale',
        'altre_cariche',
        'eventuali_incarichi',
        'dichiarazione_incompatibilita',
        'notes',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Persone pubbliche';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificatore persona pubblica*";
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        if ($node->classIdentifier() === 'employee'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromEmployee(), $options);
        }
        if ($node->classIdentifier() === 'politico'){
            return $this->fromNode($node, $this->getOpencityFieldMapperFromPolitico(), $options);
        }

        return null;
    }

    protected function getOpencityFieldMapperFromEmployee(): array
    {
        return [
            'given_name' => OCMigrationOpencity::getMapperHelper('nome'),
            'family_name' => OCMigrationOpencity::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => $this->getOpencityFieldMapperFromPolitico()['has_online_contact_point'],
            'curriculum' => OCMigrationOpencity::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale',
            'altre_cariche' => OCMigrationOpencity::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => false,
            'dichiarazione_incompatibilita' => false,
            'notes' => false,
        ];
    }

    protected function getOpencityFieldMapperFromPolitico(): array
    {
        return [
            'given_name' => OCMigrationOpencity::getMapperHelper('nome'),
            'family_name' => OCMigrationOpencity::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_online_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $hoursName = false;
                $ricevimento = OCMigrationOpencity::getMapperHelper('ricevimento')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
                if (!empty($ricevimento)) {
                    $hoursId = $id . ':hours';
                    $hoursName = "Ricevimento di $name";
                    $hours = new ocm_opening_hours_specification();
                    $hours->setAttribute('_id', $hoursId);
                    $hours->setAttribute('name', $hoursName);
                    $hours->setAttribute('stagionalita', "Unico");
                    $hours->setAttribute('note', $ricevimento);
                    $hours->store();
                }

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
                $data = [];
                foreach (['phone', 'mobile_phone', 'email', 'fax', ] as $identifier){
                    if (isset($dataMap[$identifier])){
                        $type = $identifier;
                        if ($identifier == 'phone'){
                            $type = 'Telefono';
                        }elseif ($identifier == 'mobile_phone'){
                            $type = 'Cellulare';
                        }elseif ($identifier == 'fax'){
                            $type = 'Fax';
                        }elseif (stripos($identifier, 'email') !== false){
                            $type = 'Email';
                        }
                        $value = $dataMap[$identifier]->toString();
                        if (!empty($value)) {
                            $data[] = [
                                'type' => $type,
                                'value' => $value,
                                'contact' => '',
                            ];
                        }
                    }
                }
                $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
                if ($hoursName) {
                    $contacts->setAttribute('phone_availability_time', $hoursName);
                }
                $contacts->store();

                return $contactsName;
            },
            'curriculum' => OCMigrationOpencity::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale',
            'altre_cariche' => OCMigrationOpencity::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => false,
            'dichiarazione_incompatibilita' => false,
            'notes' => false,
        ];
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        if ($node->classIdentifier() === 'dipendente'){
            return $this->fromNode($node, $this->getComunwebFieldMapperFromDipendente(), $options);
        }
        if ($node->classIdentifier() === 'politico'){
            return $this->fromNode($node, $this->getComunwebFieldMapperFromPolitico(), $options);
        }

        return null;
    }

    protected function getComunwebFieldMapperFromDipendente(): array
    {
        return [
            'given_name' => OCMigrationOpencity::getMapperHelper('nome'),
            'family_name' => OCMigrationOpencity::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
                $data = [];
                foreach (['telefono', 'cellulare', 'email', 'fax', ] as $identifier){
                    if (isset($dataMap[$identifier])){
                        $type = $identifier;
                        if ($identifier == 'telefono'){
                            $type = 'Telefono';
                        }elseif ($identifier == 'cellulare'){
                            $type = 'Cellulare';
                        }elseif ($identifier == 'fax'){
                            $type = 'Fax';
                        }elseif (stripos($identifier, 'email') !== false){
                            $type = 'Email';
                        }
                        $value = $dataMap[$identifier]->toString();
                        if (!empty($value)) {
                            $data[] = [
                                'type' => $type,
                                'value' => $value,
                                'contact' => '',
                            ];
                        }
                    }
                }
                $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'curriculum' => OCMigrationOpencity::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale',
            'altre_cariche' => OCMigrationOpencity::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
//                $object = eZContentObject::fetch($content->metadata['id']);
//                if ($object instanceof eZContentObject){
//                    $incarichi = $object->reverseRelatedObjectList(false, eZContentObjectTreeNode::classAttributeIDByIdentifier('conferimento_incarico/dipendente'));
//                    print_r($incarichi);
//                }
                return '';
            },
            'dichiarazione_incompatibilita' => false,
            'notes' => false,
        ];
    }

    protected function getComunwebFieldMapperFromPolitico(): array
    {
        return [
            'given_name' => OCMigrationOpencity::getMapperHelper('nome'),
            'family_name' => OCMigrationOpencity::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $hoursName = false;
                $ricevimento = OCMigrationOpencity::getMapperHelper('ricevimento')($content, $firstLocalizedContentData, $firstLocalizedContentLocale);
                if (!empty($ricevimento)) {
                    $hoursId = $id . ':hours';
                    $hoursName = "Ricevimento di $name";
                    $hours = new ocm_opening_hours_specification();
                    $hours->setAttribute('_id', $hoursId);
                    $hours->setAttribute('name', $hoursName);
                    $hours->setAttribute('stagionalita', "Unico");
                    $hours->setAttribute('note', $ricevimento);
                    $hours->storeThis($options['is_update']);
                }

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = new ocm_online_contact_point();
                $contacts->setAttribute('_id', $contactsId);
                $contacts->setAttribute('name', $contactsName);
                $data = [];
                foreach (['phone', 'mobile_phone', 'email', 'fax', ] as $identifier){
                    if (isset($dataMap[$identifier])){
                        $type = $identifier;
                        if ($identifier == 'phone'){
                            $type = 'Telefono';
                        }elseif ($identifier == 'mobile_phone'){
                            $type = 'Cellulare';
                        }elseif ($identifier == 'fax'){
                            $type = 'Fax';
                        }elseif (stripos($identifier, 'email') !== false){
                            $type = 'Email';
                        }
                        $value = $dataMap[$identifier]->toString();
                        if (!empty($value)) {
                            $data[] = [
                                'type' => $type,
                                'value' => $value,
                                'contact' => '',
                            ];
                        }
                    }
                }
                $contacts->setAttribute('contact', json_encode(['ita-IT' => $data]));
                if ($hoursName) {
                    $contacts->setAttribute('phone_availability_time', $hoursName);
                }
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'curriculum' => OCMigrationOpencity::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale',
            'altre_cariche' => OCMigrationOpencity::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
//                $object = eZContentObject::fetch($content->metadata['id']);
//                if ($object instanceof eZContentObject){
//                    $incarichi = $object->reverseRelatedObjectList(false, eZContentObjectTreeNode::classAttributeIDByIdentifier('conferimento_incarico/dipendente'));
//                    print_r($incarichi);
//                }
                return '';
            },
            'dichiarazione_incompatibilita' => false,
            'notes' => false,
        ];
    }

    public function toSpreadsheet(): array
    {
        return [
            "Identificatore persona pubblica*" => $this->attribute('_id'),
            "Nome*" => $this->attribute('given_name'),
            "Cognome*" => $this->attribute('family_name'),
            "Nome completo" => $this->attribute('family_name') . ' ' . $this->attribute('given_name'),
            "Descrizione breve" => $this->attribute('abstract'),
            "Immagini" => $this->attribute('image'),
            "Biografia" => $this->attribute('bio'),
            "Punti di contatto*" => $this->attribute('has_contact_point'),
            "Curriculum vitae" => $this->attribute('curriculum'),
            "Situazione patrimoniale" => $this->attribute('situazione_patrimoniale'),
            "Dichiarazioni patrimoniali del soggetto" => $this->attribute('dichiarazioni_patrimoniali_soggetto'),
            "Dichiarazioni patrimoniali del coniuge non separato e parenti entro il secondo grado" =>
                $this->attribute('dichiarazioni_patrimoniali_parenti'),
            "Dichiarazione dei redditi*" => $this->attribute('dichiarazione_redditi'),
            "Spese elettorali*" => $this->attribute('spese_elettorali'),
            "Spese elettorali (allegati)" => $this->attribute('spese_elettorali_files'),
            "Variazioni situazione patrimoniale*" => $this->attribute('variazioni_situazione_patrimoniale'),
            "Altre cariche*" => $this->attribute('altre_cariche'),
            "Altri eventuali incarichi con oneri a carico della finanza pubblica e l'indicazione dei compensi spettanti" =>
                $this->attribute('eventuali_incarichi'),
            "Dichiarazione incompatibilità e inconferibilità" => $this->attribute('dichiarazione_incompatibilita'),
            "Ulteriori informazioni" => $this->attribute('notes'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        return new static();
    }

    public static function getImportPriority(): int
    {
        return 40;
    }

    public function generatePayload(): array
    {
        return [];
    }
}