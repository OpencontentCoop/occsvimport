<?php

class OCCSVImportTagHandler
{
    private static $sheets = array();

    /**
     * @param $googleSpreadsheetUrl
     * @param $sheet
     * @param integer $recursion
     * @return SQLICSVRowSet
     * @throws \Google\Spreadsheet\Exception\WorksheetNotFoundException
     * @throws Exception
     */
    public static function getSQLICSVRowSetFromGoogleSpreadsheetUrl($googleSpreadsheetUrl, $sheet, $recursion = 0)
    {
        if (isset(self::$sheets[$sheet]) && self::$sheets[$sheet] != $recursion) {
            throw new Exception("Errore di ricorsione: il foglio $sheet è già usato nel livello " . self::$sheets[$sheet]);
        }
        $handler = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetUri($googleSpreadsheetUrl);
        $worksheetFeed = $handler->getWorksheetFeed();
        $worksheet = $worksheetFeed->getByTitle($sheet);
        self::$sheets[$sheet] = $recursion;

        $doc = OCGoogleSpreadsheetHandler::getWorksheetAsSQLICSVDoc($worksheet);
        return $doc->rows;
    }

    public static function importStructList($tagStructList, eZTagsObject $parentTag)
    {
        foreach ($tagStructList as $item) {
            $newTag = OCCSVImportTagHandler::importTag(
                $item['keyword'],
                $parentTag,
                $item['language']
            );
            if (count($item['synonyms'])) {
                foreach ($item['synonyms'] as $synonym) {
                    OCCSVImportTagHandler::addSynonym(
                        $synonym['keyword'],
                        $newTag,
                        $synonym['language']
                    );
                }
            }
            if (count($item['translations'])) {
                foreach ($item['translations'] as $translation) {
                    OCCSVImportTagHandler::addTranslation(
                        $translation['keyword'],
                        $newTag,
                        $translation['language']
                    );
                }
            }
            if (count($item['children'])) {
                OCCSVImportTagHandler::importStructList($item['children'], $newTag);
            }
        }
    }

    /**
     * @param SQLICSVRowSet $dataSource
     * @return array
     * @param $googleSpreadsheetUrl
     * @throws Exception
     */
    public static function getStructList(SQLICSVRowSet $dataSource, $googleSpreadsheetUrl, $recursion = 0)
    {
        $structList = array();

        $rawHeaders = $dataSource->getRawHeaders();
        $headers = $dataSource->getHeaders();

        if (!in_array('tag', $rawHeaders)){
            throw new Exception("Il csv non è valido");
        }

        $locale = eZLocale::currentLocaleCode();
        $defaultLanguage = eZContentLanguage::fetchByLocale($locale);

        foreach ($dataSource as $item) {
            $struct = array(
                'keyword' => null,
                'language' => null,
                'synonyms' => array(),
                'translations' => array(),
                'children' => array(),
            );
            foreach ($rawHeaders as $index => $rawHeader) {
                $field = $headers[$index];
                if ($rawHeader == 'tag') {
                    $struct['keyword'] = $item->{$field};
                    $struct['language'] = $defaultLanguage;
                } elseif (strpos($rawHeader, 'tag_') !== false) {
                    $currentLocale = str_replace('tag_', '', $rawHeader);
                    $currentLanguage = eZContentLanguage::fetchByLocale($currentLocale);
                    if (!$currentLanguage) {
                        throw new Exception("Language $currentLocale not found");
                    }
                    if ((string)$item->{$field} != ''){
                        $struct['translations'][] = array(
                            'keyword' => $item->{$field},
                            'language' => $currentLanguage
                        );
                    }
                } elseif (strpos($rawHeader, 'syn_') !== false) {
                    $currentLocale = substr($rawHeader, -6, 6);
                    $currentLanguage = eZContentLanguage::fetchByLocale($currentLocale);
                    if (!$currentLanguage) {
                        throw new Exception("Language $currentLocale not found");
                    }
                    if ((string)$item->{$field} != ''){
                        $struct['synonyms'][] = array(
                            'keyword' => $item->{$field},
                            'language' => $currentLanguage
                        );
                    }
                } elseif ($rawHeader == 'children' && (string)$item->{$field} !== '') {
                    $recursion++;
                    $childDataSource = OCCSVImportTagHandler::getSQLICSVRowSetFromGoogleSpreadsheetUrl($googleSpreadsheetUrl, $item->{$field}, $recursion);
                    $struct['children'] = OCCSVImportTagHandler::getStructList($childDataSource, $googleSpreadsheetUrl, $recursion);
                    $recursion--;
                }
            }
            $structList[] = $struct;
        }

        return $structList;
    }

