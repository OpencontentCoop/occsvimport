<?php
/** @var eZModule $module */
$module = $Params['Module'];
$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$nodeID = $Params['ParentNodeID'];
$importIdentifier = $Params['ImportIdentifier'];

function makeErrorArray($num, $msg)
{
    return array('number' => $num, 'message' => $msg);
}

$node = eZContentObjectTreeNode::fetch($nodeID);
if (!$node) {
    return $module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
}

$tpl->setVariable('node_id', $nodeID);
$tpl->setVariable('node', $node);
$tpl->setVariable('import_identifier', $importIdentifier);

try {
    $handler = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetId($importIdentifier);    
    $worksheetFeed = $handler->getWorksheetFeed();
    $feedTitle = (string)$worksheetFeed->getTitle();
    $tpl->setVariable('feed_title', $feedTitle);
    $sheets = $worksheetFeed->getSheetTitleList();
    $tpl->setVariable('sheets', $sheets);

} catch (Exception $e) {
    $tpl->setVariable('error', makeErrorArray(
        $e->getCode(),
        $e->getMessage()
    ));
}

if ($http->hasVariable('ImportGoogleSpreadsheetClass')){
    $classIdentifier = $http->variable('ImportGoogleSpreadsheetClass');
    $contentClass = eZContentClass::fetchByIdentifier($classIdentifier);
    if(!$contentClass instanceof eZContentClass)
        $tpl->setVariable('error', makeErrorArray(1, "Class $classIdentifier not found"));
}else{
    $classIdentifier = false;
}
$tpl->setVariable('selected_class_identifier', $classIdentifier);

if ($http->hasVariable('ImportGoogleSpreadsheetClass')){
    $sheet = $http->variable('ImportGoogleSpreadsheetSheet');
}else{
    $sheet = false;
}
$tpl->setVariable('selected_sheet', $sheet);

$incremental = (bool)$http->hasVariable('Incremental') && $http->variable('Incremental') == 1;
$tpl->setVariable('incremental', $incremental);

if ($http->hasVariable('MapFields')){
    $mapper = $http->variable('MapFields');
}else{
    $mapper = array();
}
$tpl->setVariable('mapped_headers', $mapper);

if ($http->hasVariable('FileDir')){
    $fileDir = $http->variable('FileDir');
}else{
    $fileDir = false;
}
$tpl->setVariable('file_dir', $fileDir);

$tpl->setVariable('mapped_headers', $mapper);

if ($http->hasVariable('DateFormat')){
    $dateFormat = $http->variable('DateFormat');
}else{
    $dateFormat = false;
}
$tpl->setVariable('date_format', $dateFormat);

if ($http->hasVariable('Language')){
    $language = $http->variable('Language');
}else{
    $language = eZContentObject::defaultLanguage();
}
$tpl->setVariable('language', $language);

if (!$tpl->hasVariable('error')){
    if ($module->isCurrentAction('UpdateGoogleSpreadsheet')) {
        if ($contentClass && $sheet){
            $attributes = $contentClass->fetchAttributes();
            $tpl->setVariable('class_attributes', $attributes);        

            try{
                $doc = OCGoogleSpreadsheetHandler::getWorksheetAsSQLICSVDoc($importIdentifier, $sheet, $contentClass);
                $headers = $doc->rows->getRawHeaders();
                $tpl->setVariable('headers', $headers);
            } catch (Exception $e) {
                $tpl->setVariable('error', makeErrorArray(
                    $e->getCode(),
                    $e->getMessage()
                ));
            }

        }

    }elseif ($module->isCurrentAction('ImportGoogleSpreadsheet')) {
        $handler->setImportOption('class_identifier', $classIdentifier);
        $handler->setImportOption('sheet', $sheet);

        if (!empty($mapper))
            $handler->setImportOption('fields_map', json_encode($mapper));

        if ($fileDir){
            $handler->setImportOption('file_dir', $fileDir);
        }

        if ($dateFormat){
            $handler->setImportOption('date_format', $dateFormat);
        }

        if ($incremental) {
            $handler->setImportOption('incremental', 1);
        }

        if ($language){
            $handler->setImportOption('language', $language);
        }
        $handler->setImportOption('parent_node_id', $nodeID);
        $handler->setImportOption('name', 'Importazione di ' .  $feedTitle .' in ' . $node->attribute('name'));


        $handler->addImport();
        $module->redirectTo('sqliimport/list');
        return;
    }
}

$Result = array();
$Result['content'] = $tpl->fetch("design:csvimport/configure.tpl");
$Result['path'] = array(
    array(
        'url' => '/csvimport/import/',
        'text' => ezpI18n::tr('extension/occsvimport', "Importa CSV")
    )
);
