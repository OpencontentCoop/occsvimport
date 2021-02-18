<?php

class OCGoogleSpreadsheetImportHandler extends CSVImportHandler implements ISQLIImportHandler
{
    protected $worksheetFeed;

    protected $worksheet;

    public function initialize()
    {
        $this->worksheetFeed = OCGoogleSpreadsheetHandler::instanceFromPublicSpreadsheetId($this->options['google_spreadsheet_id'])->getWorksheetFeed();
        $this->worksheet = $this->worksheetFeed->getByTitle($this->options['sheet']);

        $this->csvIni = eZINI::instance('csvimport.ini');
        $this->classIdentifier = $this->options->attribute('class_identifier');
        $this->contentClass = eZContentClass::fetchByIdentifier($this->classIdentifier);

        if (!$this->contentClass) {
            $this->registerError("La class $this->classIdentifier non esiste.");
            die();
        }

        $mapper = $this->options->hasAttribute('fields_map') ? json_decode($this->options->attribute('fields_map'), 1) : array();

        $this->doc = OCGoogleSpreadsheetHandler::getWorksheetAsSQLICSVDoc($this->worksheet, $this->contentClass, $mapper);
        $this->dataSource = $this->doc->rows;
    }

    public function getHandlerName()
    {
        return $this->options->attribute('name');
    }

    public function getHandlerIdentifier()
    {
        return 'googlespreadsheetimporthandler';
    }

    public function cleanup()
    {
        return;
    }

    protected function cleanFileName($fileName)
    {
        return $fileName;
    }

    protected function getImage($rowData)
    {
        if (!empty($rowData)) {
            if ($this->options->hasAttribute('file_dir')) {
                return parent::getImage($rowData);
            } else {
                return $this->getRemoteFile($rowData);
            }
        }

        return null;
    }

    protected function getFile($rowData)
    {
        if (!empty($rowData)) {
            if ($this->options->hasAttribute('file_dir')) {
                return parent::getFile($rowData);
            } else {
                return $this->getNamedRemoteFile($rowData);
            }
        }

        return null;
    }

    protected function getNamedRemoteFile($url)
    {
        try {
            $filename = basename($url);
            $suffix = eZFile::suffix($filename);
            if ($suffix == $filename || strpos($suffix, '?') !== false) {
                $newFileName = $this->getFileNameFromContentDisposition($url);
                $localPathName = $this->getRemoteFile($url);
                $localPath = dirname($localPathName);
                $newLocalPathName = $localPath . '/' . $newFileName;
                if ($newLocalPathName != $localPathName) {
                    eZFile::rename($localPathName, $newLocalPathName);
                }
                return $newLocalPathName;
            } else {
                return $this->getRemoteFile($url);
            }
        } catch (Exception $e) {
            $this->registerError($e->getMessage());
        }

        return false;
    }

    private function getFileNameFromContentDisposition($url)
    {
        $filename = basename($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)eZINI::instance('sqliimport.ini')->variable('ImportSettings', 'StreamTimeout'));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, eZStaticCache::USER_AGENT);
        $headers = curl_exec($ch);
        $parsedHeaders = array_map(function ($x) {
            return array_map("trim", explode(":", $x, 2));
        }, array_filter(array_map("trim", explode("\n", $headers))));
        foreach ($parsedHeaders as $line) {
            if (strtolower(trim($line[0])) == 'content-disposition') {
                $parts = explode('filename=', $line[1]);
                if (!isset($parts[1])) {
                    $parts = explode("filename*=UTF-8''", $line[1]);
                }
                $filename = urldecode(trim(array_pop($parts), '"'));
            }
        }

        return $filename;
    }

    protected function getTimestamp($string)
    {
        if (empty($string)) {
            return null;
        }

        if ($this->options->hasAttribute('date_format')) {
            $date = DateTime::createFromFormat($this->options->attribute('date_format'), $string);
            if ($date instanceof DateTime) {
                return $date->format('U');
            }
        }

        return parent::getTimestamp($string);
    }

    protected function getRemoteFile($url, array $httpAuth = null, $debug = false, $allowProxyUse = true)
    {
        $url = trim($url);
        $ini = eZINI::instance();
        $importINI = eZINI::instance('sqliimport.ini');

        $localPath = $ini->variable('FileSettings', 'TemporaryDir') . '/' . basename($url);
        $timeout = $importINI->variable('ImportSettings', 'StreamTimeout');

        $ch = curl_init($url);
        $fp = fopen($localPath, 'w+');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        }

        // Should we use proxy ?
        $proxy = $ini->variable('ProxySettings', 'ProxyServer');
        if ($proxy && $allowProxyUse) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            $userName = $ini->variable('ProxySettings', 'User');
            $password = $ini->variable('ProxySettings', 'Password');
            if ($userName) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$userName:$password");
            }
        }

        // Should we use HTTP Authentication ?
        if (is_array($httpAuth)) {
            if (count($httpAuth) != 2) {
                $this->registerError('HTTP Auth : Wrong parameter count in $httpAuth array');
                return false;
            }

            list($httpUser, $httpPassword) = $httpAuth;
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $httpUser . ':' . $httpPassword);
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            $errorNum = curl_errno($ch);
            curl_close($ch);
            $this->registerError("Failed downloading from '$url'. $error");
            return false;
        }

        curl_close($ch);
        fclose($fp);

        return trim($localPath);
    }
}
