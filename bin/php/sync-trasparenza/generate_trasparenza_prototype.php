<?php

require 'autoload.php';

use Google\Service\Sheets\Sheet;
use League\HTMLToMarkdown\HtmlConverter;

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();
$options = $script->getOptions(
    "[root:][sheet:]",
    "",
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);


class WalkerTrasparenza
{
    private $recursionLevel = 0;

    private $callback;

    public function __construct($callback = null)
    {
        $this->callback = $callback;
    }

    public function walk(eZContentObjectTreeNode $root, $parent = null)
    {
        foreach ($root->children() as $child) {
            if ($child->attribute('class_identifier') === 'pagina_trasparenza') {
                eZCLI::instance()->warning(self::pad($this->recursionLevel) . ' ' . $child->attribute('name'));
                call_user_func($this->callback, $child, $root, $this->recursionLevel);
            }
            $this->recursionLevel++;
            $this->walk($child, $root);
            $this->recursionLevel--;
        }
    }

    public static function pad($recursionLevel)
    {
        return $recursionLevel > 0 ? str_pad(' ', $recursionLevel * 2, "    ", STR_PAD_LEFT) . '|- ' : '';
    }
}

function convertToMarkdown(?string $html): string
{
    if (!$html) {
        return '';
    }

    $converter = new HtmlConverter();
    return $converter->convert($html);
}

function getSheet($spreadsheetId, $sheetTitle): Sheet
{
    global $googleSheetClient, $sheetService;
    $spreadsheet = new Opencontent\Google\GoogleSheet($spreadsheetId, $googleSheetClient);
    try {
        $sheet = $spreadsheet->getByTitle($sheetTitle);
    } catch (Exception $e) {
        $sheetService->spreadsheets->batchUpdate(
            $spreadsheetId,
            new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(
                ['requests' => ['addSheet' => ['properties' => ['title' => $sheetTitle]],],]
            )
        );
        $spreadsheet = new Opencontent\Google\GoogleSheet($spreadsheetId, $googleSheetClient);
        $sheet = $spreadsheet->getByTitle($sheetTitle);
    }

    return $sheet;
}

function cleanSheet($spreadsheetId, Sheet $sheet)
{
    global $sheetService;
    $rowCount = $sheet->getProperties()->getGridProperties()->getRowCount();
    $allColCount = $sheet->getProperties()->getGridProperties()->getColumnCount();
    $sheetTitle = $sheet->getProperties()->getTitle();
    $range = "$sheetTitle!R1C1:R{$rowCount}C{$allColCount}";
    $clear = new Google_Service_Sheets_ClearValuesRequest();
    $sheetService->spreadsheets_values->clear($spreadsheetId, $range, $clear);
}

function writeSheet($spreadsheetId, Sheet $sheet, array $values)
{
    global $sheetService, $cli;
    $count = count($values);
    $countHeaders = count($values[0]);
    if ($count > 1000) {
        $length = $count - 800;
        $cli->warning("Add $length rows");
        $sheetService->spreadsheets->batchUpdate(
            $spreadsheetId,
            new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => ['appendDimension' => ['dimension' => 'ROWS', 'length' => $length]],
            ])
        );
    }
    $sheetTitle = $sheet->getProperties()->getTitle();
    $range = "$sheetTitle!R1C1:R{$count}C{$countHeaders}";
    $cli->warning("Write $count rows in range $range");
    $sheetService->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        new Google_Service_Sheets_ValueRange(['values' => $values,]),
        ['valueInputOption' => 'USER_ENTERED',]
    );
}

function hashToRows($vocabularies): array
{
    $length = 0;
    foreach ($vocabularies as $terms) {
        $vocLength = count($terms);
        if ($vocLength > $length) {
            $length = $vocLength;
        }
    }
    $rows = [];
    for ($i = 0; $i < $length; $i++) {
        $row = [];
        foreach ($vocabularies as $vocabulary){
            $row[] = $vocabulary[$i] ?? '';
        }
        $rows[] = $row;
    }

    return $rows;
}

try {
    $rootId = $options['root'] ?? '5399ef12f98766b90f1804e5d52afd75';
    $object = eZContentObject::fetchByRemoteID($rootId);
    if (!$object instanceof eZContentObject) {
        throw new Exception("Object $rootId not found");
    }
    if ($object->attribute('class_identifier') != 'trasparenza') {
        throw new Exception("Object is not trasparenza");
    }
    $class = eZContentClass::fetchByIdentifier('pagina_trasparenza');
    if (!$class instanceof eZContentClass) {
        throw new Exception("Class pagina_trasparenza not found");
    }

    $googleSheetClient = new OCMGoogleSheetClient();
    $sheetService = $googleSheetClient->getGoogleSheetService();

    $data = new ArrayObject();
    $spreadsheetId = $options['sheet'];
    $sheet = getSheet($spreadsheetId, 'Dati');
    $sheetVoc = getSheet($spreadsheetId, 'Vocabolari');


    $vocabularies = [];
    $headers = [
        'tree',
        'remote_id',
        'parent_remote_id',
    ];
    foreach ($class->dataMap() as $identifier => $classAttribute) {
        if ($classAttribute->attribute('data_type_string') === eZPageType::DATA_TYPE_STRING) {
            continue;
        }
        $headers[] = $identifier;
        if ($classAttribute->attribute('data_type_string') === eZSelectionType::DATA_TYPE_STRING) {
            $vocabulary = array_column($classAttribute->content()['options'], 'name');
            array_unshift($vocabulary, $identifier);
            $vocabularies[] = $vocabulary;
        }
    }

    $dataMapIdentifier = OCTrasparenzaSpreadsheet::getDataConverters();

    cleanSheet($spreadsheetId, $sheetVoc);
    writeSheet($spreadsheetId, $sheetVoc, hashToRows($vocabularies));

    $countHeaders = count($headers);
    $data[] = $headers;
    (new WalkerTrasparenza(
        function (eZContentObjectTreeNode $node, eZContentObjectTreeNode $parent, $level) use (
            $data,
            $dataMapIdentifier
        ) {
            $item = [
                WalkerTrasparenza::pad($level) . $node->attribute('name'),
                $node->object()->remoteID(),
                $node->fetchParent()->object()->remoteID(),
            ];
            $dataMap = $node->dataMap();
            foreach ($dataMapIdentifier as $identifier => $callbacks) {
                $callback = $callbacks['fromAttribute'];
                $item[] = isset($dataMap[$identifier]) ? $callback($dataMap[$identifier]) : '';
            }
            $data[] = $item;
        }
    ))->walk($object->mainNode());

    $values = $data->getArrayCopy();
    cleanSheet($spreadsheetId, $sheet);
    writeSheet($spreadsheetId, $sheet, $values);


} catch (Throwable $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();


