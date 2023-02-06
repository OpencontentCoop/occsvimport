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
                        if ($identifier == 'phone' || $identifier == 'mobile_phone'){
                            $type = 'Telefono';
                        }elseif ($identifier == 'fax'){
                            $type = 'Fax';
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

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $map = [
            'employee' => [
                'given_name' => ['nome'],
                'family_name' => ['cognome'],
                'curriculum' => ['curriculum_vitae'],
                'altre_cariche' => ['assunzione_cariche'],
                '_constraint' => [
                ],
            ],
            'politico' => [
                'bio' => ['abstract'],
                '_constraint' => [
                ],
            ],
        ];

        $object = $node->object();
        $content = \Opencontent\Opendata\Api\Values\Content::createFromEzContentObject($object);
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        $this->setAttribute('_id', $object->attribute('remote_id'));

        $nodeUrl = $node->attribute('url_alias');
        eZURI::transformURI($nodeUrl, false, 'full');
        $this->setAttribute('_original_url', $nodeUrl);
        $this->setAttribute('_parent_name', $node->attribute('parent')->attribute('name'));

        $alreadyDone = [];
        foreach (static::$fields as $identifier) {
            [$id] = explode('___', $identifier);
            if (isset($alreadyDone[$id])) {
                continue;
            }

            if (isset($map[$node->classIdentifier()][$id])) {
                foreach ($map[$node->classIdentifier()][$id] as $mapToId) {
                    $data = static::getAttributeString($mapToId, $dataMap, $content, $options);
                    foreach ($data as $name => $value) {
                        $this->appendAttribute($id, $value);
                    }
                }
                $alreadyDone[$id] = true;
            }

            if (!isset($alreadyDone[$id])) {
                $data = static::getAttributeString($id, $dataMap, $content, $options);
                foreach ($data as $name => $value) {
                    $this->setAttribute($name, $value);
                }
                $alreadyDone[$id] = true;
            }

            if (isset($map[$node->classIdentifier()]['_defaults'][$id]) && empty($this->attribute($id))) {
                $this->setAttribute($id, $map[$node->classIdentifier()]['_defaults'][$id]);
            }
            if (isset($map[$node->classIdentifier()]['_constraint'][$id])) {
                $this->setAttribute($id, $map[$node->classIdentifier()]['_constraint'][$id]);
            }
        }

        $contactPoint = $this->appendContactPointFromOpencityNode($node, $options);
        if ($contactPoint instanceof ocm_online_contact_point) {
            $this->setAttribute('has_contact_point', $contactPoint->attribute('name'));
        }

        return $this;
    }

    protected function appendContactPointFromOpencityNode(eZContentObjectTreeNode $node, array $options = [])
    {
        $dataMap = $node->dataMap();
        $object = $node->object();
        $content = \Opencontent\Opendata\Api\Values\Content::createFromEzContentObject($object);
        $contact = [];
        if (self::hasAttributeStringContent($dataMap, 'phone')){
            $contact[] = [
                'type' => 'Telefono',
                'value' => $dataMap['phone']->toString(),
                'contact' => '',
            ];
        }
        if (self::hasAttributeStringContent($dataMap, 'mobile_phone')){
            $contact[] = [
                'type' => 'Telefono',
                'value' => $dataMap['mobile_phone']->toString(),
                'contact' => '',
            ];
        }
        if (self::hasAttributeStringContent($dataMap, 'fax')){
            $contact[] = [
                'type' => 'Fax',
                'value' => $dataMap['fax']->toString(),
                'contact' => '',
            ];
        }
        if (self::hasAttributeStringContent($dataMap, 'email')){
            $contact[] = [
                'type' => 'Email',
                'value' => $dataMap['email']->toString(),
                'contact' => '',
            ];
        }

        if (!empty($contact)){
            $contactPoint = new ocm_online_contact_point();
            $contactPoint->setAttribute('name', 'Contatti di ' . $node->attribute('name'));
            $contactPoint->setAttribute('contact', json_encode($contact));
            $contactPoint->setAttribute('_id', 'contact_' . $this->attribute('_id'));

            if (self::hasAttributeStringContent($dataMap, 'ricevimento')){
                $openingHour = new ocm_opening_hours_specification();
                $openingHour->setAttribute('_id', 'hours_' . $this->attribute('_id'));
                $openingHour->setAttribute('name', 'Ricevimento di ' . $node->attribute('name'));
                $data = static::getAttributeString('ricevimento', $dataMap, $content, $options);
                foreach ($data as $name => $value) {
                    $openingHour->appendAttribute('note', $value);
                }
                $openingHour->storeThis($options['is_update']);
                $contactPoint->setAttribute('phone_availability_time', $openingHour->attribute('name'));
            }

            $contactPoint->storeThis($options['is_update']);
            return $contactPoint;
        }

        return false;
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