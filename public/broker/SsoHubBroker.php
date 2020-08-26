<?php

namespace WBC;

use \Exception;
use Jasny\SSO\Broker;

class SsoHubBroker extends Broker
{
    const SSO_BROKER_ID = 'Client';
    const SSO_BROKER_SECRET = 'd41d8cd98f';

    /**
     * Class constructor
     *
     * @param string $url Url of SSO server
     * @param string $broker My identifier, given by SSO provider.
     * @param string $secret My secret word, given by SSO provider.
     */
    public function __construct($url, $broker, $secret)
    {
        parent::__construct(rtrim($url, '/'), $broker, $secret);
    }

    /**
     * Get the request url for a command
     *
     * @param string $command
     * @param array $params Query parameters
     * @return string
     */
    protected function getRequestUrl($command, $params = [])
    {
        $params['command'] = $command;
        $params['sso_session'] = $this->getSessionId();

        return $this->url . '/' . strtolower($command) . '/' . '?' . http_build_query($params);
    }

    /**
     * Get URL to attach session at SSO server.
     *
     * @param array $params
     * @return string
     */
    public function getAttachUrl($params = [])
    {
        $this->generateToken();

        $data = [
                'command' => 'attach',
                'broker' => $this->broker,
                'token' => $this->token,
                'checksum' => hash('sha256', 'attach' . $this->token . $_SERVER['REMOTE_ADDR'] . $this->secret)
            ];
            //+ $_GET;

        return $this->url . '/attach/' . "?" . http_build_query($data + $params);
    }

    /**
     * Execute on SSO server.
     *
     * @param string $method HTTP method: 'GET', 'POST', 'DELETE'
     * @param string $command Command
     * @param array|string $data Query or post parameters
     * @return array|object
     */
    protected function request($method, $command, $data = null, $headers = null)
    {
        $url = $this->getRequestUrl($command, !$data || $method === 'POST' ? [] : $data);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($headers){
            $headers = \Requests::flatten($headers);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        }

        if ($method === 'POST' && !empty($data)) {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch) != 0) {
            throw new Exception("Server request failed: " . curl_error($ch), 500);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($contentType) = explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE));

        if ($contentType != 'application/json') {
            $message = "Expected application/json response, got $contentType";
            error_log($message . "\n\n<pre>" . $response.'</pre>');
            throw new Exception($message . "\n\n<pre>" . $response.'</pre>', $httpCode);
        }

        $data = json_decode($response, true);
        return $data;
    }

    /**
     * Generate session token
     */
    public function generateToken()
    {
        if (isset($this->token)) return;

        $this->token = base_convert(md5(uniqid(rand(), true)), 16, 36);
        $this->setTokenToCookie();
    }

    /**
     * Update time cookie token
     */
    public function updateCookieTimeToken()
    {
        if (!isset($this->token)) {
            return null;
        }

        $this->setTokenToCookie();
    }

    protected function setTokenToCookie()
    {
        setcookie($this->getCookieName(), $this->token, time() + 3 * 3600, '/');
    }

    /**
     * @return mixed
     */
    public static function getPackageJson()
    {
        $packageJson = file_get_contents(BASE_PATH . '/package.json');
        return json_decode($packageJson, true);
    }

    /**
     * @param null $packageJson
     * @return string
     */
    public static function getSsoUrl($packageJson = null)
    {
        $packageJson = self::getPackageJson();
        $env = Tools::getInstance()->getEnv();
        $ssoServer = $packageJson['protocol'] . '://' . rtrim($packageJson['envData'][$env]['api_url'], '/');
        return $ssoServer;
    }

    public static function getBroker($attach = true)
    {

        $ssoServer = self::getSsoUrl();
        $broker = new static($ssoServer, self::SSO_BROKER_ID, self::SSO_BROKER_SECRET);

        if($attach) {
            $broker->attach(true);
        }

        return $broker;
    }

    public static function getHeaders()
    {
        $result = [
            'Accept' => 'application/json',
            'locale' => CURRENT_LOCALE_ISO
        ];
        return $result;
    }

    public function login($username = null, $password = null)
    {
        if (!isset($username) && isset($_POST['username'])) $username = $_POST['username'];
        if (!isset($password) && isset($_POST['password'])) $password = $_POST['password'];

        $result = $this->request('POST', 'login', compact('username', 'password'), self::getHeaders());
        $this->userinfo = $result;

        return $this->userinfo;
    }
}