    /**
     * @param string $keyword
     * @param eZTagsObject $parentTag
     * @param eZContentLanguage $language
     * @return eZTagsObject
     */
    public static function importTag($keyword, eZTagsObject $parentTag, eZContentLanguage $language)
    {
        if (eZTagsObject::exists(0, $keyword, $parentTag->attribute('id'))) {
            $tags = eZTagsObject::fetchList(array('keyword' => $keyword, 'parent_id' => $parentTag->attribute('id')));
            if (is_array($tags) && !empty($tags)) {
                return $tags[0];
            }
        }

        $db = eZDB::instance();
        $db->begin();

        $languageMask = eZContentLanguage::maskByLocale(array($language->attribute('locale')), true);

        $tag = new eZTagsObject(
            array(
                'parent_id' => $parentTag->attribute('id'),
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
                'keyword' => $keyword,
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

        return $tag;
    }

    /**
     * @param string $newKeyword
     * @param eZTagsObject $mainTag
     * @param eZContentLanguage $language
     * @param bool $alwaysAvailable
     * @return eZTagsObject
     */
    public static function addSynonym($newKeyword, eZTagsObject $mainTag, eZContentLanguage $language, $alwaysAvailable = true)
    {
        $parentTag = $mainTag->getParent(true);

        if (eZTagsObject::exists(0, $newKeyword, $parentTag->attribute('id'))) {
            $tags = eZTagsObject::fetchList(array('keyword' => $newKeyword, 'parent_id' => $parentTag->attribute('id')));
            if (is_array($tags) && !empty($tags)) {
                return $tags[0];
            }
        }

        $db = eZDB::instance();
        $db->begin();

        $languageMask = eZContentLanguage::maskByLocale(array($language->attribute('locale')), $alwaysAvailable);

        $tag = new eZTagsObject(
            array(
                'parent_id' => $mainTag->attribute('parent_id'),
                'main_tag_id' => $mainTag->attribute('id'),
                'depth' => $mainTag->attribute('depth'),
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

        if ($alwaysAvailable)
            $translation->setAttribute('language_id', $translation->attribute('language_id') + 1);

        $translation->store();

        $tag->setAttribute('path_string', $tag->attribute('path_string') . $tag->attribute('id') . '/');
        $tag->store();
        $tag->updateModified();

        $db->commit();

        /* Extended Hook */
        if (class_exists('ezpEvent', false)) {
            ezpEvent::getInstance()->filter('tag/add', array('tag' => $tag, 'parentTag' => $parentTag));
            ezpEvent::getInstance()->filter('tag/makesynonym', array('tag' => $tag, 'mainTag' => $mainTag));
        }

        return $tag;
    }

    public static function addTranslation($newKeyword, eZTagsObject $tag, eZContentLanguage $language, $setAsMainTranslation = false, $alwaysAvailable = true)
    {
        $parentTag = $tag->getParent(true);

        if (eZTagsObject::exists(0, $newKeyword, $parentTag->attribute('id'))) {
            $tags = eZTagsObject::fetchList(array('keyword' => $newKeyword, 'parent_id' => $parentTag->attribute('id')));
            if (is_array($tags) && !empty($tags)) {
                return $tags[0];
            }
        }

        $tagID = $tag->attribute('id');
        $tagTranslation = eZTagsKeyword::fetch($tag->attribute('id'), $language->attribute('locale'), true);
        if (!$tagTranslation instanceof eZTagsKeyword) {
            $tagTranslation = new eZTagsKeyword(array('keyword_id' => $tag->attribute('id'),
                'keyword' => '',
                'language_id' => $language->attribute('id'),
                'locale' => $language->attribute('locale'),
                'status' => eZTagsKeyword::STATUS_DRAFT));

            $tagTranslation->store();
            $tag->updateLanguageMask();
        }

        $tag = eZTagsObject::fetch($tagID, $language->attribute('locale'));

        $newParentID = $tag->attribute('parent_id');
        $newParentTag = eZTagsObject::fetchWithMainTranslation($newParentID);

        $updateDepth = false;
        $updatePathString = false;

        $db = eZDB::instance();
        $db->begin();

        $oldParentDepth = $tag->attribute('depth') - 1;
        $newParentDepth = $newParentTag instanceof eZTagsObject ? $newParentTag->attribute('depth') : 0;

        if ($oldParentDepth != $newParentDepth)
            $updateDepth = true;

        $oldParentTag = false;
        if ($tag->attribute('parent_id') != $newParentID) {
            $oldParentTag = $tag->getParent(true);
            if ($oldParentTag instanceof eZTagsObject)
                $oldParentTag->updateModified();

            $synonyms = $tag->getSynonyms(true);
            foreach ($synonyms as $synonym) {
                $synonym->setAttribute('parent_id', $newParentID);
                $synonym->store();
            }

            $updatePathString = true;
        }

        $tagTranslation->setAttribute('keyword', $newKeyword);
        $tagTranslation->setAttribute('status', eZTagsKeyword::STATUS_PUBLISHED);
        $tagTranslation->store();

        if ($setAsMainTranslation)
            $tag->updateMainTranslation($language->attribute('locale'));

        $tag->setAlwaysAvailable($alwaysAvailable);

        $tag->setAttribute('parent_id', $newParentID);
        $tag->store();

        if (class_exists('ezpEvent', false)) {
            ezpEvent::getInstance()->filter(
                'tag/edit',
                array(
                    'tag' => $tag,
                    'oldParentTag' => $oldParentTag,
                    'newParentTag' => $newParentTag,
                    'move' => $updatePathString
                )
            );
        }

        if ($updatePathString)
            $tag->updatePathString();

        if ($updateDepth)
            $tag->updateDepth();

        $tag->updateModified();
        $tag->registerSearchObjects();

        $db->commit();

        return $tag;
    }


}