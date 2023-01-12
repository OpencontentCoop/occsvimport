<?php

class ocm_organization extends eZPersistentObject implements ocm_interface
{
    use ocm_trait;

    public static $fields = [
        'legal_name',
        'alt_name',
        'topics',
        'abstract',
        'description',
        'image',
        'main_function',
        'hold_employment',
        'type',
        'has_spatial_coverage',
        'has_online_contact_point',
        'attachments',
        'more_information',
        'identifier',
        'tax_code_e_invoice_service',
        'has_logo___name',
        'has_logo___url',
    ];

    public static function getSpreadsheetTitle(): string
    {
        return 'Unità organizzative';
    }

    public static function getIdColumnLabel(): string
    {
        return "Identificativo unità organizzativa*";
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $map = [
            'administrative_area' => [
                'attachments' => ['allegati'],
                'identifier' => ['ipacode'],
                'alt_name' => ['org_acronym'],
                '_constraint' =>  [
                    'type' => 'Area',
                ],
            ],
            'homogeneous_organizational_area' => [
                'attachments' => ['allegati'],
                'identifier' => ['a_o_o_identifier'],
                'alt_name' => ['org_acronym'],
                '_constraint' => [
                    'type' => 'Area',
                ],
            ],
            'office' => [
                'attachments' => ['allegati'],
                'alt_name' => ['acronym'],
                'hold_employment' => ['is_part_of'],
                'identifier' => [
                    'office_identifier',
                    'a_o_o_identifier',
                    'identifier',
                ],
                '_constraint' => [
                    'type' => 'Ufficio',
                ],
            ],
            'political_body' => [
                'type' => ['type_political_body'],
                '_constraint' => [],
            ]
        ];

        $object = $node->object();
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        $this->setAttribute('_id', $object->attribute('remote_id'));
        $alreadyDone = [];
        foreach (static::$fields as $identifier) {
            [$id] = explode('___', $identifier);
            if (isset($alreadyDone[$id])) {
                continue;
            }

            if (isset($map[$node->classIdentifier()][$id])){
                foreach ($map[$node->classIdentifier()][$id] as $mapToId){
                    $data = static::getAttributeString($mapToId, $dataMap, $options);
                    foreach ($data as $name => $value) {
                        $this->appendAttribute($id, $value);
                    }
                }
                $alreadyDone[$id] = true;
            }

            if (!isset($alreadyDone[$id])) {
                $data = static::getAttributeString($id, $dataMap, $options);
                foreach ($data as $name => $value) {
                    $this->setAttribute($name, $value);
                }
                $alreadyDone[$id] = true;
            }

            if (isset($map[$node->classIdentifier()]['_defaults'][$id]) && empty($this->attribute($id))){
                $this->setAttribute($id, $map[$node->classIdentifier()]['_defaults'][$id]);
            }
            if (isset($map[$node->classIdentifier()]['_constraint'][$id])){
                $this->setAttribute($id, $map[$node->classIdentifier()]['_constraint'][$id]);
            }
        }

        return $this;
    }

    public function toSpreadsheet(): array
    {
        return [
            'Identificativo unità organizzativa*' => $this->attribute('_id'),
            'Nome dell\'unità organizzativa*' => $this->attribute('legal_name'),
            'Descrizione breve*' => $this->attribute('abstract'),
            'Descrizione' => $this->attribute('description'),
            'Competenze*' => $this->attribute('main_function'),
            'Tipo di organizzazione*' => $this->attribute('type'),
            'Sede/i*' => $this->attribute('has_spatial_coverage'),
            'Contatti*' => $this->attribute('has_online_contact_point'),
            'Argomenti' => $this->attribute('topics'),
            'Immagine' => $this->attribute('image'),
            'Unità organizzativa genitore' => $this->attribute('hold_employment'),
            'Allegati' => $this->attribute('attachments'),
            'Ulteriori informazioni' => $this->attribute('more_information'),
            'Nome alternativo' => $this->attribute('alt_name'),
            'Identificatore univoco interno' => $this->attribute('identifier'),
            'Codice fiscale servizio di fatturazione elettronica' => $this->attribute('tax_code_e_invoice_service'),
        ];
    }

    public static function fromSpreadsheet($row): ocm_interface
    {
        return new static();
    }

    public static function getImportPriority(): int
    {
        return 30;
    }

    public function generatePayload(): array
    {
        return [];
    }
}