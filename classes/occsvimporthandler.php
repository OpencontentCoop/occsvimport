<?php 

class OCCSVImportHandler
{
	const ERROR_DOCNOTSUPPORTED = 1;
	
	public $ini, $mime_data, $file_path, $file_dir, $data_source, $import_options;
	public $files = array(); 
	
	function __construct()
	{
		$this->ini = eZINI::instance( 'csvimport.ini' );
	}
	
	public static function cleanFileName( $name )
	{
		return urlencode( trim( $name ) ); 
	}
	
	public function inizializeFromFile( $filename )
	{
		if ( !file_exists( $filename ) )
        {
            return false;
        }
        
		$storageDir = $this->ini->variable( 'Storage', 'StorageZipDir' );
		$storage = eZSys::storageDirectory() . eZSys::fileSeparator() . $storageDir;
		if ( !is_dir( $storage ) )
		{
			eZDir::mkdir( $storage, false, true );
		}
		
		$originalFilename = basename( $filename );
        $fileExtension = eZFile::suffix( $originalFilename );
        
        if ( $fileExtension !== 'zip' )
        {
        	return false;
        }
        
        $mimeData = eZMimeType::findByFileContents( $filename );        	
        if ( !$mimeData['is_valid'] && $originalFilename != $filename)
        {
            $mimeData = eZMimeType::findByFileContents( $originalFilename );
        }
       
        $tmpName = explode( '.', $originalFilename );
        eZMimeType::changeBaseName( $mimeData, $tmpName[0] );
        eZMimeType::changeDirectoryPath( $mimeData, $storage );
        $this->mime_data = $mimeData;
        if ( eZFileHandler::copy( $filename, $mimeData['url'] ) )
        {
	        $this->file_path = $this->mime_data['url'];
	        $this->file_dir = eZDir::dirpath( $this->file_path ) . eZSys::fileSeparator() .  $this->mime_data['basename'];
			if ( !is_dir( $this->file_dir ) )
			{
				eZDir::mkdir( $storage, false, true );
			}
			$this->extractZip();
        	if ( !$this->getCSVFile() )
			{
				eZDir::recursiveDelete( $this->file_dir );
				return false;
			}
	        return true;
        }
        return false;
	}
	
	public function inizializeFromHTTPFile( $httpFile )
	{
		$storageDir = $this->ini->variable( 'Storage', 'StorageZipDir' );
		$storage = eZSys::storageDirectory() . eZSys::fileSeparator() . $storageDir;
		if ( !is_dir( $storage ) )
		{
			eZDir::mkdir( $storage, false, true );
		}
		
        $fileExtension = eZFile::suffix( $httpFile->attribute( 'original_filename' ) );
        
        if ( $fileExtension !== 'zip' )
        {
        	return false;
        }
        
        $mimeData = eZMimeType::findByFileContents( $httpFile->attribute( 'filename' ) );        	
        if ( !$mimeData['is_valid'] )
        {
            $mimeData = eZMimeType::findByName( $httpFile->attribute( 'mime_type' ) );
            if ( !$mimeData['is_valid'] )
            {
                $mimeData = eZMimeType::findByURL( $httpFile->attribute( 'original_filename' ) );
        	}
        }
       
        $tmpName = explode( '.', $httpFile->attribute('original_filename') );
        eZMimeType::changeBaseName( $mimeData, $tmpName[0] );
        eZMimeType::changeDirectoryPath( $mimeData, $storage );
        $this->mime_data = $mimeData;
        if ( $httpFile->store( $storageDir, $fileExtension, $this->mime_data ) )
        {
	        $this->file_path = $this->mime_data['url'];
	        $this->file_dir = eZDir::dirpath( $this->file_path ) . eZSys::fileSeparator() .  $this->mime_data['basename'];
			if ( !is_dir( $this->file_dir ) )
			{
				eZDir::mkdir( $storage, false, true );
			}
			$this->extractZip();
        	if ( !$this->getCSVFile() )
			{
				eZDir::recursiveDelete( $this->file_dir );
				return false;
			}
	        return true;
        }
        return false;
	}
	
