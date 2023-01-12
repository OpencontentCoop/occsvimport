<?php

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();
$options = $script->getOptions(
    "[root:][only_leaf]",
    "",
    [
        'root' => 'root tag id',
        'only_leaf' => 'only leaf tags'
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);
/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

function readTree($tagRepository, $name)
{
    $offset = 0;
    $limit = 100;
    $rootTag = $tagRepository->read($name, $offset, $limit)->jsonSerialize();

    if ($rootTag['hasChildren']) {
        while ($rootTag['childrenCount'] > count($rootTag['children'])) {
            $offset = $offset + $limit;
            $offsetRootTag = $tagRepository->read($name, $offset, $limit)->jsonSerialize();
            $rootTag['children'] = array_merge(
                $rootTag['children'],
                $offsetRootTag['children']
            );
        }

        foreach ($rootTag['children'] as $index => $child) {
            if ($child['hasChildren']) {
                $rootTag['children'][$index] = readTree($tagRepository, $child['id']);
            }
        }
    }

    return $rootTag;
}

$remoteHost = 'https://www.comune.bugliano.pi.it';
$rootTag = array_pop($parts);

$client = new \Opencontent\Installer\TagClient(
    $remoteHost,
    null,
    null,
    'tags_tree'
);

$remoteRoot = $client->readTree($options['root']);

$locale = 'ita-IT';
$list = [];
function readRicorsive($tag, $onlyChild)
{
    global $list, $locale;
    foreach ($tag['children'] as $child){
        if (!$onlyChild || ($onlyChild && !$child['hasChildren'])) {
            $list[] = $child['keywordTranslations'][$locale];
            foreach ($child['synonyms'] as $lang => $synonym) {
                if ($lang === $locale) {
                    $list[] = $synonym;
                }
            }
        }
        readRicorsive($child, $onlyChild);
    }
}

readRicorsive($remoteRoot, $options['only_leaf']);
foreach($list as $item) $cli->output($item);

$script->shutdown();