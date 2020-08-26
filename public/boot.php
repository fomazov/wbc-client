<?php

namespace WBC;

use Exception;

define('BASE_PATH', __DIR__);

class Tools
{

    private static $_instance = null;
    private static $_instanceBroker = null;

    private $_pathes = null;
    private $_env = null;

    public $language;

    private function _getFullPath($path)
    {
        return $this->_pathes['base_path'] . '/' . trim($path, '/');
    }

    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function setEnv($env)
    {
        $this->_env = $env;
        return $this;
    }

    public function getEnv()
    {
        return $this->_env;
    }

    public function setVersion($version)
    {
        $this->_version = $version;
        return $this;
    }

    public function getVersion()
    {
        return $this->_version;
    }

    public function setBuildDate($buildDate)
    {
        $this->_buildDate = $buildDate;
        return $this;
    }

    public function getBuildDate()
    {
        return $this->_buildDate;
    }

    public function getBuildDateFormat()
    {
        return date('d.m.Y', strtotime($this->_buildDate));
    }

    public function setPath(array $pathes)
    {
        if (is_null($this->_pathes) && $pathes) {
            $this->_pathes = $pathes;
            $this->_pathes['base_path'] = rtrim(BASE_PATH, '/');
        }
    }

    public function getPath($path = null, $fullPath = false)
    {
        if ($path) {
            try {
                if (array_key_exists($path, $this->_pathes)) {
                    return $fullPath ? $this->_getFullPath($this->_pathes[$path]) :
                        $this->_pathes[$path];
                } else {
                    Throw new Exception(sprintf('Path: %s, does not exists', $path));
                }
            } catch (Exception $e) {
                die($e->getMessage());
            }

        } else {
            return $this->_pathes;
        }

    }

    public static function checkResponse($res = null, \Slim\Slim $app = null)
    {
        if (!is_null($app)) {

            try {
                if (!($res instanceof \Requests_Response)) {
                    Throw new Exception(sprintf(
                        'The response: %s should be an instance of \Requests_Response', $res));
                }
            } catch (\Exception $e) {
                die($e->getMessage());
            }

            if(isset($res->headers['content-type'])) {
                $app->response->header('Content-Type', $res->headers['content-type']);
            }

            if($res->status_code == 401) {
                self::clearAllCookie();
            }

            $app->response->setStatus($res->status_code);
            $app->response->setBody($res->body);

        } else {

            if (is_null($res)) $res = self::getUser();

            return (is_array($res) && isset($res['code'])
                && $res['code'] === 200 && isset($res['response']) && !empty($res['response']));

        }

    }

    public static function clearAllCookie()
    {
        if (!isset($_SERVER['HTTP_COOKIE'])) {
            return false;
        }
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach ($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time() - 1000);
            setcookie($name, '', time() - 1000, '/');
        }
    }

    public static function getBroker($useCache = true, $attach = true)
    {
        if($useCache && self::$_instanceBroker) {
            return self::$_instanceBroker;
        }

        return self::$_instanceBroker = SsoHubBroker::getBroker($attach);
    }

    public static function getUser($resClear = false)
    {
        $result = self::getBroker($resClear)->getUserInfo();

        if (!$resClear) {
            return $result;
        }

        if (!isset($result['code']) || 200 !== $result['code'] || !isset($result['response'])) {
            App::redirectToLogin();
        }
        return $result['response'];
    }

    // Выбирается первый существующий язык из списка пользовательских языков в браузере
    // Accept-Language:de,en; -> en
    // Accept-Language:de,ru; -> ru
    // Accept-Language:de,az; -> default
    public function getBestLang($default, $langs)
    {
        $languages = [];
        foreach ($langs as $lang => $alias) {
            if (is_array($alias)) {
                foreach ($alias as $alias_lang) {
                    $languages[strtolower($alias_lang)] = strtolower($lang);
                }
            } else $languages[strtolower($alias)] = strtolower($lang);
        }

        foreach ($this->language as $l => $v) {
            $s = strtok($l, '-'); // убираем то что идет после тире в языках вида "en-us, ru-ru"
            if (isset($languages[$s]))
                return $languages[$s];
        }
        return $default;
    }

    public static function getFileJson($fileName)
    {
        try {
            $path = $fileName;
            if (!file_exists($path)) {
                throw new Exception(sprintf('File: %s, does not exist', $path));
            }
            return json_decode(file_get_contents($path), true);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    public static function getVersionJson()
    {
        return self::getFileJson(BASE_PATH . '/version.json');
    }

    public static function getPackageJson()
    {
        return self::getFileJson(BASE_PATH . '/package.json');
    }
}

$tools = Tools::getInstance();
$packageJson = Tools::getPackageJson();
$tools->setPath($packageJson['pathes']);
$versionJson = Tools::getVersionJson();
$tools->setEnv($versionJson['env']);
$tools->setVersion($versionJson['version']);
$tools->setBuildDate($versionJson['build_date']);

$vendorPath = $tools->getPath('vendor', $fullPath = true);
$brokerPath = $tools->getPath('broker', $fullPath = true);

require_once $vendorPath . '/autoload.php';

// Broker
require_once $brokerPath . '/SsoHubBroker.php';
require_once $brokerPath . '/Request.php';

$app = new \Slim\Slim();
App::run($app, $tools);