<?php

use Opencontent\Google\GoogleSheetClient;

class OCMGoogleSheetClient extends GoogleSheetClient
{
    private $credentialData;

    public function __construct()
    {
        $credentialData = self::getGoogleCredentials();
        if (!$credentialData) {
            $credentialFilepath = getenv('GOOGLE_CREDENTIAL_JSON_FILE');
            if (!$credentialFilepath) {
                $credentialFilepath = eZSys::rootDir() . '/settings/google_credentials.json';
            }
            if (file_exists($credentialFilepath)) {
                $credentialData = json_decode(file_get_contents($credentialFilepath), true);
            }
        }

        $this->credentialData = $credentialData;
    }

    public static function getGoogleCredentials(): ?array
    {
        $siteData = eZSiteData::fetchByName('ocm_google_credentials');
        if ($siteData instanceof eZSiteData) {
            return json_decode($siteData->attribute('value'), true);
        }

        return null;
    }

    public static function setGoogleCredentials($data)
    {
        $siteData = eZSiteData::fetchByName('ocm_google_credentials');
        if (!$siteData instanceof eZSiteData) {
            $siteData = eZSiteData::create('ocm_google_credentials', '');
        }
        if (empty($data)) {
            $siteData->remove();
        } else {
            if (!self::validateGoogleCredentials($data)) {
                throw new Exception("Invalid credentials format");
            }
            if (!is_string($data)) {
                $data = json_encode($data);
            }
            $siteData->setAttribute('value', $data);
            $siteData->store();
        }
    }

    private static function validateGoogleCredentials($data): bool
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            return false;
        }
        return isset($data['type'], $data['client_id'], $data['client_email']);
    }

    public function getGoogleClient()
    {
        $client = new \Google_Client();
        $client->setApplicationName('Google Sheets Importer');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig($this->credentialData);
        return $client;
    }
}