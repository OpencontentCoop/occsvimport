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

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface
    {
        $mapper = [
            'legal_name' => false,
            'alt_name' => false,
            'topics' => false,
            'abstract' => false,
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
            'de_legal_name' => false,
            'de_abstract' => false,
            'de_main_function' => false,
            'de_alt_name' => false,
            'de_more_information' => false,
        ];

        return $this->fromNode($node, $mapper, $options);
    }

    protected function discoverParentNode(): int
    {
        return $this->getNodeIdFromRemoteId('10742bd28e405f0e83ae61223aea80cb');
    }

    public static function getImportPriority(): int
    {
        return 29;
    }
}