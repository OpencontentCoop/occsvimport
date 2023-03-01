<?php

use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\EnvironmentLoader;

class OCMPayload extends eZPersistentObject
{
    public static function definition()
    {
        return [
            'fields' => [
                'id' => [
                    'name' => 'id',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true,
                ],
                'priority' => [
                    'name' => 'priority',
                    'datatype' => 'integer',
                    'default' => 0,
                    'required' => false,
                ],
                'type' => [
                    'name' => 'type',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true,
                ],
                'payload' => [
                    'name' => 'payload',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => false,
                ],
                'modified_at' => [
                    'name' => 'created_at',
                    'datatype' => 'integer',
                    'default' => time(),
                    'required' => false,
                ],
                'executed_at' => [
                    'name' => 'executed_at',
                    'datatype' => 'integer',
                    'default' => null,
                    'required' => false,
                ],
                'result' => [
                    'name' => 'result',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => false,
                ],
                'error' => [
                    'name' => 'error',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => false,
                ],
            ],
            'keys' => ['id'],
            'class_name' => 'OCMPayload',
            'name' => 'ocmpayload',
        ];
    }

    public static function fetch($id): OCMPayload
    {
        $item = eZPersistentObject::fetchObject(self::definition(), null, ['id' => $id]);
        if (!$item instanceof OCMPayload){
            throw new Exception("Payload $id not found");
        }

        return $item;
    }

    public static function create(string $id, string $type, int $priority, array $payload): OCMPayload
    {
        $item = new OCMPayload();
        $item->setAttribute('id', $id);
        $item->setAttribute('priority', $priority);
        $item->setAttribute('type', $type);
        $item->setAttribute('payload', json_encode($payload));
        $item->setAttribute('modified_at', time());
        $item->store();

        return $item;
    }

    public function id()
    {
        return $this->attribute('id');
    }

    public function type()
    {
        return $this->attribute('type');
    }

    public function createOrUpdateContent()
    {
        $repository = new ContentRepository();
        $environment = EnvironmentLoader::loadPreset('content');
        $repository->setCurrentEnvironmentSettings($environment);

        $payload = json_decode($this->attribute('payload'), true);
        $this->setAttribute('executed_at', time());

        try {
            if (strpos($this->id(), '###')){
                $result = $repository->update($payload, true);
            }else {
                $result = $repository->createUpdate($payload, true);
            }
            $this->setAttribute('result', $result['method']);
            $this->setAttribute('error', '');
            $this->store();

            return $result['method'];

        } catch (Throwable $e) {
            $this->setAttribute('error', $e->getMessage());
            $this->store();

            throw $e;
        }
    }
}