<?php

use Opencontent\Opendata\Api\Values\Content;

class ocm_public_organization extends ocm_organization
{
    public static function canPush(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static function canExport(): bool
    {
        return OCMigration::discoverContext() === 'opencity';
    }

    public static function getSpreadsheetTitle(): string
    {
        return 'Enti e fondazioni';
    }

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ocm_interface
    {
        $mapper = [
            'legal_name' => false,
            'alt_name' => false,
            'topics' => false,
            'abstract',
            'description' => false,
            'image' => false,
            'main_function' => false,
            'hold_employment' => false,
            'type' => false,
            'has_spatial_coverage' => false,
            'has_online_contact_point' => false,
            'attachments' => false,
            'more_information' => false,
            'identifier' => OCMigration::getMapperHelper('ipacode'),
            'tax_code_e_invoice_service' => OCMigration::getMapperHelper('tax_code'),
            'has_logo___name' => false,
            'has_logo___url' => false,
        ];

        return $this->fromNode($node, $mapper, $options);
    }
}