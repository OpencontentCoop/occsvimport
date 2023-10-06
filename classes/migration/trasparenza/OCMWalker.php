<?php

class OCMWalker
{
    private $recursionLevel = 0;

    private $maxRecursionLevel = 0;

    private $countByClassIdentifier = [];

    private $callback;

    public function __construct($callback = null)
    {
        $this->callback = $callback;
    }

    public function walk(eZContentObjectTreeNode $root, $parent = null)
    {
        /** @var eZContentObjectTreeNode $child */
        foreach ($root->children() as $child) {
            if (!in_array($child->object()->remoteID(), self::$validIdList)) {
                $this->collect($child, $root);
            }else{
                $this->cli()->warning(
                    $this->pad($this->recursionLevel) . ' ' . $child->attribute('class_identifier') . ' ' .$child->attribute('name')
                );
            }
            $this->recursionLevel++;
            $this->maxRecursionLevel++;
            $this->walk($child, $root);
            $this->recursionLevel--;
        }
    }

    private function collect($node, $parent)
    {
        if (!isset($this->countByClassIdentifier[$node->attribute('class_identifier')])){
            $this->countByClassIdentifier[$node->attribute('class_identifier')] = 0;
        }
        $this->countByClassIdentifier[$node->attribute('class_identifier')]++;
        $this->cli()->output(
            $this->pad($this->recursionLevel) . ' ' . $node->attribute('class_identifier') . ' ' . $node->attribute('name')
        );
        if (is_callable($this->callback)){
            call_user_func($this->callback, $node, $parent);
        }
    }

    private function pad($int)
    {
        return $this->recursionLevel > 0 ? str_pad(' ', $this->recursionLevel * 2, "    ", STR_PAD_LEFT) . '|- ' : '';
    }

    private function cli()
    {
        return eZCLI::instance();
    }

    public function getStats()
    {
        return [
            'objects' => array_sum($this->countByClassIdentifier),
            'classes' => $this->countByClassIdentifier,
            'max_recursion' => $this->maxRecursionLevel,
        ];
    }

