<?php

use Opencontent\Opendata\Rest\Client\PayloadBuilder;

interface ocm_interface
{
    public static function getSpreadsheetTitle(): string;

    public static function getIdColumnLabel(): string;

    public static function getColumnName(): string;

    public static function getSortField(): string;

    public function toSpreadsheet(): array;

    public static function fromSpreadsheet($row): ocm_interface;

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface;

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []): ?ocm_interface;

    /**
     * @return PayloadBuilder|PayloadBuilder[]
     */
    public function generatePayload();

    public function storePayload(): int;

    public static function getImportPriority(): int;

    public static function canImport(): bool;

    public static function canPull(): bool;

    public static function canPush(): bool;

    public static function canExport(): bool;

    public static function enableImport(): void;

    public static function disableImport(): void;

    public function storeThis(bool $isUpdate): bool;

    public function setAttribute($attr, $val);

    public static function checkPayloadGeneration(): bool;
}