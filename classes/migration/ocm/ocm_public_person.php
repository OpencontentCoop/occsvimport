<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_public_person extends OCMPersistentObject implements ocm_interface
{
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
        'de_abstract',
        'de_bio',
        'de_situazione_patrimoniale',
        'de_spese_elettorali',
        'de_notes',
    ];

    public function avoidNameDuplication()
    {
        return false;
    }

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
            'given_name' => OCMigration::getMapperHelper('nome'),
            'family_name' => OCMigration::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => $this->getOpencityFieldMapperFromPolitico()['has_contact_point'],
            'curriculum' => OCMigration::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale' => false,
            'altre_cariche' => OCMigration::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => false,
            'dichiarazione_incompatibilita' => false,
            'notes' => false,
            'de_abstract' => false,
            'de_bio' => false,
            'de_situazione_patrimoniale' => false,
            'de_spese_elettorali' => false,
            'de_notes' => false,
        ];
    }

    protected function getOpencityFieldMapperFromPolitico(): array
    {
        return [
            'given_name' => OCMigration::getMapperHelper('given_name'),
            'family_name' => OCMigration::getMapperHelper('family_name'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){

                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $dataMap = $object->dataMap();

                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];

                $hoursName = false;
                $ricevimento = OCMigration::getMapperHelper('ricevimento')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                if (!empty($ricevimento)) {
                    $hoursId = $id . ':hours';
                    $hoursName = "Ricevimento di $name";
                    $hours = ocm_opening_hours_specification::instanceBy('name', $hoursName, $hoursId);
                    $hours->setAttribute('stagionalita', "Unico");
                    $hours->setAttribute('note', $ricevimento);
                    $hours->setNodeReference($node);
                    $hours->storeThis($options['is_update']);
                }

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
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
                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'curriculum' => OCMigration::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale' => false,
            'altre_cariche' => OCMigration::getMapperHelper('assunzione_cariche'),
            'eventuali_incarichi' => false,
            'dichiarazione_incompatibilita' => false,
            'notes' => OCMigration::getMapperHelper('nota'),
            'de_abstract' => false,
            'de_bio' => false,
            'de_situazione_patrimoniale' => false,
            'de_spese_elettorali' => false,
            'de_notes' => false,
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
            'given_name' => OCMigration::getMapperHelper('nome'),
            'family_name' => OCMigration::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $dataMap = $object->dataMap();
                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
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
                $node = $object->mainNode();
                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'curriculum' => OCMigration::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale' => false,
            'altre_cariche' => OCMigration::getMapperHelper('assunzione_cariche'),
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
            'given_name' => OCMigration::getMapperHelper('nome'),
            'family_name' => OCMigration::getMapperHelper('cognome'),
            'abstract' => false,
            'image' => false,
            'bio' => false,
            'has_contact_point' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $id = $content->metadata['classIdentifier'] . ':' . $content->metadata['id'];
                $name = $content->metadata['name']['ita-IT'];
                $object = eZContentObject::fetch((int)$content->metadata['id']);
                $node = $object->mainNode();
                $hoursName = false;
                $ricevimento = OCMigration::getMapperHelper('ricevimento')($content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options);
                if (!empty($ricevimento)) {
                    $hoursId = $id . ':hours';
                    $hoursName = "Ricevimento di $name";
                    $hours = ocm_opening_hours_specification::instanceBy('name', $hoursName, $hoursId);
                    $hours->setAttribute('stagionalita', "Unico");
                    $hours->setAttribute('note', $ricevimento);
                    $hours->setNodeReference($node);
                    $hours->storeThis($options['is_update']);
                }

                $contactsId = $id . ':contacts';
                $contactsName = "Contatti $name";
                $contacts = ocm_online_contact_point::instanceBy('name', $contactsName, $contactsId);
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
                $contacts->setNodeReference($node);
                $contacts->storeThis($options['is_update']);

                return $contactsName;
            },
            'curriculum' => OCMigration::getMapperHelper('curriculum_vitae'),
            'situazione_patrimoniale' => false,
            'dichiarazioni_patrimoniali_soggetto' => false,
            'dichiarazioni_patrimoniali_parenti' => false,
            'dichiarazione_redditi' => false,
            'spese_elettorali' => false,
            'spese_elettorali_files' => false,
            'variazioni_situazione_patrimoniale' => false,
            'altre_cariche' => OCMigration::getMapperHelper('assunzione_cariche'),
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

    public static function getSortField(): string
    {
        return 'family_name';
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
            "Dichiarazione dei redditi" => $this->attribute('dichiarazione_redditi'),
            "Spese elettorali*" => $this->attribute('spese_elettorali'),
            "Spese elettorali" => $this->attribute('spese_elettorali'),
            "Spese elettorali (allegati)" => $this->attribute('spese_elettorali_files'),
            "Variazioni situazione patrimoniale*" => $this->attribute('variazioni_situazione_patrimoniale'),
            "Variazioni situazione patrimoniale" => $this->attribute('variazioni_situazione_patrimoniale'),
            "Altre cariche*" => $this->attribute('altre_cariche'),
            "Altre cariche" => $this->attribute('altre_cariche'),
            "Altri eventuali incarichi con oneri a carico della finanza pubblica e l'indicazione dei compensi spettanti" =>
                $this->attribute('eventuali_incarichi'),
            "Dichiarazione incompatibilità e inconferibilità" => $this->attribute('dichiarazione_incompatibilita'),
            "Ulteriori informazioni" => $this->attribute('notes'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
            'Incarichi*' => '=VLOOKUP("'.$this->attribute('family_name').' '.$this->attribute('given_name').'";PersoneIncarichi;1;FALSE)',
            'Kurze Beschreibung [de]' => $this->attribute('de_abstract'),
            'Biografie [de]' => $this->attribute('de_bio'),
            'Erbliche Situation [de]' => $this->attribute('de_situazione_patrimoniale'),
            'Wahlkosten* [de]' => $this->attribute('de_spese_elettorali'),
            'Weitere Informationen [de]' => $this->attribute('de_notes'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificatore persona pubblica*"]);
        $item->setAttribute('given_name', $row["Nome*"]);
        $item->setAttribute('family_name', $row["Cognome*"]);
        $item->setAttribute('abstract', $row["Descrizione breve"]);
        $item->setAttribute('image', $row["Immagini"]);
        $item->setAttribute('bio', $row["Biografia"]);
        $item->setAttribute('has_contact_point', $row["Punti di contatto*"]);
        $item->setAttribute('curriculum', $row["Curriculum vitae"]);
        $item->setAttribute('situazione_patrimoniale', $row["Situazione patrimoniale"]);
        $item->setAttribute('dichiarazioni_patrimoniali_soggetto', $row["Dichiarazioni patrimoniali del soggetto"]);
        $item->setAttribute('dichiarazioni_patrimoniali_parenti', $row["Dichiarazioni patrimoniali del coniuge non separato e parenti entro il secondo grado"]);
        if (isset($row["Dichiarazione dei redditi*"])) {
            $item->setAttribute('dichiarazione_redditi', $row["Dichiarazione dei redditi*"]);
        }
        if (isset($row["Dichiarazione dei redditi"])) {
            $item->setAttribute('dichiarazione_redditi', $row["Dichiarazione dei redditi"]);
        }
        if (isset($row["Spese elettorali*"])) {
            $item->setAttribute('spese_elettorali', $row["Spese elettorali*"]);
        }
        if (isset($row["Spese elettorali"])) {
            $item->setAttribute('spese_elettorali', $row["Spese elettorali"]);
        }
        $item->setAttribute('spese_elettorali_files', $row["Spese elettorali (allegati)"]);
        if (isset($row["Variazioni situazione patrimoniale*"])) {
            $item->setAttribute('variazioni_situazione_patrimoniale', $row["Variazioni situazione patrimoniale*"]);
        }
        if (isset($row["Variazioni situazione patrimoniale"])) {
            $item->setAttribute('variazioni_situazione_patrimoniale', $row["Variazioni situazione patrimoniale"]);
        }
        if (isset($row["Altre cariche*"])) {
            $item->setAttribute('altre_cariche', $row["Altre cariche*"]);
        }

        if (isset($row["Altre cariche"])) {
            $item->setAttribute('altre_cariche', $row["Altre cariche"]);
        }
        $item->setAttribute('eventuali_incarichi', $row["Altri eventuali incarichi con oneri a carico della finanza pubblica e l'indicazione dei compensi spettanti"]);
        $item->setAttribute('dichiarazione_incompatibilita', $row["Dichiarazione incompatibilità e inconferibilità"]);
        $item->setAttribute('notes', $row["Ulteriori informazioni"]);

        $item->setAttribute('de_abstract', $row['Kurze Beschreibung [de]']);
        $item->setAttribute('de_bio', $row['Biografie [de]']);
        $item->setAttribute('de_situazione_patrimoniale', $row['Erbliche Situation [de]']);
        $item->setAttribute('de_spese_elettorali', $row['Wahlkosten* [de]']);
        $item->setAttribute('de_notes', $row['Weitere Informationen [de]']);

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public function generatePayload()
    {
        $locale = 'ita-IT';
        $payload = $this->getNewPayloadBuilderInstance();
        $payload->setClassIdentifier('public_person');
        $payload->setRemoteId($this->attribute('_id'));
        $payload->setParentNode($this->discoverParentNode());
        $payload->setLanguages([$locale]);

        $payload->setData($locale, 'given_name', $this->attribute('given_name'));
        $payload->setData($locale, 'family_name', $this->attribute('family_name'));
        $payload->setData($locale, 'abstract', $this->attribute('abstract'));
        $payload->setData($locale, 'image', ocm_image::getIdListByName($this->attribute('image')));
        $payload->setData($locale, 'bio', $this->attribute('bio'));
        $payload->setData($locale, 'has_contact_point', ocm_online_contact_point::getIdListByName($this->attribute('has_contact_point')));
        $payload->setData($locale, 'curriculum', $this->formatBinary($this->attribute('curriculum'), false));
        $payload->setData($locale, 'situazione_patrimoniale', $this->attribute('situazione_patrimoniale'));
        $payload->setData($locale, 'dichiarazioni_patrimoniali_soggetto', $this->formatBinary($this->attribute('dichiarazioni_patrimoniali_soggetto'), true));
        $payload->setData($locale, 'dichiarazioni_patrimoniali_parenti', $this->formatBinary($this->attribute('dichiarazioni_patrimoniali_parenti')));
        $payload->setData($locale, 'dichiarazione_redditi', $this->formatBinary($this->attribute('dichiarazione_redditi')));
        $payload->setData($locale, 'spese_elettorali', $this->attribute('spese_elettorali'));
        $payload->setData($locale, 'spese_elettorali_files', $this->formatBinary($this->attribute('spese_elettorali_files')));
        $payload->setData($locale, 'variazioni_situazione_patrimoniale', $this->formatBinary($this->attribute('variazioni_situazione_patrimoniale')));
        $payload->setData($locale, 'altre_cariche', $this->formatBinary($this->attribute('altre_cariche')));
        $payload->setData($locale, 'eventuali_incarichi', $this->formatBinary($this->attribute('eventuali_incarichi')));
        $payload->setData($locale, 'dichiarazione_incompatibilita', $this->formatBinary($this->attribute('dichiarazione_incompatibilita')));
        $payload->setData($locale, 'notes', $this->attribute('notes'));
        $payload->setData($locale, 'has_role', '1');

        return $this->appendTranslationsToPayloadIfNeeded($payload);
    }

    // 3da91bfec50abc9740f0f3d62c8aaac4 amm
    // 50f295ca2a57943b195fa8ffc6b909d8 pol
    private function discoverParentNode(): int
    {
        $type = $this->getPersonType();

        if ($type) {
            $remoteId = $type == 'Amministrativo' ? '3da91bfec50abc9740f0f3d62c8aaac4' : '50f295ca2a57943b195fa8ffc6b909d8';
            return $this->getNodeIdFromRemoteId($remoteId);
        }

        return $this->getUntypedParentNode();
    }

    private function getUntypedParentNode()
    {
        $remoteId = '01268e2ede40faaa233c64acc189c5cc'; // Cessati dall'incarico
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if ($object instanceof eZContentObject){
            return (int)$object->mainNodeID();
        }

        $remoteId = 'ocm_cessati'; // temp cessati dall'incarico
        $object = eZContentObject::fetchByRemoteID($remoteId);
        if ($object instanceof eZContentObject){
            return (int)$object->mainNodeID();
        }

        $object = eZContentFunctions::createAndPublishObject([
            'parent_node_id' => eZINI::instance('content.ini')->variable('NodeSettings', 'MediaRootNode'),
            'remote_id' => $remoteId,
            'class_identifier' => 'folder',
            'attributes' => [
                'name' => "Cessati dall'incarico"
            ]
        ]);
        if ($object instanceof eZContentObject){
            return (int)$object->mainNodeID();
        }

        return false;
    }

    public function getPersonType(): ?string
    {
        $name = $this->attribute('family_name') . ' ' . $this->attribute('given_name');
        /** @var ocm_time_indexed_role[] $rows */
        $rows = ocm_time_indexed_role::fetchByField('person', $name);
        $type = false;
        foreach ($rows as $row){
            $type = $rows[0]->attribute('type');
            if (!empty($type)){
                return $type;
            }
        }

        return $type;
    }

    public static function getRangeValidationHash(): array
    {
        return [
            'Punti di contatto*' => [
                'strict' => false,
                'ref' => ocm_online_contact_point::getRangeRef()
            ],
            'Immagini' => [
                'strict' => false,
                'ref' => ocm_image::getRangeRef()
            ],
//            'Incarichi*' => [
//                'strict' => false,
//                'ref' => ocm_time_indexed_role::getRangeRef()
//            ],
        ];
    }

    public static function getMax160CharConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve"
        ];
    }

    public static function getInternalLinkConditionalFormatHeaders(): array
    {
        return [
            "Descrizione breve",
            "Biografia",
            "Situazione patrimoniale",
        ];
    }

    public static function getUrlValidationHeaders(): array
    {
        return [
            "Curriculum vitae",
        ];
    }

    public static function getColumnName(): string
    {
        return 'Nome completo';
    }

    public static function getImportPriority(): int
    {
        return 40;
    }

    public static function getIdListByName($name, $field = 'name', string $tryWithPrefix = null): array
    {
        $data = [];
        $names = explode(PHP_EOL, $name);
        if (!self::isEmptyArray($names)){

            $names = self::trimArray($names);

            $list = static::fetchObjectList(
                static::definition(), ['_id'],
                ["concat_ws(' ', family_name, given_name)" => [$names]]
            );
            $data = array_column($list, '_id');
        }

        return $data;
    }

    public static function getTypedPersonIdListByName($name)
    {
        $data = [];
        $names = explode(PHP_EOL, $name);
        if (!self::isEmptyArray($names)){

            $names = self::trimArray($names);

            /** @var ocm_public_person[] $list */
            $list = static::fetchObjectList(
                static::definition(), null,
                ["concat_ws(' ', family_name, given_name)" => [$names]]
            );

            foreach ($list as $item){
                if ($item->getPersonType()){
                    $data[] = $item->attribute('_id');
                }
            }
        }

        return $data;
    }

    public function name(): ?string
    {
        return $this->attribute('family_name') . ' ' . $this->attribute('given_name');
    }

}