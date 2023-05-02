<?php

class OCMNullClient extends \Opencontent\Opendata\Rest\Client\HttpClient
{
    public function __construct()
    {

    }

    public function request($method, $url, $data = null)
    {
        return [];
    }

}