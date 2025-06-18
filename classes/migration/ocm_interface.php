<?php

use Opencontent\Opendata\Rest\Client\PayloadBuilder;

interface ocm_interface
{
    public static function getSpreadsheetTitle();

    public static function getIdColumnLabel();

    public static function getColumnName();

    public static function getSortField();

    public function toSpreadsheet();

    public static function fromSpreadsheet($row);

    public function fromOpencityNode(eZContentObjectTreeNode $node, array $options = []);

    public function fromComunwebNode(eZContentObjectTreeNode $node, array $options = []);

    /**
     * @return PayloadBuilder|PayloadBuilder[]
     */
    public function generatePayload();

    public function storePayload();

    public static function getImportPriority();

    public static function canImport();

    public static function canPull();

    public static function canPush();

    public static function canExport();

    public static function enableImport();

    public static function disableImport();

    public function storeThis($isUpdate);

    public function setAttribute($attr, $val);

    public static function checkPayloadGeneration();

    public function validatePayload(OCMPayload $payload);
}