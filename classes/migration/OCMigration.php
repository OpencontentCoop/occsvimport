<?php

class OCMigration extends eZPersistentObject
{
    /**
     * @param string|null $context
     * @return OCMigrationComunweb|OCMigrationOpencity
     * @throws Exception
     */
    final public static function factory(string $context = null)
    {
        if (!$context) {
            !$context = self::discoverContext();
        }
        if ($context === 'comunweb') {
            return new OCMigrationComunweb();
        }

        if ($context === 'opencity') {
            return new OCMigrationOpencity();
        }

        throw new Exception("Context $context not found");
    }

    final public static function discoverContext()
    {

        if (eZContentClass::classIDByIdentifier('organization')) {
            return false;
        }

        if (eZContentClass::classIDByIdentifier('opening_hours_specification')) {
            return 'opencity';
        }

        return 'comunweb';
    }

    protected function debug($message, $eol = true)
    {
        eZCLI::instance()->output($message, $eol);
    }

    protected function info($message, $eol = true)
    {
        eZCLI::instance()->warning($message, $eol);
    }

    protected function error($message, $eol = true)
    {
        eZCLI::instance()->error($message, $eol);
    }

    /**
     * @param array $classIdentifiers
     * @return eZContentObjectTreeNode[]
     */
    protected function getNodesByClassIdentifierList(array $classIdentifiers): array
    {
        $this->info('Fetching ' . implode(', ', $classIdentifiers) . '... ', false);
        /** @var eZContentObjectTreeNode[] $nodes */
        $nodes = eZContentObjectTreeNode::subTreeByNodeID([
            'MainNodeOnly' => true,
            'ClassFilterType' => 'include',
            'ClassFilterArray' => $classIdentifiers,
        ], 1);
        $this->info(count($nodes) . ' node founds');

        return $nodes;
    }

    final public static function getAvailableClasses($namesFilter = [])
    {
        $ocmList = [];
        $classes = include 'var/autoload/ezp_extension.php';
        foreach (array_keys($classes) as $class) {
            if (strpos($class, 'ocm_') !== false && in_array('ocm_interface', class_implements($class))) {
                if (!empty($namesFilter) && !in_array($class, $namesFilter)){
                    continue;
                }
                $ocmList[] = $class;
            }
        }

        return $ocmList;
    }

    final public static function createTableIfNeeded($cli = null)
    {
        $db = eZDB::instance();
        eZDB::setErrorHandling(eZDB::ERROR_HANDLING_EXCEPTIONS);
        if ($cli) $cli->warning("Using db " . $db->DB);

        $tableQuery = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename  like 'ocm_%';";
        $exists = array_column($db->arrayQuery($tableQuery), 'tablename');

        $classes = OCMigration::getAvailableClasses();
        foreach ($classes as $class) {
            $fields = $class::definition()['fields'];

            $tableCreateSql = "CREATE TABLE $class (";
            foreach ($fields as $field => $definition) {
                if ($field === '_id') {
                    $tableCreateSql .= "$field varchar(255) NOT NULL default ''";
                } else {
                    $tableCreateSql .= "$field text default '', ";
                }
            }
            $tableCreateSql .= ');';
            $tableKeySql = "ALTER TABLE ONLY $class ADD CONSTRAINT {$class}_pkey PRIMARY KEY (_id);";
            if (!in_array($class, $exists)) {
                if ($cli) $cli->warning('Create table ' . $class);
                foreach ($fields as $field => $definition) {
                    if ($cli) $cli->output(' - ' . $field);
                }
                $db->query($tableCreateSql);
                $db->query($tableKeySql);
            } else {
                if ($cli) $cli->output('Table ' . $class . ' already exists');
            }
        }
    }
}