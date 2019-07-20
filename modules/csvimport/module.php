<?php
$Module = array('name' => 'OpenContent CSV Import');

$ViewList = array();
$ViewList['import'] = array(
    'script' => 'import.php',
    'single_post_actions' => array(
        'ImportButton' => 'Import',
        'UploadFileButton' => 'UploadFile',
        'SelectGoogleSpreadsheetButton' => 'SelectGoogleSpreadsheet'
    ),
    'params' => array('ParentNodeID')
);
$ViewList['export'] = array(
    'script' => 'export.php',
    'single_post_actions' => array(
        'ExportButton' => 'Export',
        'AddPseudoFieldButton' => 'AddPseudoField',
        'RemovePseudoFieldButton' => 'RemovePseudoField'
    ),
    'params' => array('NodeID')
);
$ViewList['configure'] = array(
    'script' => 'configure.php',
    'params' => array('ParentNodeID','ImportIdentifier'),
    'single_post_actions' => array(
        'UpdateGoogleSpreadsheetButton' => 'UpdateGoogleSpreadsheet',
        'ImportGoogleSpreadsheetButton' => 'ImportGoogleSpreadsheet',
    )
);
$ViewList['import_tag'] = array(
    'script' => 'import_tag.php',
    'default_navigation_part' => 'eztagsnavigationpart',
    'single_post_actions' => array(
        'ImportButton' => 'Import',        
        'SelectGoogleSpreadsheetButton' => 'SelectGoogleSpreadsheet',        
        'ImportGoogleSpreadsheetButton' => 'ImportGoogleSpreadsheet'
    ),
    'params' => array('ParentTagID')
);
$FunctionList = array();
$FunctionList['import'] = array();
$FunctionList['export'] = array();
$FunctionList['import_tag'] = array();

?>
