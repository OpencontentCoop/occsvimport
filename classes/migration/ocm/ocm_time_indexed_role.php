<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_time_indexed_role extends OCMPersistentObject implements ocm_interface
{
    public static $fields = [
        'label',
        'person',
        'role',
        'type',
        'for_entity',
        'compensi',
        'importi',
        'start_time',
        'end_time',
        'data_insediamento',
        'atto_nomina',
        'competences',
        'delegations',
        'organizational_position',
        'incarico_dirigenziale',
        'ruolo_principale',
        'priorita',
        'notes',
    ];

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $this->fromNode($node, $this->getOpencityFieldMapper(), $options);
        $this->setAttribute('type', $node->classIdentifier() === 'politico' ? 'Politico' : 'Amministrativo');

        return $this;
    }

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $role = false;
        if ($node->classIdentifier() === 'dipendente'){
            $role = $this->fromNode($node, $this->getComunwebFieldMapperFromDipendente(), $options);
        } elseif ($node->classIdentifier() === 'politico'){
            $role = $this->fromNode($node, $this->getComunwebFieldMapperFromPolitico(), $options);
        } elseif ($node->classIdentifier() === 'ruolo'){
            $role = $this->fromNode($node, $this->getComunwebFieldMapperFromRuolo(), $options);
        }

        if ($role){
            $forEntities = explode(PHP_EOL, $role->attribute('for_entity'));
            sort($forEntities);
            if (count($forEntities) > 1){
                $isUpdate = $options['is_update'] ?? false;
                $first = array_shift($forEntities);
                $role->setAttribute('for_entity', $first);
                foreach ($forEntities as $index => $forEntity){
                    $forEntity = trim($forEntity);
                    if (!empty($forEntity)) {
                        $duplicateRole = clone $role;
                        $duplicateRole->setAttribute('_id', $role->attribute('_id') . '-' . $index);
                        $duplicateRole->setAttribute('for_entity', $forEntity);
                        $duplicateRole->storeThis($isUpdate);
                    }
                }
            }

            return $role;
        }

        return $this->fromNode($node, [], $options);
    }

    protected function getComunwebFieldMapperFromRuolo(): array
    {
        return [
            'label' => OCMigration::getMapperHelper('titolo'),
            'person' => OCMigration::getMapperHelper('utente'),
            'role' => OCMigration::getMapperHelper('ruolo'),
            'type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $personClass = $firstLocalizedContentData['utente']['content'][0]['classIdentifier'] ?? false;
                return $personClass === 'dipendente' ? 'Amministrativo' : 'Politico';
            },
            'for_entity' => OCMigration::getMapperHelper('struttura_di_riferimento'),
            'compensi' => false,
            'importi' => false,
            'start_time' => false,
            'end_time' => false,
            'data_insediamento' => false,
            'atto_nomina' => OCMigration::getMapperHelper('atti'),
            'competences' => false,
            'delegations' => false,
            'organizational_position' => false,
            'incarico_dirigenziale' => false,
            'ruolo_principale' => false,
            'priorita' => false,
            'notes' => false,
        ];
    }

    protected function getComunwebFieldMapperFromDipendente(): array
    {
        return [
            'label' => false,
            'person' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return $content->metadata['name']['ita-IT'];
            },
            'role' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return 'Dipendente';
            },
            'type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return 'Amministrativo';
            },
            'for_entity' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $data = [];
                foreach (['area', 'servizio', 'ufficio', 'struttura'] as $identifier)
                if (isset($firstLocalizedContentData[$identifier]['content'][0]['name']['ita-IT'])){
                    $data[] = $firstLocalizedContentData[$identifier]['content'][0]['name']['ita-IT'];
                };
                return implode(PHP_EOL, array_unique($data));
            },
            'compensi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return ''; // non migrabile la sorgente è una matrice
            },
            'importi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return ''; // non migrabile la sorgente è un multi binary
            },
            'start_time' => false,
            'end_time' => false,
            'data_insediamento' => false,
            'atto_nomina' => OCMigration::getMapperHelper('atti'),
            'competences' => false,
            'delegations' => false,
            'organizational_position' => false,
            'incarico_dirigenziale' => false,
            'ruolo_principale' => false,
            'priorita' => false,
            'notes' => false,
        ];
    }

    protected function getComunwebFieldMapperFromPolitico(): array
    {
        return [
            'label' => false,
            'person' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return $content->metadata['name']['ita-IT'];
            },
            'role' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return 'Membro';
            },
            'type' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return 'Politico';
            },
            'for_entity' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                $data = [];
                $object = eZContentObject::fetch($content->metadata['id']);
                if ($object instanceof eZContentObject){
                    $organiPolitici = $object->reverseRelatedObjectList(false, 0, false, ['AllRelations' => true, 'RelatedClassIdentifiers' => ['organo_politico']]);
                    foreach ($organiPolitici as $organoPolitico){
                        $data[] = $organoPolitico->attribute('name');
                    }
                }

                return implode(PHP_EOL, $data);
            },
            'compensi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return ''; // non migrabile la sorgente è una multi binary
            },
            'importi' => function(Content $content, $firstLocalizedContentData, $firstLocalizedContentLocale, $options){
                return ''; // non migrabile la sorgente è un multi binary
            },
            'start_time' => OCMigration::getMapperHelper('data_iniziomandato'),
            'end_time' => OCMigration::getMapperHelper('data_finemandato'),
            'data_insediamento' => OCMigration::getMapperHelper('decorrenza_carica'),
            'atto_nomina' => OCMigration::getMapperHelper('atti'),
            'competences' => false,
            'delegations' => false,
            'organizational_position' => false,
            'incarico_dirigenziale' => false,
            'ruolo_principale' => false,
            'priorita' => false,
            'notes' => OCMigration::getMapperHelper('nota'),
        ];
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Incarichi';
    }

    public static function getIdColumnLabel(): string
    {
        return 'Identificativo incarico*';
    }

    public static function getSortField(): string
    {
        return 'person';
    }

    public function toSpreadsheet(): array
    {
        $competences = json_decode($this->attribute('competences'), true);
        $delegations = json_decode($this->attribute('delegations'), true);
        return [
            "Identificativo incarico*" => $this->attribute('_id'),
            "Titolo dell'incarico" => $this->attribute('label'),
            "Persona che ha il ruolo*" => $this->attribute('person'),
            "Ruolo*" => $this->attribute('role'),
            "Tipo di incarico*" => $this->attribute('type'),
            "Unità organizzativa*" => $this->attribute('for_entity'),
            "Compensi" => $this->attribute('compensi'),
            "Importi di viaggio e/o servizio" => $this->attribute('importi'),
            "Data inizio incarico*" => $this->attribute('start_time'),
            "Data conclusione incarico" => $this->attribute('end_time'),
            "Data insediamento" => $this->attribute('data_insediamento'),
            "Atto di nomina" => $this->attribute('atto_nomina'),
            "Competenze" => implode(PHP_EOL, $competences['ita-IT']),
            "Deleghe" => implode(PHP_EOL, $delegations['ita-IT']),
            "Incarichi di posizione organizzativa" => $this->attribute('organizational_position'),
            "Incarico dirigenziale" => $this->attribute('incarico_dirigenziale'),
            "Ruolo principale" => $this->attribute('ruolo_principale'),
            "Priorità" => $this->attribute('priorita'),
            "Ulteriori informazioni" => $this->attribute('notes'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        $item = new static();
        $item->setAttribute('_id', $row["Identificativo incarico*"]);
        $item->setAttribute('label', $row["Titolo dell'incarico"]);
        $item->setAttribute('person', $row["Persona che ha il ruolo*"]);
        $item->setAttribute('role', $row["Ruolo*"]);
        $item->setAttribute('type', $row["Tipo di incarico*"]);
        $item->setAttribute('for_entity', $row["Unità organizzativa*"]);
        $item->setAttribute('compensi', $row["Compensi"]);
        $item->setAttribute('importi', $row["Importi di viaggio e/o servizio"]);
        $item->setAttribute('start_time', $row["Data inizio incarico*"]);
        $item->setAttribute('end_time', $row["Data conclusione incarico"]);
        $item->setAttribute('data_insediamento', $row["Data insediamento"]);
        $item->setAttribute('atto_nomina', $row["Atto di nomina"]);
        $item->setAttribute('organizational_position', $row["Incarichi di posizione organizzativa"]);
        $item->setAttribute('incarico_dirigenziale', $row["Incarico dirigenziale"]);
        $item->setAttribute('ruolo_principale', $row["Ruolo principale"]);
        $item->setAttribute('priorita', $row["Priorità"]);
        $item->setAttribute('notes', $row["Ulteriori informazioni"]);
        $item->setAttribute('competences', json_encode(['ita-IT' => explode(PHP_EOL, $row["Competenze"])]));
        $item->setAttribute('delegations', json_encode(['ita-IT' => explode(PHP_EOL, $row["Deleghe"])]));

        self::fillNodeReferenceFromSpreadsheet($row, $item);
        return $item;
    }

    public static function getDateValidationHeaders(): array
    {
        return [
            "Data inizio incarico*",
            "Data conclusione incarico",
            "Data insediamento",
        ];
    }

    public static function getRangeValidationHash(): array
    {
        return [
            "Ruolo*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('ruoli'),
            ],
            "Tipo di incarico*" => [
                'strict' => true,
                'ref' => self::getVocabolaryRangeRef('incarichi'),
            ],
            "Unità organizzativa*" => [
                'strict' => true,
                'ref' => ocm_organization::getRangeRef()
            ],
            "Persona che ha il ruolo*" => [
                'strict' => true,
                'ref' => ocm_public_person::getRangeRef()
            ],
            "Atto di nomina" => [
                'strict' => true,
                'ref' => ocm_document::getRangeRef()
            ],
        ];
    }


    public static function getColumnName(): string
    {
        return 'Persona che ha il ruolo*';
    }

    public function generatePayload()
    {
        return $this->getNewPayloadBuilderInstance();
    }

    public static function getImportPriority(): int
    {
        return 110;
    }


}