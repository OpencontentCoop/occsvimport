<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$tpl->setVariable( 'error', false );
$ini = eZINI::instance( 'csvimport.ini' );
$module = $Params['Module'];

function makeErrorArray( $num, $msg )
{
    return array( 'number' => $num, 'message' => $msg );
}


$NodeID = $http->variable( 'NodeID', false );
$ObjectID = $http->variable( 'ObjectID', false );

$node = eZContentObjectTreeNode::fetch( $NodeID );

if ( !$node )
{
	return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$object = eZContentObject::fetch( $ObjectID );

if ( !$object )
{
	return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

if ( $module->isCurrentAction( 'UploadFile' ) )
{
	$httpFileName = 'ImportFile';
	if ( eZHTTPFile::canFetch( $httpFileName ) )
    {
		$httpFile = eZHTTPFile::fetch( $httpFileName );
        if ( $httpFile )
        {
        	$handler = new OCCSVImportHandler;
        	$isValid = $handler->inizializeFromHTTPFile( $httpFile );
        	if ( !$isValid )
        	{
        		$tpl->setVariable( 'error', makeErrorArray(
        													OCCSVImportHandler::ERROR_DOCNOTSUPPORTED,
        													'Tipo di documento non supportato'
        												  ) );
        	}
        	else
        	{
        		$handler->setImportOption( 'parent_node_id', $NodeID );
        		$handler->setImportOption( 'name', 'Importazione in ' . $node->attribute( 'name' ) );
        		$handler->addImport();
        		$module->redirectTo( 'sqliimport/list' );
        	}
        }
    }
	
	
}

$tpl->setVariable( 'ObjectID', $ObjectID );
$tpl->setVariable( 'node', $node );
$tpl->setVariable( 'NodeID', $NodeID );

$Result = array();
$Result['content'] = $tpl->fetch( "design:csvimport/import.tpl" );
$Result['path'] = array( array( 'url' => '/csvimport/import/',
                                'text' => ezpI18n::tr( 'extension/occsvimport', "Importa CSV" ) ) );

?>