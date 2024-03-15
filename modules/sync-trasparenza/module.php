<?php
$Module = array('name' => 'OpenContent sync-trasparenza');

$ViewList = array();
$ViewList['dashboard'] = array(
    'script' => 'dashboard.php',
    'functions' => ['migration'],
    'params' => array('Action', 'ID'),
);

$FunctionList = array();
$FunctionList['migration'] = array();

