<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => "Create custom editor trsaprenza",
    'use-session' => false,
    'use-extensions' => true,
    'use-modules' => false,
    'debug-timing' => true,
]);

$optionsConfigList = [
//    [
//        'identifier' => 'languages',
//        'config' => '[languages:]',
//        'help' => 'Comma separates language code list, first is primary (default is: ita-IT,eng-GB)',
//    ],
];
$configs = array_column($optionsConfigList, 'config');
sort($configs);
$optionsHelp = [];
foreach ($optionsConfigList as $optionsConfig) {
    $optionsHelp[$optionsConfig['identifier']] = $optionsConfig['help'];
}
ksort($optionsHelp);

$script->startup();
$options = $script->getOptions(implode('', $configs), '', $optionsHelp);
$script->initialize();
$cli = eZCLI::instance();

$policies = [
    [
        'ModuleName' => 'content',
        'FunctionName' => 'edit',
        'Limitation' => [
            'Class' => eZContentClass::classIDByIdentifier('trasparenza'),
        ],
    ],
    [
        'ModuleName' => 'exportas',
        'FunctionName' => 'avpc',
        'Limitation' => [],
    ],
    [
        'ModuleName' => 'exportas',
        'FunctionName' => 'xml',
        'Limitation' => [],
    ],
    [
        'ModuleName' => 'exportas',
        'FunctionName' => 'csv',
        'Limitation' => [],
    ],
];
$datasetLotto = (int)eZContentClass::classIDByIdentifier('dataset_lotto');
$lotto = (int)eZContentClass::classIDByIdentifier('lotto');
if ($datasetLotto > 0 && $lotto > 0) {
    $policies[] = [
        'ModuleName' => 'content',
        'FunctionName' => 'create',
        'Limitation' => [
            'Class' => $lotto,
            'ParentClass' => $datasetLotto,
        ],
    ];
    foreach (['edit', 'remove'] as $function) {
        $policies[] = [
            'ModuleName' => 'content',
            'FunctionName' => $function,
            'Limitation' => [
                'Class' => $lotto,
            ],
        ];
    }
}
$tree = [
    '5399ef12f98766b90f1804e5d52afd75' => [
        'link',
        'document',
        'public_person',
        'nota_trasparenza',
        'pagina_trasparenza',
        'folder',
        'file',
    ],
    'ea1e3d5b55670e2e6261f095a20774a5' => [
        'concorso',
    ],
    '5840a8c6c04b7851b91645040d082b7b' => [
        'ente_controllato',
        'partecipazione_societaria',
    ],
    'c46fafba5730589c0b34a5fada7f3d07' => [
        'tasso_assenza',
    ],
    'a8ba76963914fcf6f41adfc6fc979232' => [
        'regolamento',
        'procedimento',
    ],
    '90b631e882ab0f966d03aababf3d9f15' => [
        'deliberazione',
        'determinazione',
        'sovvenzione_contributo',
    ],
    'c3dfcd9435bd41fb254e5d6c0993de78' => [
        'link',
        'disciplinare',
    ],
    'b7286a151f027977fa080f78817c895a' => [
        'conferimento_incarico',
    ],
    '280fb2af7960538a94d877f404f67a61' => [
        'decreto_sindacale',
        'ordinanza',
        'deliberazione',
    ],
    'b5df51b035ee30375db371af76c3d9fb' => [
        'consulenza',
    ],
    '5f467d7b4dc96527d061f8d8bf96168d' => [
        'regolamento',
    ],
    '47b6cf9a122fde963b54f1c79a3d033b' => [
        'regolamento',
        'piano_progetto',
    ],
    '5509d1b850b324ad247f35cdcf8974a4' => [
        'deliberazione',
        'determinazione',
        'piano_progetto',
        'decreto_sindacale',
    ],
    '23ecfe0ea9410b19a02b42055a6c659b' => [
        'deliberazione',
        'determinazione',
        'piano_progetto',
    ],
    'a9d59c0a74d2a7e03448e2bbea8e8458' => [
        'bilancio_di_previsione',
        'bilancio_di_settore',
        'rendiconto',
    ],
    'e28f316825dba873f9f0a4ca50d1c5f4' => [
        'determinazione',
    ],
    'b77effe1c84fcd44a88379b94ac0e402' => [
        'dataset_lotto',
    ],
];

$classGroup = eZContentClassGroup::fetchByName('Amministrazione trasparente');
$classList = eZContentClassClassGroup::fetchClassListByGroups(
    eZContentClass::VERSION_STATUS_DEFINED,
    [$classGroup->attribute('id')]
);
$activeClassIdentifiers = [
    'document' => eZContentClass::classIDByIdentifier('document'),
    'public_person' => eZContentClass::classIDByIdentifier('public_person'),
    'nota_trasparenza' => eZContentClass::classIDByIdentifier('nota_trasparenza'),
    'pagina_trasparenza' => eZContentClass::classIDByIdentifier('pagina_trasparenza'),
    'folder' => eZContentClass::classIDByIdentifier('folder'),
    'file' => eZContentClass::classIDByIdentifier('file'),
];
foreach ($classList as $class) {
    $activeClassIdentifiers[$class->attribute('identifier')] = $class->attribute('id');
}

foreach ($tree as $parentId => $classIdentifiers) {
    $missingClasses = array_diff($classIdentifiers, array_keys($activeClassIdentifiers));
    if (!empty($missingClasses)) {
        $classIdentifiers = array_diff($classIdentifiers, $missingClasses);
        if (empty($classIdentifiers)) {
            continue;
        }
    }

    $subtreeObject = eZContentObject::fetchByRemoteID($parentId);
    if (!$subtreeObject instanceof eZContentObject) {
        continue;
    }
    $subtreeNode = $subtreeObject->mainNode();
    if (!$subtreeNode instanceof eZContentObjectTreeNode) {
        continue;
    }

    $classLimitations = [];
    foreach ($classIdentifiers as $identifier) {
        if (isset($activeClassIdentifiers[$identifier])) {
            $classId = (int)$activeClassIdentifiers[$identifier];
            if ($classId > 0) {
                $classLimitations[] = $classId;
            }
        }
    }
    if (empty($classLimitations)) {
        continue;
    }

    foreach (['create', 'edit', 'remove'] as $function) {
        $policies[] = [
            'ModuleName' => 'content',
            'FunctionName' => $function,
            'Limitation' => [
                'Subtree' => [
                    $subtreeNode->attribute('path_string'),
                ],
                'Class' => $classLimitations,
            ],
        ];
    }
}

$roleName = 'Editor Trasparenza';
$role = eZRole::fetchByName($roleName);
if ($role instanceof eZRole) {
    $role->removePolicies();
} else {
    $role = eZRole::create($roleName);
    $role->store();
}
foreach ($policies as $policy) {
    $role->appendPolicy(
        $policy['ModuleName'],
        $policy['FunctionName'],
        $policy['Limitation']
    );
}
eZCache::clearByID(['user_info_cache']);

$script->shutdown();