	public function extractZip()
	{
		$archive = ezcArchive::open( $this->file_path );
		while ( $archive->valid() )
		{
			$entry = $archive->current();
			$archive->extractCurrent( $this->file_dir );
			$this->addFile( $entry->getPath() );
			$archive->next();
		}
		
		$fileList = array();
		eZDir::recursiveList( $this->file_dir, $this->file_dir, $fileList);
		
		foreach ( $fileList as $file )
		{
			if ( $file['type'] == 'file' )
			{
				rename( 
					$this->file_dir . eZSys::fileSeparator() . $file['name'],
					$this->file_dir . eZSys::fileSeparator() . OCCSVImportHandler::cleanFileName( $file['name'] ) 
				);
			}
		}
		
	}
	
	private function addFile( $fileName )
	{
		$this->files[ basename( $fileName ) ] = $this->file_dir .  eZSys::fileSeparator() . $fileName;
	}
	
	public function getCSVFile( $withSuffix = true )
	{
		foreach ( $this->files as $basename => $path )
		{
			if ( eZFile::suffix( $basename ) == 'csv' )
			{
				if ( $withSuffix )
					return $path;
				else 
				{
					return str_replace( '.' . eZFile::suffix( $basename ) , '', $basename  );
				}
			}
		}
		return false;
	}
	
	public function setImportOption( $key, $value )
	{
		$this->import_options[$key] = $value;
		//$this->importOptions = new SQLIImportHandlerOptions();
		return $this->import_options;
	}
	
	public function addImport()
	{		
		$this->setImportOption( 'csv_path', $this->getCSVFile() );
		$this->setImportOption( 'delimiter', $this->ini->variable( 'Settings', 'CSVDelimiter' ) );
		$this->setImportOption( 'enclosure', $this->ini->variable( 'Settings', 'CSVEnclosure' ) );
		$this->setImportOption( 'file_dir', $this->file_dir );
		$this->setImportOption( 'class_identifier', $this->getCSVFile( false ) );

		$pendingImport = new SQLIImportItem( array(
            'handler'               => $this->ini->variable( 'Settings', 'SQLIImportHandler' ),
            'user_id'               => eZUser::currentUserID()
        ) );
        $pendingImport->setAttribute( 'options', new SQLIImportHandlerOptions( $this->import_options ) );
        $pendingImport->store();
	}
	
	public function readCSV()
	{	
		//todo
	}
	
	public static function call( $parameters )
	{
		$cli = eZCLI::instance();
		if ( isset( $parameters['method'] ) )
		{
			$self = new OCCSVImportHandler();
			$method = trim( $parameters['method'] );
	        if ( method_exists( $self, $method ) )
	        {
	            return call_user_func_array( array( $self, $method ), array( 'parameters' => $parameters ) );
	        }
		}
		$cli->error( $parameters['method'] . ' non trovato.' );
        return false;
	}
	
	public function make_csvimportchildren( $parameters )
	{
		$cli = eZCLI::instance();
		
		$data = $parameters['data'];
        print_r($parameters);
		$parentNodeID = (integer) $parameters['this_node_id'];
		foreach ( $data as $d )
		{
			$classIdentifier = trim( $d['class'] );
			
			$class = eZContentClass::fetchByIdentifier( $classIdentifier );
			$attributes = $class->fetchAttributes();
	    	foreach( $attributes as $attribute )
	    	{
	    		if ( $attribute->attribute( 'data_type_string' ) == trim( $d['attribute'] ) )
	    		{
	    			$field = $attribute->attribute( 'identifier' );
	    		}
	    	}
			$objects = $d['values'];
		
			foreach ( $objects as $index => $object )
			{
				$contentOptions = new SQLIContentOptions( array(
		            'class_identifier'      => $classIdentifier,
		            'remote_id'             => $parameters['guid'] . '_children_' . $index
		        ) );
		        
		        $object = trim( $object );
		        $nameArray = explode( '|', $object );
		        
		        $fileName = $parameters['file_dir'] . eZSys::fileSeparator() . OCCSVImportHandler::cleanFileName( $nameArray[0] );

		        if ( file_exists( $fileName ) )
		        {
			        $content = SQLIContent::create( $contentOptions );
			        $content->fields->name = ( isset( $nameArray[1] ) && !empty( $nameArray[1] ) ) ? $nameArray[1] : $nameArray[0];
			        $content->fields->{$field} = $fileName;
			        $content->addLocation( SQLILocation::fromNodeID( $parentNodeID ) );
			        $publisher = SQLIContentPublisher::getInstance();
			        $publisher->publish( $content );
			        unset( $content );
		        }
			}
		}
	}
	
}
?>