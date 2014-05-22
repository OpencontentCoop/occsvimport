<?php

include( 'autoload.php' );

$script = eZScript::instance(
    array(
        "description" => "Clean temp csv import files.",
        "use-session" => false,
        "use-modules" => false,
        "use-extensions" => true,
    )
);

$script->startup();
$options = $script->getOptions(
    "[n]",
    "",
    array(
        "-q" => "Quiet mode",
        "n" => "Do not wait"
    )
);

$script->initialize();

$cli = eZCLI::instance();

$helper = new OCCSVImportHandler();
$storageDir = $helper->ini->variable( 'Storage', 'StorageZipDir' );
$storageTemp = $helper->ini->variable( 'Storage', 'StorageTempDir' );
$storage = eZSys::cacheDirectory() . eZSys::fileSeparator() . $storageDir . eZSys::fileSeparator();
$storageTmp = eZSys::cacheDirectory() . eZSys::fileSeparator() . $storageTemp . eZSys::fileSeparator();


$cli->warning( "This cleanup script will remove any file from $storage and $storageTemp" );
if ( !isset( $options['n'] ) )
{    
    $cli->warning();
    $cli->warning( "IT IS YOUR RESPONSABILITY TO TAKE CARE THAT NO ITEMS REMAINS IN TRASH BEFORE RUNNING THIS SCRIPT." );
    $cli->warning();
    $cli->warning( "You have 30 seconds to break the script (press Ctrl-C)." );
    sleep( 30 );
}

deleteDir( $storage );
deleteDir( $storageTmp );

function deleteDir( $dir )
{
    global $cli;
    $fileList = array();
    eZDir::recursiveList( $dir, $dir, $fileList );
    foreach( $fileList as $file )
    {
        $filepath = $file['path'] . eZSys::fileSeparator() . $file['name'];        
        $cli->output( 'Remove ' . $filepath );
        if ( $file['type'] == 'file' )
        {        
            $item = eZClusterFileHandler::instance( $filepath );
            if ( $item->exists() )
            {
                $cli->output( 'Remove ' . $filepath );
                $item->delete();
                $item->purge();
            }        
        }
        else
        {
            deleteDir( $filepath );
        }
    }
}

$script->shutdown();
?>