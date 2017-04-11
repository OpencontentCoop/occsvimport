<?php

class CSVImportHandlerLotto extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    private $handlerIdentifier = 'csvimporthandlerlotto';

    protected $rowIndex = 0;
    protected $rowCount;
    //protected $currentGUID;
    protected $options;
    protected $csvIni, $doc, $classIdentifier, $contentClass, $countRow = 0;

    //const REMOTE_IDENTIFIER = 'csvimport_';

    public function __construct( SQLIImportHandlerOptions $options = null )
    {
        parent::__construct( $options );
        $this->options = $options;
    }

    public function initialize()
    {
        $currentUser = eZUser::currentUser();
        $this->cli->warning( 'UserID #' . $currentUser->attribute( 'contentobject_id' ) );
        $this->csvIni = eZINI::instance( 'csvimport.ini' );
        $this->classIdentifier = $this->options->attribute( 'class_identifier' );
        $this->contentClass = eZContentClass::fetchByIdentifier( $this->classIdentifier );

        if ( !$this->contentClass )
        {
            $this->cli->error( "La class $this->classIdentifier non esiste." );
            die();
        }

        $csvOptions = new SQLICSVOptions( array(
            'csv_path'         => $this->options->attribute( 'csv_path' ),
            'delimiter'        => $this->options->attribute( 'delimiter' ),
            'enclosure'        => $this->options->attribute( 'enclosure' )
        ) );
        $this->doc = new SQLICSVDoc( $csvOptions );
        $this->doc->parse();
        $this->dataSource = $this->doc->rows;
    }

    public function getProcessLength()
    {
        return $this->dataSource->count();
    }

    public function getNextRow()
    {
        if( $this->dataSource->key() !== false )
        {
            $row = $this->dataSource->current();
            $this->dataSource->next();
        }
        else
        {
            $row = false;
        }
        return $row;
    }

    public function process( $row )
    {
        //$this->currentGUID = array_pop( explode( '/', $this->options->attribute( 'file_dir' ) ) ) . '_' . time() . '_' . $this->countRow++;

        $headers = $this->doc->rows->getHeaders();
        $rawHeaders = $this->doc->rows->getRawHeaders();

        //$this->currentGUID = $row->{$headers[0]} . '_' . $this->classIdentifier;

        $pseudoLocations = array_keys( $this->csvIni->variable( 'Settings', 'PseudoLocation' ) );

        $attributeArray = array();
        $attributeRepository = array();

        $attributes = $this->contentClass->fetchAttributes();
        foreach( $attributes as $attribute )
        {
            $attributeArray[$attribute->attribute( 'identifier' )] = $attribute->attribute( 'data_type_string' );
            $attributeRepository[$attribute->attribute( 'identifier' )] = $attribute;
        }

        $structuredFields = array();

        //$remoteID = substr( self::REMOTE_IDENTIFIER . $this->currentGUID, 0, 100 );


        $contentOptions = new SQLIContentOptions( array(
            'class_identifier'      => $this->classIdentifier
        ) );

        if($headers[0]=='remoteId' || $headers[0]=='cig'){
            $remoteID = $this->classIdentifier.'_'.$row->{$headers[0]};
            $contentOptions->__set('remote_id', $remoteID);
        }

        $content = SQLIContent::create( $contentOptions );

        $i = 0;
        foreach ( $headers as $key => $header )
        {
            
            $rawHeader = $rawHeaders[$key];
            $rawHeaderArray = explode('.', $rawHeader);

            //FIX per problematica array_key_exists che ritorna sempre false su prima colonna del CSV
            if($i==0){
                $rawHeader = $headers[0];
            }

            if ( array_key_exists( $rawHeader, $attributeArray ) )
            {
                switch( $attributeArray[$rawHeader] )
                {
                    case 'ezxmltext':
                    {
                        $content->fields->{$rawHeader} = $this->getRichContent( $row->{$header} );
                    }break;

                    case 'ezobjectrelationlist':
                    {
                        $contentClassAttributeContent = $attributeRepository[$rawHeader]->content();
                        $relationsNames = $row->{$header};
                        $content->fields->{$rawHeader} = $this->getRelations( $relationsNames, $contentClassAttributeContent['class_constraint_list'] );
                    }break;

                    case 'ezobjectrelation':
                    {
                        $contentClassAttributeContent = $attributeRepository[$rawHeader]->content();
                        $relationsNames = $row->{$header};
                        $content->fields->{$rawHeader} = $this->getRelations( $relationsNames, $contentClassAttributeContent['class_constraint_list'] );
                    }break;

                    case 'ezimage':
                    {
                        $fileAndName = explode( '|', $row->{$header} );
                        $file = $this->options->attribute( 'file_dir' ) . eZSys::fileSeparator() . OCCSVImportHandler::cleanFileName( $fileAndName[0] );
                        if( !is_dir( $file ) )
                        {
                            $name = '';
                            if ( isset( $fileAndName[1] ) )
                            {
                                $name = $fileAndName[1];
                            }

                            $fileHandler = eZClusterFileHandler::instance( $file );

                            if( $fileHandler->exists() )
                            {
                                //$this->cli->notice( $file . '|' . $name );
                                $content->fields->{$rawHeader} = $file . '|' . $name;
                            }
                            else
                            {
                                $this->cli->error( $file . ' non trovato' );
                            }
                        }

                    }break;

                    case 'ezbinaryfile':
                    case 'ezmedia':
                    {
                        $fileAndName = explode( '|', $row->{$header} );
                        $file = $this->options->attribute( 'file_dir' ) . eZSys::fileSeparator() . OCCSVImportHandler::cleanFileName( $fileAndName[0] );
                        if( !is_dir( $file ) )
                        {
                            $name = '';
                            if ( isset( $fileAndName[1] ) )
                            {
                                $name = $fileAndName[1];
                            }

                            $fileHandler = eZClusterFileHandler::instance( $file );

                            if( $fileHandler->exists() )
                            {
                                //$this->cli->notice( $file );
                                $content->fields->{$rawHeader} = $file;
                            }
                            else
                            {
                                $this->cli->error( $file . ' non trovato' );
                            }
                        }
                    }break;

                    case 'ezdate':
                    case 'ezdatetime':
                    {
                        $content->fields->{$rawHeader} = $this->getTimestamp( $row->{$header} );
                    }break;

                    case 'ezprice':
                    {
                       $value = $this->getPrice( $row->{$header} );

                       if($value!==false){
                            $content->fields->{$rawHeader} = $value;
                       }

                    }break;

                    case 'ezmatrix':
                    {

                        $content->fields->{$rawHeader} = $this->getMatrix($contentOptions['remote_id'], $header, $row->{$header});

                    }break;


                    default:
                    {
                        $content->fields->{$rawHeader} = $row->{$header};
                    }break;
                }
            }
            elseif ( count($rawHeaderArray) > 0 && array_key_exists( $rawHeaderArray[0], $attributeArray) )
            {
                switch( $attributeArray[$rawHeaderArray[0]] ) {
                    case 'ezmatrix':
                        $structuredFields[$rawHeaderArray[0]][$rawHeaderArray[1]] = $row->{$header};
                        break;

                    default:
                        break;
                }
            }
            else
            {
                $doAction = false;
                foreach ( $pseudoLocations as $pseudo )
                {
                    if ( strpos( $rawHeader, $pseudo ) !== false )
                    {
                        $files = explode( ',', $row->{$header} );
                        array_walk( $files, 'trim' );

                        if ( !empty( $files ) && $files[0] != '' )
                        {
                            $actionArray = explode( '_', $rawHeader );
                            $action = array_shift( $actionArray );
                            $doAction[$action][] = array(
                                'attribute' => array_shift( $actionArray ),
                                'class' => implode( '_', $actionArray ),
                                'values' => $files
                            );
                        }
                    }
                }
            }
            $i++;
        }

        // Ricompongo il campo con i valori della matrice salvati in $structuredFields
        foreach ($structuredFields as $k => $v)
        {
            $content->fields->{$k} = $this->getStructuredMatrix($k, $v, $contentOptions['remote_id']);
        }

        // Gestione partecipanti ed aggiudicatari
        // todo: modo migliore???
        $sFields = array('partecipanti', 'aggiudicatari');
        foreach ( $sFields as $s)
        {
            if ($row->{$s} == '2')
            {
                $content->fields->{$rawHeaders[array_search($s, $headers)]} = $this->prepareMatrixData($remoteID, $row, $s );
            }
            else
            {
                $content->fields->{$rawHeaders[array_search($s, $headers)]} = $this->preserveMatrixData($remoteID, $s );
            }

        }

        $content->addLocation( SQLILocation::fromNodeID( intval( $this->options->attribute( 'parent_node_id' ) ) ) );
        $publisher = SQLIContentPublisher::getInstance();
        $publisher->publish( $content );

        $newNodeID = $content->getRawContentObject()->attribute( 'main_node_id' );
        unset( $content );

        if ( $doAction !== false )
        {
            foreach ( $doAction as $action => $values )
            {
                $parameters = array(
                    'method' => 'make_' . $action,
                    'data' => $values,
                    'parent_node_id' => $this->options->attribute( 'parent_node_id' ),
                    'this_node_id' => $newNodeID,
                    'guid' => $this->currentGUID,
                    'file_dir' => $this->options->attribute( 'file_dir' )
                );
                call_user_func_array( array( 'OCCSVImportHandler', 'call' ), array( 'parameters' => $parameters ) );
            }
        }

    }

    public function getTimestamp( $string )
    {
        /*
         * Sostiutisco gli / con -
         * Dates in the m/d/y or d-m-y formats are disambiguated by looking at the separator between the various components:
         * if the separator is a slash (/), then the American m/d/y is assumed; whereas if the separator is a dash (-) or a dot (.),
         * then the European d-m-y format is assumed.
         */
        if (!is_numeric($string)) {
            $string = str_replace('/', '-', $string);
        }

        if (($timestamp = strtotime($string)) !== false) {
            return $timestamp;
        } else {
            // Approccio con regex?
            return time();
        }
    }

     public function getPrice( $string )
    {
        $priceComponent = explode( '|', $string );
        if ( is_array( $priceComponent ) && count( $priceComponent ) == 3 )
        {
            if($priceComponent[0]=='0' || trim($priceComponent[0])==''){
                return false;
            }else{
                return $string;
            }
        }

        $locale = eZLocale::instance();
        $data = $locale->internalCurrency( $string );

        if($data=='0' || $data==''){
            return false;
        }else{
            return $data . '|1|1';
        }
    }

    /**
     * @param $attributeIdentifier
     * @param $values
     * @param bool $remoteID
     * @return string
     */
    public function getStructuredMatrix( $attributeIdentifier, $values, $remoteID = false )
    {

        /** @var eZContentClassAttribute $attribute */
        $attribute = $this->contentClass->fetchAttributeByIdentifier($attributeIdentifier);
        $columns = $attribute->content()->attribute('columns');

        $sortedValues = array();
        foreach ($columns as $c)
        {
            $sortedValues [$c['identifier']] = isset( $values[$c['identifier']]) ? str_replace(array('&', '|'), '', $values[$c['identifier']]) : ' ';
        }

        $string = eZStringUtils::implodeStr( array_values($sortedValues), '|' );

        /*eZLog::write('--------------', 'lotto.log');
        eZLog::write(print_r($sortedValues, 1), 'lotto.log');
        eZLog::write('Structured: ' . $string, 'lotto.log');*/


        if ($remoteID && isset( $this->options['incremental'] ) && $this->options['incremental'] == 1)
        {
            $object = eZContentObject::fetchByRemoteID( $remoteID );
            if (!$object instanceof eZContentObject )
            {
                return $string;
            }

            $dataMap = $object->dataMap();
            if (!isset($dataMap[$attributeIdentifier]))
            {
                return $string;
            }
            $string =  $dataMap[$attributeIdentifier]->hasContent() ? $dataMap[$attributeIdentifier]->toString() . '&' . $string : $string;
        }

        /*eZLog::write('Aggregated: ' . $string, 'lotto.log');*/
        return $string;
    }

    /**
     * @param $remoteID
     * @param $attribute
     * @param $string
     * @return string
     */
    public function getMatrix( $remoteID, $attribute, $string )
    {
        if ( isset( $this->options['incremental'] ) && $this->options['incremental'] == 1)
        {
            $object = eZContentObject::fetchByRemoteID( $remoteID );
            if (! $object instanceof eZContentObject) /*(empty($object->MainNodeID) || $object->Published == 0)*/
            {
                return $string;
            }

            $dataMap = $object->dataMap();
            if (!isset($dataMap[$attribute]))
            {
                return $string;
            }
            return $dataMap[$attribute]->hasContent() ? $dataMap[$attribute]->toString() . '&' . $string : $string;
        }
        return $string;
    }

    public function getRelations( $relationsNames, $classes = array() )
    {
        if ( empty( $relationsNames ) )
        {
            return false;
        }
        $relations = array();
        $relationsNames = explode( ',', $relationsNames );
        array_walk( $relationsNames, 'trim' );

        $classesIDs = array();
        if ( !empty( $classes ) )
        {
            foreach ($classes as $class)
            {
                $contentClass = eZContentClass::fetchByIdentifier( $class );
                if ( $contentClass )
                {
                    $classesIDs[] = $contentClass->attribute( 'id' );
                }
            }
        }

        foreach( $relationsNames as $name )
        {
            $searchResult = eZSearch::search(
                trim( $name ),
                array(
                    'SearchContentClassID' => $classesIDs,
                    'SearchLimit' => 1
                )
            );
            if ( $searchResult['SearchCount'] > 0 )
            {
                $relations[] = $searchResult['SearchResult'][0]->attribute( 'contentobject_id' );
            }
        }
        if ( !empty( $relations ) )
        {
            return implode( '-', $relations );
        }
        return false;
    }


    public function prepareMatrixData( $remoteID, $row, $attributeIdentifier )
    {

        $data = array(
            'codice_fiscale'                => trim($row->{'invitatiCodiceFiscale'})!='' ? str_replace(array('&', '|'), '', $row->{'invitatiCodiceFiscale'} ) : '',
            'identificativo_fiscale_estero' => trim($row->{'invitatiIdentificativoFiscaleEstero'})!='' ? str_replace(array('&', '|'), '', $row->{'invitatiIdentificativoFiscaleEstero'} ) : '',
            'ragione_sociale'               => trim($row->{'invitatiRagioneSociale'})!=''? str_replace(array('&', '|'), '', $row->{'invitatiRagioneSociale'} ) : '',
            'id_gruppo'                     => '',
            'ruolo'                         => '',
        );

        eZLog::write(print_r($row, 1), 'lotto.log');
        eZLog::write(print_r($data, 1), 'lotto.log');
        eZLog::write(' --------- ', 'lotto.log');

        $dataToString = eZStringUtils::implodeStr( array_values($data), '|' );

        if ( isset( $this->options['incremental'] ) && $this->options['incremental'] == 1)
        {
            $object = eZContentObject::fetchByRemoteID( $remoteID );
            if (! $object instanceof eZContentObject) /*(empty($object->MainNodeID) || $object->Published == 0)*/
            {
                return $dataToString;
            }
            $dataMap = $object->dataMap();
            $dataToString = $dataMap[$attributeIdentifier]->hasContent() ? $dataMap[$attributeIdentifier]->toString() . '&' . $dataToString : $dataToString;
        }
        return $dataToString;
    }

    public function preserveMatrixData( $remoteID, $attributeIdentifier )
    {

        $object = eZContentObject::fetchByRemoteID( $remoteID );
        if (! $object instanceof eZContentObject) /*(empty($object->MainNodeID) || $object->Published == 0)*/
        {
            return '';
        }
        $dataMap = $object->dataMap();
        return $dataMap[$attributeIdentifier]->hasContent() ? $dataMap[$attributeIdentifier]->toString()  : '';
    }

    public function cleanup()
    {
        eZDir::recursiveDelete( $this->options->attribute( 'file_dir' ) );
        return;
    }

    public function getHandlerName()
    {
        return $this->options->attribute( 'name' );
    }

    public function getHandlerIdentifier()
    {
        return $this->handlerIdentifier;
    }

    public function getProgressionNotes()
    {
        return 'Current: ' . $this->currentGUID;
    }
}
