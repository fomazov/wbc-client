<?php

namespace WBC;

use Jasny\ValidationResult;

class Request extends SsoHubBroker
{
    private $urlParam;
    private $userToken;

    public function __construct(array $urlParam, $userToken, $locale)
    {
        $this->urlParam  = $urlParam;
        $this->userToken = $userToken;
        $this->locale    = $locale;
    }

    public function getRequest($data, array $options)
    {
        $request = \Requests::request($this->_requestUrl(), $this->_headers($options), $data, \Requests::GET, $options);
        return $request;
    }

    public function postRequest($data, array $options)
    {
        $request = \Requests::post($this->_requestUrl(), $this->_headers($options), $data, $this->uploadFile($data, $options));
        return $request;
    }

    public function putRequest($data, array $options)
    {
        $request = \Requests::put($this->_requestUrl(), $this->_headers($options), $data, $options);
        return $request;
    }

    public function deleteRequest($data, array $options)
    {
        $request = \Requests::delete($this->_requestUrl(), $this->_headers($options), $options);
        return $request;
    }

    /** Private methods **/

    private function _headers(&$options = array())
    {
        $result = [
            'Accept' => 'application/json',
            'id' => self::SSO_BROKER_ID,
            'secret' => self::SSO_BROKER_SECRET,
            'token' => $this->userToken,
            'locale' => $this->locale
        ];

        if($this->userToken && isset($options['ssotoken']) && $options['ssotoken']) {
            $result['ssotoken'] = Tools::getBroker()->getSessionId();
            unset($options['ssotoken']);
        }
// Debug feature. Uncomment this for getting ssotoken
//print_r($result);die();
        return $result;
    }

    private function uploadFile($postData, $options)
    {
        if (!isset($_FILES) || !isset($_FILES['file']) || !count($_FILES)) {
            return $options;
        }

        $hooks = new \Requests_Hooks();
        $hooks->register('curl.before_send', function($fp) use($postData){
            $fileList = array();
            foreach($_FILES['file'] as $name=>$dataValues) {
                if(is_array($dataValues)) {
                    foreach ($dataValues as $key => $data) {
                        if (!isset($fileList[$key])) {
                            $fileList[$key] = array();
                        }

                        $fileList[$key][$name] = $data;
                    }
                } else {
                    $key = 0;

                    if (!isset($fileList[$key])) {
                        $fileList[$key] = array();
                    }

                    $fileList[$key][$name] = $dataValues;
                }
            }

            $result = array();
            foreach($fileList as $file) {
                $postData[] = new \CurlFile($file['tmp_name'], $file['type'], $file['name']);
            }

            curl_setopt($fp, CURLOPT_POSTFIELDS, $postData);
        });

        return array_merge($options, array('hooks' => $hooks));
    }

    private function _requestUrl()
    {
        $ssoUrl = rtrim(self::getSsoUrl(), '/');
        return $this->urlParam ?
            sprintf('%s/%s', $ssoUrl, implode('/', $this->urlParam)) : $ssoUrl;
    }
}