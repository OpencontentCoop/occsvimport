<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$ini = eZINI::instance( 'csvimport.ini' );
$module = $Params['Module'];

$NodeID = $http->variable( 'NodeID', false );
$ObjectID = $http->variable( 'ObjectID', false );

$object = eZContentObject::fetch( $ObjectID );

if ( !$object )
{
	return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

$classIdentifier = $object->attribute( 'class_identifier' );

if( $module->isCurrentAction( 'Export' ) )
{
	$storageTempDir = $ini->variable( 'Storage', 'StorageTempDir' );
	$storage = eZSys::storageDirectory() . eZSys::fileSeparator() . $storageTempDir;
	if ( !is_dir( $storage ) )
	{
		eZDir::mkdir( $storage, false, true );
	}
	else 
	{
		eZDir::recursiveDelete( $storage );
		eZDir::mkdir( $storage, false, true );
	}
	
	$exportFileName = $classIdentifier . '.csv';
	$exportFilePath = $storage . eZSys::fileSeparator() . $exportFileName;
	$exportFile = @fopen( $exportFilePath, "w" );
	if ( !$exportFile )
	{
		eZDebug::writeError( "Can not open output file for $classIdentifier class", __FILE__ );
		return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
	}
	
	$excludeList = $http->variable( 'AttributeExcludeList', array() ); 
	
	$objectFields = array();

	foreach ( $object->attribute( 'contentobject_attributes' ) as $attribute )
	{
		$name = $attribute->attribute( 'contentclass_attribute_identifier' );
		if ( !in_array( $name, $excludeList ) )
		{
			$objectFields[] = $attribute->attribute( 'contentclass_attribute_identifier' );
		}
	}
	
	$pseudoFields = array();
	$pseudoFieldsArray = PseudoField::fromArrayString( $http->variable( 'PseudoFields', array() ) );
	
	foreach ( $pseudoFieldsArray as $name => $key )
	{
		$pseudoFields[] = $key->attribute( 'string' );
	}
	
	$objectFields = array_merge( $objectFields, $pseudoFields );
	
	$objectData = array();
	
	foreach ( $object->attribute( 'contentobject_attributes' ) as $attribute )
	{
		$name = $attribute->attribute( 'contentclass_attribute_identifier' );
		if ( !in_array( $name, $excludeList ) )
		{
			switch ( $datatypeString = $attribute->attribute( 'data_type_string' ) )
		    {
		        case 'ezobjectrelation':
		        {
		        	$attributeStringContent = $attribute->content()->attribute('name');
		        } break;
		        
		        case 'ezobjectrelationlist':
		        {
		        	$attributeContent = $attribute->content();
		        	$relations = $attributeContent['relation_list'];
		        	
		        	$nodeNames = array();
		        	foreach ($relations as $relation)
		        	{
		        		$node = eZContentObjectTreeNode::fetch( $relation['node_id'] );
		        		if ( $node )
		        		{
		        			$nodeNames[] = $node->attribute( 'name' );
		        		}
		        	}
		        	$attributeStringContent = implode( ',', $nodeNames );
		        } break;
		    	
		    	case 'ezxmltext':
		        {
		            $text = str_replace( '"', "'", $attribute->content()->attribute('output')->outputText() );
		            $text = strip_tags( $text );
                    $text = str_replace( ';', ',', $text );
		            $text = str_replace( array("\n","\r"), "", $text );
		        	$attributeStringContent = $text;
		        } break;
		        
		    	case 'ezimage':
		        {
                    $imagePathParts = explode( '/', $attribute->toString() );
		            $imageFile = array_pop( $imagePathParts );
		            
                    if ( $imageFile !== '|' )
                        $attributeStringContent = $imageFile;
                    else
                        $attributeStringContent = '';
                    
		        } break;
		    
		        case 'ezbinaryfile':
		        case 'ezmedia':
		        {
		            $binaryData = explode( '|', $attribute->toString() );
		            $attributeStringContent = $binaryData[1];
		        } break;
		    
		        default:
		        	$attributeStringContent = $attribute->toString();
		        	break;
		    }
		
		    $objectData[] = $attributeStringContent;
		}
	}
    
    if ( count( $pseudoFields ) )
    {
        foreach( $pseudoFields as $p )
        {
            $objectData[] = '';
        }
    }
	
	if ( !fputcsv( $exportFile, $objectFields, $ini->variable( 'Settings', 'CSVDelimiter' ), $ini->variable( 'Settings', 'CSVEnclosure' ) ) )
	{
	    eZDebug::writeError( "Can not write to file", __FILE__ );
	    return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
	}
	
	if ( !fputcsv( $exportFile, $objectData, $ini->variable( 'Settings', 'CSVDelimiter' ), $ini->variable( 'Settings', 'CSVEnclosure' ) ) )
	{
	    eZDebug::writeError( "Can not write to file", __FILE__ );
	    return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
	}
	
	$archiveName = date( 'Ymd' ) . '_' . $classIdentifier . '.zip';
	$archivePath = $storage . eZSys::fileSeparator() . $archiveName;
	
	$archive = ezcArchive::open( $archivePath, ezcArchive::ZIP );
	$archive->append( $exportFilePath, $storage );
	
	eZFile::download( $archivePath, true, $archiveName );
	
}
else 
{

	$objectFields = array();
	
	$excludeList = $http->variable( 'AttributeExcludeList', array() ); 

	foreach ( $object->attribute( 'contentobject_attributes' ) as $attribute )
	{
		$objectFields[$attribute->attribute( 'contentclass_attribute_identifier' )] = $attribute->attribute( 'data_type_string' );
	}
	
	$pseudoFields = PseudoField::fromArrayString( $http->variable( 'PseudoFields', array() ) );
	
	if( $module->isCurrentAction( 'RemovePseudoField' ) )
	{
		$names = $http->variable( 'PseudoDelete', array() );
		foreach ( $names as $name )
		{
			if ( $key = array_key_exists( $name, $pseudoFields ) )
			{
				unset( $pseudoFields[$name] );
			}
		}
	}
	
	if( $module->isCurrentAction( 'AddPseudoField' ) )
	{
		$pseudoFieldName = $http->variable( 'PseudoFieldName', false );
		$pseudoFieldType = $http->variable( 'PseudoFieldType', false );
		$pseudoFieldLocation = $http->variable( 'PseudoFieldLocation', false );
		
		if ( $pseudoFieldLocation !== 0 && $pseudoFieldType !== 0 && !empty( $pseudoFieldName ) )
		{
			$pseudoFields[$pseudoFieldName] = new PseudoField( $pseudoFieldName, $pseudoFieldType, $pseudoFieldLocation );
		}
	}
	
	$tpl->setVariable( 'ObjectID', $ObjectID );
	$tpl->setVariable( 'exclude_list', $excludeList );
	$tpl->setVariable( 'pseudo_fields', $pseudoFields );
	$tpl->setVariable( 'attribute_fields', $objectFields );
	$tpl->setVariable( 'class_identifier', $classIdentifier );
	
	$Result = array();
	$Result['content'] = $tpl->fetch( "design:csvimport/export.tpl" );
	$Result['path'] = array( array( 'url' => '/csvimport/export/',
                                'text' => ezpI18n::tr( 'extension/occsvimport', "Esporta intestazioni CSV" ) ) );
}

class PseudoField
{
	public $name, $type, $location, $toString;
	
	function __construct( $name, $type, $location )
	{
		$this->name = $name;
		$this->type = $type;
		$this->location = $location;
		$this->string = $location . '_' . $type . '_' . $name;
	}
	
	public function attributes()
	{
		return array(
			'name',
			'type',
			'location',
			'string'
		);
	}
	
	public function attribute( $name )
	{
		if ( isset( $this->{$name} ) )
			return $this->{$name};
	}
	
	public function hasAttribute( $name )
	{
		if ( isset( $this->{$name} ) )
			return true;
		return false;
	}
	
	public static function fromArray( $array )
	{
		$return = array();
		foreach ( $array as $name => $values )
		{
			$return[$name] = new self( $name, $values['type'], $values['location'] );	
		}
		return $return;
	}
	
	public static function fromArrayString( $array )
	{
		$return = array();
		foreach ( $array as $name => $value )
		{
			$valueArray = explode( '_', $value );
			$location = array_shift( $valueArray );
			$type = array_shift( $valueArray );
			$return[$name] = new self( $name, $type, $location );	
		}
		return $return;
	}
	
}

?>