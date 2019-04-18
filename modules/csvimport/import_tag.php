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

        $googleSpreadsheetUrl = $http->variable('GoogleSpreadsheetUrl', false);
        $sheet = $http->variable('ImportGoogleSpreadsheetSheet', false);

        $handler = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl);
        $worksheetFeed = $handler->getWorksheetFeed();
        $worksheet = $worksheetFeed->getByTitle($sheet);
        $doc = OCGoogleSpreadsheetHandler::getWorksheetAsSQLICSVDoc($worksheet);
        $dataSource = $doc->rows;
        $tagList = array();
        foreach ($dataSource as $item) {
            $tagList[] = (string)$item->tag;
        }

        $locale = eZLocale::currentLocaleCode();
        $language = eZContentLanguage::fetchByLocale($locale);
        if (!$language instanceof eZContentLanguage) {
            return $module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
        }
        $languageMask = eZContentLanguage::maskByLocale(array($language->attribute('locale')), true);
        $db = eZDB::instance();

        foreach ($tagList as $newKeyword) {
            $db->begin();
            $tag = new eZTagsObject(
                array('parent_id' => $parentTagID,
                    'main_tag_id' => 0,
                    'depth' => $parentTag instanceof eZTagsObject ? $parentTag->attribute('depth') + 1 : 1,
                    'path_string' => $parentTag instanceof eZTagsObject ? $parentTag->attribute('path_string') : '/',
                    'main_language_id' => $language->attribute('id'),
                    'language_mask' => $languageMask
                ),
                $language->attribute('locale')
            );
            $tag->store();
            $translation = new eZTagsKeyword(
                array(
                    'keyword_id' => $tag->attribute('id'),
                    'language_id' => $language->attribute('id'),
                    'keyword' => $newKeyword,
                    'locale' => $language->attribute('locale'),
                    'status' => eZTagsKeyword::STATUS_PUBLISHED
                )
            );
            $translation->setAttribute('language_id', $translation->attribute('language_id') + 1);
            $translation->store();
            $tag->setAttribute('path_string', $tag->attribute('path_string') . $tag->attribute('id') . '/');
            $tag->store();
            $tag->updateModified();

            if (class_exists('ezpEvent', false))
                ezpEvent::getInstance()->filter('tag/add', array('tag' => $tag, 'parentTag' => $parentTag));

            $db->commit();
        }

        $tagUrl = $parentTag->attribute('url');
        eZURI::transformURI($tagUrl);
        $module->redirectTo($tagUrl);
        
        return;
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
