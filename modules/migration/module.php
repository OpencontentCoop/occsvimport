<?php
$Module = array('name' => 'OpenContent Migration');

$ViewList = array();
$ViewList['dashboard'] = array(
    'script' => 'dashboard.php',
    'functions' => ['migration'],
    'params' => array('Action', 'ID'),
);

$FunctionList = array();
$FunctionList['migration'] = array();

