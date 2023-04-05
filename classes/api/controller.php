<?php

class OCMController extends ezpRestMvcController
{
    private function doExceptionResult(Exception $exception)
    {
        $result = new ezcMvcResult;
        $result->variables['message'] = $exception->getMessage();

        $serverErrorCode = 500;
        $errorType = get_class($exception);

        $result->status = new OpenApiErrorResponse(
            $serverErrorCode,
            $exception->getMessage(),
            $errorType,
            $exception
        );

        return $result;
    }

    public function doGetItem()
    {
        $result = new ezpRestMvcResult();
        $classes = OCMigration::getAvailableClasses();
        try {
            $class = $this->collection;
            if (strpos($class, 'ocm_') === false){
                $class = 'ocm_' . $class;
            }
            $requestId = $this->item;
            if (in_array($class, $classes)) {
                /** @var OCMigration[] $items */
                $items = $class::fetchByField('_id', $requestId);
                if (isset($items[0])) {
                    $result->variables = $items[0];
                } else {
                    throw new Exception("$class $requestId type not found");
                }
            } else {
                throw new Exception("$class type not found");
            }
        } catch (Throwable $e) {
            $result = $this->doExceptionResult($e);
        }

        return $result;
    }

    // =IMPORTHTML("https://opencity.localtest.me/api/ocm/v1/document/64761e7bdf942f887e02be57b5a7282f/has_organization"; "list"; 1)
    public function doGetItemField()
    {
        $result = $this->doGetItem();

        $value = '';
        if ($result->variables->hasAttribute($this->field)) {
            $value = $result->variables->attribute($this->field);
        }else{
            $spreadSheet =  $result->variables->toSpreadsheet();
            if (isset($spreadSheet[$this->field])){
                $value = $spreadSheet[$this->field];
            }
        }

        header('Content-Type: text/html');
        header('HTTP/1.1 200 OK');
        echo '<html lang="it-IT"><head><title>'.$result->variables->attribute('_id').'</title></head><body><ul><li>';
        echo $value;
        echo '</li></ul></body></html>';
        eZExecution::cleanExit();
    }

    public function doGetCollection()
    {
        $result = new ezpRestMvcResult();
        $classes = OCMigration::getAvailableClasses();
        $limit = $this->request->get['limit'] ?? 500;
        $offset = $this->request->get['offset'] ?? 0;
        try {
            $class = $this->collection;
            if (strpos($class, 'ocm_') === false){
                $class = 'ocm_' . $class;
            }
            if (in_array($class, $classes)) {
                $count = eZPersistentObject::count($class::definition());
                $items = eZPersistentObject::fetchObjectList(
                    $class::definition(),
                    null,
                    null,
                    [$class::getSortField() => 'asc'],
                    [
                        'offset' => (int)$offset,
                        'limit' => (int)$limit,
                    ],
                    true
                );
                $result->variables = [
                    'count' => (int)$count,
                    'limit' => (int)$limit,
                    'offset' => (int)$offset,
                    'items' => $items,
                ];
            } else {
                throw new Exception("$class type not found");
            }
        } catch (Throwable $e) {
            $result = $this->doExceptionResult($e);
        }

        return $result;
    }
}