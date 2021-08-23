<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$tpl->setVariable('error', false);
$ini = eZINI::instance('csvimport.ini');
/** @var eZModule $module */
$module = $Params['Module'];
$parentNodeID = $Params['ParentNodeID'];

function makeErrorArray($num, $msg)
{
    return array('number' => $num, 'message' => $msg);
}

$NodeID = $http->variable('NodeID', $parentNodeID);
$node = eZContentObjectTreeNode::fetch($NodeID);
if (!$node) {
    return $module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
}

$tpl->setVariable('googleSpreadsheetUrl', false);

if ($module->isCurrentAction('UploadFile')) {
    $httpFileName = 'ImportFile';
    if (eZHTTPFile::canFetch($httpFileName)) {
        $httpFile = eZHTTPFile::fetch($httpFileName);
        if ($httpFile) {
            $handler = new OCCSVImportHandler;
            $isValid = $handler->inizializeFromHTTPFile($httpFile);
            if (!$isValid) {
                $tpl->setVariable('error', makeErrorArray(
                    OCCSVImportHandler::ERROR_DOCNOTSUPPORTED,
                    'Tipo di documento non supportato'
                ));
            } else {
                $handler->setImportOption('parent_node_id', $NodeID);
                $handler->setImportOption('name', 'Importazione in ' . $node->attribute('name'));

                /* Se è stata flaggata l'impostazione per l'import incrementale aggiungo un'opzione */
                if ($http->hasPostVariable('Incremental') && $http->postVariable('Incremental') == 1) {
                    $handler->setImportOption('incremental', 1);
                }

                $handler->addImport();
                $module->redirectTo('sqliimport/list');
            }
        }
    }
}elseif ($module->isCurrentAction('SelectGoogleSpreadsheet')) {

    $googleSpreadsheetUrl = $http->variable('GoogleSpreadsheetUrl', false);
    $tpl->setVariable('googleSpreadsheetUrl', $googleSpreadsheetUrl);

    try {
        $handler = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl);

        if (count($handler->getWorksheetFeed()->getSheetTitleList()) > 0){
            return $module->redirectTo('csvimport/configure/' . $NodeID . '/' . $handler->getWorksheetId());
        }else{
            $tpl->setVariable('error', makeErrorArray(
                1,
                "Il documento non contiene fogli"
            ));
        }
    }catch (Exception $e){
        $tpl->setVariable('error', makeErrorArray(
            $e->getCode(),
            $e->getMessage()
        ));
    }

}

$tpl->setVariable('node', $node);
$tpl->setVariable('NodeID', $NodeID);

$Result = array();
$Result['content'] = $tpl->fetch("design:csvimport/import.tpl");
$Result['path'] = array(
    array(
        'url' => '/csvimport/import/',
        'text' => ezpI18n::tr('extension/occsvimport', "Importa CSV")
    )
);

