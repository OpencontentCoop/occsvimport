<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => ("Assign section to subtree"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions(
    '[class:]',
    '',
    [
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);
$cli = eZClI::instance();
try {
    $user = eZUser::fetchByName('admin');
    eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

    $class = $options['class'] ?? 'none';
    if (eZContentClass::fetchByIdentifier($class)) {
        $count = (int)eZContentObjectTreeNode::subTreeCountByNodeID([
            'MainNodeOnly' => true,
            'ClassFilterType' => 'include',
            'ClassFilterArray' => [$class],
        ], 1);

        if ($count) {
            $cli->output($count);
        }
    }
    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown($errCode, $e->getMessage());
}
