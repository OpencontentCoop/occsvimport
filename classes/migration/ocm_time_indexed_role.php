<?php

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
        $this->internalFromOpencityNode($node, $options);
        $this->setAttribute('type', $node->classIdentifier() === 'politico' ? 'Politico' : 'Amministrativo');

        return $this;
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
        ];
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
        return 100;
    }


}