<?php

$parentNodeID = $Params['ParentNodeID'];
$classIdentifier = $Params['ClassIdentifier'];

$data = (new OCCSVImportHandler)->fetchImportHistory($parentNodeID, $classIdentifier);

header( "HTTP/1.1 200 OK" );
header('Content-Type: application/json');
echo json_encode( $data );
//eZDisplayDebug();
eZExecution::cleanExit();
