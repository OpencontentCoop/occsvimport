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
$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$topics = eZContentClass::fetchByIdentifier('topic')->objectList();

$data = [];
foreach ($topics as $topic) {
    $parent = $topic->mainNode()->fetchParent();
    $parentParent = $parent->fetchParent();

    $data[$topic->attribute('remote_id')] = [];
    if ($parent->classIdentifier() === 'topic') {
        $data[$topic->attribute('remote_id')][] =
            $parent->object()->attribute('remote_id');
    }
    if ($parentParent->classIdentifier() === 'topic') {
        $data[$topic->attribute('remote_id')][] =
            $parentParent->object()->attribute('remote_id');
    }
}

$topics = [
    'topic_1_agricoltura_e_alimentazione' => [],
    'topic_1_ambiente' => [],
    'topic_1_popolazione_e_societa' => [],
    'topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica' => [],
    'topic_1_economia_e_finanze' => [],
    'topic_13' => [],
    'topic_1_scienza_e_tecnologia' => [],
    'topic_1_istruzione_cultura_e_sport' => [],
    'topic_1_territorio' => [],
    'topic_1_governo_e_settore_pubblico' => [],
    '18e6e1013c2999465c05b2ad41b364cf' => [],
    'topic_1_tematiche_internazionali' => [],
    '6b9adcbc7ca00d48590c2c0122d45873' => [],
    'topic_36' => [],
    'topic_2_agricoltura' => ['topic_1_agricoltura_e_alimentazione',],
    'topic_4' => ['topic_1_agricoltura_e_alimentazione',],
    'topic_2_foreste' => ['topic_1_agricoltura_e_alimentazione',],
    '2b67071267460acb651dab78c5937290' => ['topic_1_agricoltura_e_alimentazione',],
    '303df154b15e47f7986343c30ba57637' => ['topic_1_agricoltura_e_alimentazione',],
    'topic_2' => ['topic_1_ambiente',],
    '17722a57fb20ca1210125d2bdd8323ec' => ['topic_1_ambiente',],
    'topic_17' => ['topic_1_ambiente',],
    'topic_21' => ['topic_1_ambiente',],
    'topic_31' => ['topic_1_ambiente',],
    'topic_7' => ['topic_1_popolazione_e_societa',],
    'topic_2_impiego_nella_pa' => ['topic_1_popolazione_e_societa',],
    'topic_24' => ['topic_1_popolazione_e_societa',],
    'topic_16' => ['topic_1_popolazione_e_societa',],
    'topic_10' => ['topic_1_popolazione_e_societa',],
    '520467b8e456dd71a0df06701267ec62' => ['topic_1_popolazione_e_societa',],
    'a01a8345c4dd069454bd23f4a131b8ec' => ['topic_1_popolazione_e_societa',],
    '2585c8de2079feb3db29f85d3293de15' => ['topic_1_popolazione_e_societa',],
    'a600b3fb2825c2e6c688c4bde8c3f961' => ['topic_1_popolazione_e_societa',],
    'topic_2_volontariato' => ['topic_1_popolazione_e_societa',],
    '03247490a219ea48d754b8ffe0218429' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
    'topic_3_polizia' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
    '087e4fb8eb71d06eb6edbfe6aaee6ecf' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
    'topic_30' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
    '468a42f92ac4acd1543f830f630fe1dd' => ['topic_1_giustizia_sistema_giuridico_e_sicurezza_pubblica',],
    'topic_2_costi_bilanci_spese_dell_ente' => ['topic_1_economia_e_finanze',],
    '6df6d993b921ba5585b2c992b3ab4d5e' => ['topic_1_economia_e_finanze',],
    '9e9c6c0a4f25bad956def349e7ba7548' => ['topic_1_economia_e_finanze',],
    '91bc19e1e6201bcb0a246791bad4d888' => ['topic_1_economia_e_finanze',],
    'topic_2_tributi' => ['topic_1_economia_e_finanze',],
    '7a508ac8d8ede77941d382c758a99042' => ['topic_1_economia_e_finanze',],
    '9642f556d5f52562385a6ff83f342b78' => ['topic_1_economia_e_finanze',],
    '82f3daf9f172801c57f13d95000facfb' => ['topic_1_economia_e_finanze',],
    'f9646a846cd5c0cc94e576fe3250d502' => ['topic_1_economia_e_finanze',],
    '0dfc780404e1e86d3013c942f812e262' => ['topic_1_economia_e_finanze',],
    'topic_2_energia_rinnovabile' => ['topic_13',],
    'ff27f221bb1a1105319f758da98f1005' => ['topic_13',],
    'topic_2_risparmio_energetico' => ['topic_13',],
    '30308859ca4274ad266ae1b38666ae1e' => ['topic_1_scienza_e_tecnologia',],
    'topic_2_citta_intelligente' => ['topic_1_scienza_e_tecnologia',],
    'topic_20' => ['topic_1_scienza_e_tecnologia',],
    'dfa6ed0f6ceeddbc718c6280e53b9385' => ['topic_1_scienza_e_tecnologia',],
    'topic_2_ricerca' => ['topic_1_scienza_e_tecnologia',],
    'f85cb496baed09fae468f041ae275a37' => ['topic_1_istruzione_cultura_e_sport',],
    'f85de55bbafcd80eeed201a2d99d2351' => ['topic_1_istruzione_cultura_e_sport',],
    '6b101d7978a415884679d24a9afcec17' => ['topic_1_istruzione_cultura_e_sport',],
    'topic_1' => ['topic_1_territorio',],
    'topic_2_catasto' => ['topic_1_territorio',],
    'topic_3_lavori_pubblici' => ['topic_1_territorio',],
    'topic_39' => ['topic_1_territorio',],
    'topic_9' => ['topic_1_governo_e_settore_pubblico',],
    'topic_2_politica' => ['topic_1_governo_e_settore_pubblico',],
    'fe63739f7047ad84533d1055c9380444' => ['topic_1_governo_e_settore_pubblico',],
    'topic_26' => ['topic_1_governo_e_settore_pubblico',],
    'c60b70f9d0f4bcb8dd1c0ff34ac90d16' => ['topic_1_governo_e_settore_pubblico',],
    '0afb9385587fd6f9fdff39d9dd5e3142' => ['topic_1_governo_e_settore_pubblico',],
    'topic_2_vita_istituzionale' => ['topic_1_governo_e_settore_pubblico',],
    'topic_3_assistenza_agli_invalidi' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_3_assistenza_sociale' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_2_covid_19' => ['18e6e1013c2999465c05b2ad41b364cf',],
    '0b43588c71719126304e8aaae9e6438d' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_19' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_22' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_15' => ['18e6e1013c2999465c05b2ad41b364cf',],
    '2eb5ffa8cfbb467dee3026fd6ab7464c' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_3_protezione_delle_minoranze' => ['18e6e1013c2999465c05b2ad41b364cf',],
    'topic_2_comunita_europea' => ['topic_1_tematiche_internazionali',],
    '901ac93a6d4baaa901cc17ac155101ca' => ['topic_1_tematiche_internazionali',],
    'topic_2_gemellaggi' => ['topic_1_tematiche_internazionali',],
    'bd5192a42244b7e5a0dc4ac0830bce83' => ['6b9adcbc7ca00d48590c2c0122d45873',],
    'topic_32' => ['6b9adcbc7ca00d48590c2c0122d45873',],
    '158a858d0fe4b8a9d0fe5d50c3605cb2' => ['6b9adcbc7ca00d48590c2c0122d45873',],
    '6c62b4913df2ea2b739dae5cc04f70da' => ['6b9adcbc7ca00d48590c2c0122d45873',],
    'd5ffcb9ed91fa49cfdc42a7ee6fb4d81' => ['topic_36',],
    'topic_6' => ['topic_36',],
    '64e2943f2b139bbe825a1ec700cb24fc' => ['topic_36',],
    'topic_37' => ['topic_36',],
    'topic_35' => ['topic_36',],
    'abc9ab5b22d6f4a06ba477b75dafd075' => ['topic_36',],
    'fdfafe70890f994101c6a56d9928ef69' => ['topic_36',],
];

$script->shutdown();
