<?php

use Opencontent\Opendata\Rest\Client\HttpClient;

class OCLocalHttpClient extends HttpClient
{
    private $host;

    public function __construct(
        $server,
        $login = null,
        $password = null,
        $apiEnvironmentPreset = 'content',
        $apiEndPointBase = '/api/opendata/v2'
    ) {
        parent::__construct($server, $login, $password, $apiEnvironmentPreset, $apiEndPointBase);
        $this->host = parse_url($this->server, PHP_URL_HOST);
        $this->server = 'localhost';
    }

    public function request($method, $url, $data = null)
    {
        $headers = [
            'Host: ' . $this->host
        ];

        if ($this->login && $this->password) {
            $credentials = "{$this->login}:{$this->password}";
            $headers[] = "Authorization: Basic " . base64_encode($credentials);
        }

        $ch = curl_init();
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
        }
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectionTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$processTimeout);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy . ':' . $this->proxyPort);
            if ($this->proxyLogin !== null) {
                curl_setopt(
                    $ch,
                    CURLOPT_PROXYUSERPWD,
                    $this->proxyLogin . ':' . $this->proxyPassword
                );
                curl_setopt($ch, CURLOPT_PROXYAUTH, $this->proxyAuthType);
            }
        }

        $data = curl_exec($ch);

        if ($data === false) {
            $errorCode = curl_errno($ch) * -1;
            $errorMessage = curl_error($ch);
            curl_close($ch);
            throw new \Exception($errorMessage, $errorCode);
        }

        $info = curl_getinfo($ch);
        if (class_exists('\eZDebug')) {
            \eZDebug::writeDebug($info['request_header'], __METHOD__);
        }

        curl_close($ch);

        $headers = substr($data, 0, $info['header_size']);
        if ($info['download_content_length'] > 0) {
            $body = substr($data, -$info['download_content_length']);
        } else {
            $body = substr($data, $info['header_size']);
        }

        return $this->parseResponse($info, $headers, $body);
    }
}