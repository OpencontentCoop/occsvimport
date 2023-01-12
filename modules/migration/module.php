<?php
$Module = array('name' => 'OpenContent Migration');

$ViewList = array();
$ViewList['dashboard'] = array(
    'script' => 'dashboard.php',
    'functions' => ['migration'],
);

$FunctionList = array();
$FunctionList['migration'] = array();