    private static $validIdList = [
        'eeed7c3f9be265ec5a18d40baa863dc3',
        '4223c7aea578a5e5beabc5369deb3cb3',
        '14afa066db4fdab8e16d72adeab3b9ee',
        '8bdf6887111dd235013a6c9692e7f27c',
        '15779d5375c4713afd09021a660d23cf',
        '8db92462dde2805cee13889a3895b267',
        '0131bfcefdbfa1d667604fc4ad5bdc3b',
        '44e8e0e8e272132c9d3c34c7d35be283',
        'bb7569672cb76895979464a68b19ab30',
        'add03339bd66c565a2b95b5ae26d46e5',
        'c922f037d6da5d052e7b70c1ce3f82a2',
        'df504e9bd778ee7d4a9f4b1039ca0319',
        'e2bf2c1845a1f01bccff822dee58c05a',
        'a4b8811918c3b93d2abe9464418653d7',
        '032cc6bb76b86bb4ea45e7ca3b2604b9',
        '5509d1b850b324ad247f35cdcf8974a4',
        'a687348507c9cf2522a6ff0317889ef8',
        'a98f90b19fcb915e4dc7459209e0a5b2',
        '2d98ea7b89322a2e49b68a6f38baefa0',
        'a8ba76963914fcf6f41adfc6fc979232',
        '7ed046186b89dd1c329001d9f24cb43f',
        'caf2ef64f0da331b8895ffd2c8032ad6',
        '5ca48270d496ad3090e746e54fe9826d',
        'b77effe1c84fcd44a88379b94ac0e402',
        '1fb873030fa6a205c799e79985fb90ea',
        'cad5271ec508ba1591b737749b774bd4',
        '5cbad60a8adb4a40e7e8a6b2900d9ffb',
        'ab203526bbe9eb8b8fb82c4c48b43f0b',
        'd936dcdabc71967dac70995b1dcd0ecf',
        'a9d59c0a74d2a7e03448e2bbea8e8458',
        '8866fd5f4b6c38137922770c28079ab9',
        'd6c1de388cd6fb343c2ef4950e9d3d50',
        '6153075fa1d64bc684ebd866e3b690cd',
        '583cd446c1978fdab33108b83ae9eb71',
        'd20a1b517d9c0cba06af6b6b345f6c0e',
        'fef099acb76c58c50c820c95882601f2',
        'd774ce1cf50f57929cb1756ae2f5fa49',
        '0fda52626f36e65ee7632dd90fa72c86',
        '2d50574a2347cd161456505aab978fe3',
        '4a8a56a94e7454a8313f2292c64313cc',
        'c3dfcd9435bd41fb254e5d6c0993de78',
        'e3272af2c0322bd9cef702214a0f7b4b',
        '67045e53aedf0fd398627d63f46182c3',
        '23ecfe0ea9410b19a02b42055a6c659b',
        'af451ee95234c2c70bde2ccf858c80a0',
        'd1607bcdbfe95f31e7c3ea7f8d046299',
        'b8ba159b3f102091f5f2c41ef32c190b',
        '367f45c867601ef7e8a3d2c125b287ca',
        '5a2189cac55adf79ddfee35336e796fa',
        '1b7517be4dde8b70eaf65f69e5c46311',
        'b9f7704b70efdaa6ffcc13929008bd1e',
        'a591577daf23075da770ed3485894ff0',
        'a070d59a69ce0f4dfade61241b79af43',
        '01268e2ede40faaa233c64acc189c5cc',
        '54a1aa186be94982f1416bf013f782db',
        'd4836df7b45710db1bf15b35c5482086',
        'ae441f5d2f78bf88f0b3e39a36743bdd',
        'f9e7ba61d625473476c8ad90904c7bfe',
        '9e88815a407de17944a27fb35cde795e',
        '2803f003c03c4940a1d01bac9dee285c',
        '8c09c804031d478149554c9aa23d3ca7',
        '9676707d6712259e25a40c16dae3a763',
        'ff175c1aad1509d6681d2471945cddba',
        '167c05fa76f7a38fb27c9d0c23a1da42',
        '47dd6ba753c3ad8963279dea8d8d1835',
        'f4d74dd77cfb92614869144c95daae3a',
        'd1ec49a3082241a7cf0499e2d47c4d13',
        'f34aaeff08a34060240f828c2f87a1c7',
        '574c2acad7e2e26db86aeeae6e920441',
        '114044726c7d5f600867b16e6ea48560',
        '214e625b575f2546b314d0996d02fdff',
        'b9f4596045334c1b6851e5428f39e335',
        '5ae3313d44d8d2b6842ea980f3970a53',
        '36fec001e36199c219fc4da229e2d694',
        '66bd19a29880fec026237d4c9fa71162',
        '3103431f27a16cdff44fc24f897e69da',
        '0d2dfc4f599bef991f37e1e043d62032',
        'e5c935557bd7f5e741e18da9b420264f',
        'b7286a151f027977fa080f78817c895a',
        'fb43c9d8bddd1767fa6dfd6955c66e07',
        'b29995a09b91de5e7164d2d61e5a4e3a',
        '154a8d7d190b4e49e2796384c0732712',
        '59e6dc22313f2f89584d90c0d67e2b4e',
        'c46fafba5730589c0b34a5fada7f3d07',
        'efc995388bebdd304f19eef17aab7e0d',
        '9eed77856255692eca75cdb849540c23',
        'e28f316825dba873f9f0a4ca50d1c5f4',
        '280fb2af7960538a94d877f404f67a61',
        '0ae045e0bea2df4ff266ec284af1f9b9',
        '2355030a5852f24d60cb9e93c13e002b',
        '0743d3983ddbb1735f3afbe524f53cf9',
        '4a9b8cea1574995194c55dfcb280fc58',
        '90a35849b84c43d797499359415aec37',
        '90b631e882ab0f966d03aababf3d9f15',
        '5f467d7b4dc96527d061f8d8bf96168d',
        '63e99a08f177d087041b826ac9f028a5',
        '2b1dab5e3f3593cccf437df119eca001',
        'ea1e3d5b55670e2e6261f095a20774a5',
        '80aeb1e135caf05f9fca3cd1430a414f',
        'f5bc287d9f9d31e7bb098d9d5e9e2458',
        'd6ad94ccfef19c49a43fef6cef576d21',
        'b5df51b035ee30375db371af76c3d9fb',
        'fc18dc0947cce81ed94b4f5228572fc1',
        '58a88bb4cb1c02c0a8803ebb8823583c',
        'c37e6f3eeceb628684a0c9eb258d547e',
        '5840a8c6c04b7851b91645040d082b7b',
        'a8f469ee3110143ca0ecf25d3a08ee8c',
        '31d5fdf590e59fcb54df90bb1bd99cc0',
        '4cc49ed080b321c1109038476dc1101c',
        'a7f7ed3a1fb71a36efaf36da950f2243',
        '9b67f9a2a890627756dc98faf63936d9',
        '455cbf5badd935740b5d9cc79833a015',
        '4aadeefd1afcc64cecb776bfbec3b877',
        '47b6cf9a122fde963b54f1c79a3d033b',
        'd6e4140acc20a01a7dda910b1a27e714',
        'b92ed031982c3f10cc39912df386ce7a',
        'cff266dcda5345ac7b51a53a64b857e4',
        'c24a3dec1f2e8be6a854d066d6e4cb40',
        '6de395037f6ac603aff4a698274e75cf',
        '66bd19a29880fec026237d4c9fa71162',
        'fb43c9d8bddd1767fa6dfd6955c66e07',
        '3103431f27a16cdff44fc24f897e69da'
    ];
}