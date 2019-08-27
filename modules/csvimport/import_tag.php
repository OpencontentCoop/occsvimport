<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$tpl->setVariable('error', false);
$ini = eZINI::instance('csvimport.ini');
/** @var eZModule $module */
$module = $Params['Module'];
$parentTagID = $Params['ParentTagID'];

function makeErrorArray($num, $msg)
{
    return array('number' => $num, 'message' => $msg);
}

$parentTag = eZTagsObject::fetch((int)$parentTagID);
$tpl->setVariable('tag', $parentTag);
if ($parentTag instanceof eZTagsObject) {

    $tpl->setVariable('googleSpreadsheetUrl', false);

    if ($module->isCurrentAction('SelectGoogleSpreadsheet')) {

        $googleSpreadsheetUrl = $http->variable('GoogleSpreadsheetUrl', false);
        $tpl->setVariable('googleSpreadsheetUrl', $googleSpreadsheetUrl);

        try {
            $handler = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl);

            if (count($handler->getWorksheetFeed()->getEntries()) > 0) {
                $worksheetFeed = $handler->getWorksheetFeed();
                $feedTitle = (string)$worksheetFeed->getXml()->title;
                $tpl->setVariable('feed_title', $feedTitle);
                $entries = $worksheetFeed->getEntries();
                $sheets = array();
                foreach ($entries as $entry) {
                    $sheets[] = $entry->getTitle();
                }
                $tpl->setVariable('sheets', $sheets);
            } else {
                $tpl->setVariable('error', makeErrorArray(
                    1,
                    "Il documento non contiene fogli"
                ));
            }
        } catch (Exception $e) {
            $tpl->setVariable('error', makeErrorArray(
                $e->getCode(),
                $e->getMessage()
            ));
        }
    } elseif ($module->isCurrentAction('ImportGoogleSpreadsheet')) {

        try {
            $googleSpreadsheetUrl = $http->variable('GoogleSpreadsheetUrl', false);
            $sheet = $http->variable('ImportGoogleSpreadsheetSheet', false);

            $dataSource = OCCSVImportTagHandler::getSQLICSVRowSetFromGoogleSpreadsheetUrl($googleSpreadsheetUrl, $sheet);
            $tagStructList = OCCSVImportTagHandler::getStructList($dataSource, $googleSpreadsheetUrl);
            OCCSVImportTagHandler::importStructList($tagStructList, $parentTag);

            $tagUrl = $parentTag->attribute('url');
            eZURI::transformURI($tagUrl);
            $module->redirectTo($tagUrl);

            return;
        } catch (Exception $e) {
            $tpl->setVariable('error', makeErrorArray(
                $e->getCode(),
                $e->getMessage()
            ));
        }
    }
} else {
    $tpl->setVariable('error', makeErrorArray(
        1,
        'Tag not found'
    ));
}

$Result = array();
$Result['content'] = $tpl->fetch("design:csvimport/import_tag.tpl");
$Result['path'] = array(
    array(
        'url' => '/csvimport/import_tag/',
        'text' => ezpI18n::tr('extension/occsvimport', "Importa tag da Google Sheet")
    )
);
