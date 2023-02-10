<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_time_indexed_role extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

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
        if ($node->classIdentifier() === 'dipendente'){
            return $this->fromNode($node, $this->getComunwebFieldMapperFromDipendente(), $options);
        }
        if ($node->classIdentifier() === 'politico'){
            return $this->fromNode($node, $this->getComunwebFieldMapperFromPolitico(), $options);
        }
        if ($node->classIdentifier() === 'ruolo'){
            return $this->fromNode($node, $this->getComunwebFieldMapperFromRuolo(), $options);
        }

        return $this->fromNode($node, [], $options);
    }

    protected function getComunwebFieldMapperFromRuolo(): array
    {
        return [];
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

    public function toSpreadsheet(): array
    {
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
            "Competenze" => $this->attribute('competences'),
            "Deleghe" => $this->attribute('delegations'),
            "Incarichi di posizione organizzativa" => $this->attribute('organizational_position'),
            "Incarico dirigenziale" => $this->attribute('incarico_dirigenziale'),
            "Ruolo principale" => $this->attribute('ruolo_principale'),
            "Priorità" => $this->attribute('priorita'),
            "Ulteriori informazioni" => $this->attribute('notes'),
            'Pagina contenitore' => $this->attribute('_parent_name'),
            'Url originale' => $this->attribute('_original_url'),
        ];
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
        return 'Titolo dell\'incarico';
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        return new static();
    }

    public function generatePayload(): array
    {
        return [];
    }

    public static function getImportPriority(): int
    {
        return 110;
    }


}