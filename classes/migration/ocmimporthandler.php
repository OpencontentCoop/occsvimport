<?php

class OCMImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    private $opt = [];

    public function initialize()
    {
        $this->opt = [
            'class_filter' => $this->options['only'] ? explode(',', $this->options['only']) : [],
            'update' => !!$this->options['update'],
            'validate' => !!$this->options['validate']
        ];
        $action = $this->options['action'];
    }

    public function getProcessLength()
    {
        return 1;
    }

    public function getNextRow()
    {
        static $alreadyDone;
        if ($alreadyDone){
            return false;
        }
        $alreadyDone = true;
        return $this->options['action'];
    }

    public function process($action)
    {
        try {
            switch ($action){
                case 'export':
                    OCMigrationSpreadsheet::export(null, $this->opt);
                    break;
                case 'import':
                    OCMigrationSpreadsheet::instance()->import(null, $this->opt);
                    break;
                case 'pull':
                    OCMigrationSpreadsheet::instance()->pull(null, $this->opt);
                    break;
                case 'push':
                    OCMigrationSpreadsheet::instance()->push(null, $this->opt);
                    break;
            }
        }catch (Throwable $e){
            OCMigrationSpreadsheet::setCurrentStatus($action, 'error', $this->opt, $e->getMessage());
            $this->progressionNotes = $e->getMessage();
        }
    }

    public function cleanup()
    {
        // TODO: Implement cleanup() method.
    }

    public function getHandlerName()
    {
        return 'Assistente Migrazione';
    }

    public function getHandlerIdentifier()
    {
        return 'ocmimporthandler';
    }

    public function getProgressionNotes()
    {
        return $this->progressionNotes;
    }

}