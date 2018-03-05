<?php

class CSVImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
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

        //$remoteID = substr( self::REMOTE_IDENTIFIER . $this->currentGUID, 0, 100 );


        $contentOptions = new SQLIContentOptions( array(
            'class_identifier'      => $this->classIdentifier

        ) );

        if($headers[0]=='remoteId'){
            $remoteID = $this->classIdentifier.'_'.$row->{$headers[0]};
            $contentOptions->__set('remote_id', $remoteID);
        }

        $content = SQLIContent::create( $contentOptions );

        $i=0;
        foreach ( $headers as $key => $header )
        {

            $rawHeader = $rawHeaders[$key];

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

                    case 'ocmultibinary':
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
                        $content->fields->{$rawHeader} = $this->getPrice( $row->{$header} );
                    }break;

                    case 'ezmatrix':
                    {
                        if (isset( $this->options['incremental'] ) && $this->options['incremental'] == 1)
                        {
                            $content->fields->{$rawHeader} = $this->getIncrementalMatrix($contentOptions['remote_id'], $header, $row->{$header});
                        }
                        else
                        {
                            $content->fields->{$rawHeader} = $row->{$header};
                        }
                    }break;


                    default:
                    {
                        $content->fields->{$rawHeader} = $row->{$header};
                    }break;
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
            /*
            $data_ora = explode( ' ', $string );
            $data = $data_ora[0];
            $ora = isset( $data_ora[1] ) ? $data_ora[1] : '00:00';
            $giorno_mese_anno = explode( '/', $data );
            $ore_minuti = explode( ':', $ora );

            $ore = $ore_minuti[0];
            $minuti = $ore_minuti[1];
            $giorno = $giorno_mese_anno[0];
            if ( !isset( $giorno_mese_anno[1] ) )
            {
                return time();
            }
            $mese = $giorno_mese_anno[1];
            $anno = $giorno_mese_anno[2];
            return mktime( $ore, $minuti, 0, $mese, $giorno, $anno );
            */
        }
    }

    public function getPrice( $string )
    {
        $priceComponent = explode( '|', $string );
        if ( is_array( $priceComponent )  && count( $priceComponent ) == 3 )
        {
            return $string;
        }
        $locale = eZLocale::instance();
        $data = $locale->internalCurrency( $string );
        return $data . '|1|1';

    }

    public function getIncrementalMatrix( $remoteID, $attribute, $string )
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
        return 'csvimportahandler';
    }

    public function getProgressionNotes()
    {
        return 'Current: ' . $this->currentGUID;
    }
}
