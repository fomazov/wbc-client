<?php

namespace WBC;

class App
{
    public static $session = false;
    public static $app = false;
    public static $tools = false;

    /**
     * @param \Slim\Slim $app
     * @param $tools
     */
    public static function run(\Slim\Slim $app, $tools)
    {
        self::$app = $app;
        self::$tools = $tools;

        self::initSession();
        $twig = self::initTwig();

        $token = false;

        $app->view()->setTemplatesDirectory(
            Tools::getInstance()->getPath('core', $fullPath = true)
        );

        $renderParams = array(
            'core_path' => Tools::getInstance()->getPath('core'),
            'env'       => Tools::getInstance()->getEnv(),
            'version'   => Tools::getInstance()->getVersion(),
            'buildDate' => Tools::getInstance()->getBuildDateFormat()
        );

        $app->hook('slim.before.router', function () use ($app, &$token) {
            $allowList = array(
                '/register' => 1,
                '/login' => 1,
                '/password-reset' => 1
            );

            $path = $app->request->getPath();

            if (!isset($allowList[$path]) && stripos($path, '/password-reset/') !== 0) {
                 $token = self::getToken();
            }
        });

        $app->get('/not-found', function () use ($app, $twig, $renderParams) {
            echo $twig->render('404.html', $renderParams);
        })->name('page-not-found');

        $app->map('/getToken', function () use ($app, &$token) {
            $app->response->header("Content-Type", "application/json");
            if ($token) {
                echo json_encode(array(
                    'code' => 200,
                    'token' => $token,
                    'status' => 'Ok'
                ));
            } else {
                echo json_encode(array(
                    'code' => 401,
                    'response' => 'Unauthorized',
                    'status' => 'error'
                ));
            }

        })->via('GET');

        /**
         * Activate user by the code which has been sent on email
         */
        $app->get('/activate-email/:code/:email', function ($code, $email) use ($app) {
            return App::activateRequest(array('activate-email', $code, $email), $app);
        })->conditions(array(
            'code' => '[a-zA-Z0-9]+',
            'email' => '[a-zA-Z0-9@\-_\.]+',
        ));

        $app->get('/activate/:code', function ($code) use ($app) {
            return App::activateRequest(array('activate', $code), $app);
        })->conditions(array('code' => '[a-zA-Z0-9]+'));

        $app->get('/activate-login/:code', function ($code) use ($app) {
            return App::activateRequest(array('activate-login', $code), $app);
        })->conditions(array('code' => '[a-zA-Z0-9]+'));


        /**
         * Registration page rendering
         */
        $app->get('/register', function () use ($app, $twig) {
            $okmsg = isset($_SESSION['slim.flash']['okmsg']) ? $_SESSION['slim.flash']['okmsg'] : null;
            $errmsg = isset($_SESSION['slim.flash']['errmsg']) ? $_SESSION['slim.flash']['errmsg'] : null;
            $formData = isset($_SESSION['slim.flash']['formdata']) ? $_SESSION['slim.flash']['formdata'] : array();

            echo $twig->render('register.html', array(
                'errmsg' => $errmsg,
                'okmsg' => $okmsg,
                'formdata' => $formData,
                'core_path' => Tools::getInstance()->getPath('core')
            ));

        })->name('registration-page');

        /**
         * Registration request.
         * Generate activation code and sent it on email
         */
        $app->post('/register', function () use ($app, $twig) {
            $data = array(
                'email' => $app->request->post('email'),
                'password' => $app->request->post('password'),
                'first_name' => $app->request->post('first_name'),
                'last_name' => $app->request->post('last_name'),
                'company' => $app->request->post('company')
            );

            if (self::$session->get('referrer')){
                $data['request_url'] = self::$session->get('referrer');
            }

            $options = array(
                'verify' => false
            );

            $req = new Request(array('register'), null, null);
            $res = $req->postRequest($data, $options);

            $body = json_decode($res->body, true);
            if ($body['status'] == 'success') {
                $app->flash('okmsg', 'An email with a link to activate your personal account has been sent to the mailbox you specified.');
            } else {
                $app->flash('formdata', $data);
                $app->flash('errmsg', isset($body['response']) && isset($body['response']['message']) ? $body['response']['message'] : null);
            }

            $app->redirect($app->urlFor('registration-page'));

        });

        // Login page rendering
        $app->get('/login', function () use ($app, $twig, $renderParams) {

            if (Tools::checkResponse()) {
                $referrerUrl = self::$session->get('referrer');
                if ($referrerUrl) {
                    $app->response->redirect($referrerUrl, 303);
                } else {
                    $app->response->redirect($app->urlFor('root'));
                }
            } else {
                App::clearSession();
                $app->deleteCookie('sso_token_client');

                $errmsg = isset($_SESSION['slim.flash']['errmsg']) ?
                    $_SESSION['slim.flash']['errmsg'] : null;

                echo $twig->render('login.html', array_merge($renderParams, array(
                    'errmsg' => $errmsg
                )));
            }
        })->name('login-page');

        // Login request
        $app->post('/login', function () use ($app) {


            $broker = Tools::getBroker();

            if (!$broker) {
                $app->flash('errmsg', _('msg_auth_error'));
                $app->redirect($app->urlFor('login-page'));
                $app->halt(500);
            }

            $user = $broker->login(
                $app->request->post('username'),
                $app->request->post('password')
            );

            if (Tools::checkResponse($user)) {
                $referrerUrl = self::$session->get('referrer');
                App::clearSession();

                 if ($referrerUrl) {
                     $app->response->redirect($referrerUrl, 303);
                 } else {
                     $app->response->redirect($app->urlFor('root'));
                 }
            } else {

                if (isset($user['error'])
                    && $user['error'] == 'The broker session id isn\'t attached to a user session'
                ) {
                    $app->deleteCookie('sso_token_client');
                }

                $app->flash('errmsg', isset($user['error']) ? $user['error'] : _('msg_auth_error'));

                $app->response->redirect($app->urlFor('login-page'));
            }

        });

        /**
         * Logout request
         */
        $app->get('/logout', function () use ($app) {
            if (Tools::checkResponse()) {
                Tools::getBroker()->logout();
                $app->response->redirect($app->urlFor('login-page'));
            } else {
                $app->response->redirect($app->urlFor('root'));
            }
        });

        /**
         * Password reset page rendering
         */
        $app->get('/password-reset', function () use ($app, $twig) {
            $okmsg = isset($_SESSION['slim.flash']['okmsg']) ? $_SESSION['slim.flash']['okmsg'] : null;
            $errmsg = isset($_SESSION['slim.flash']['errmsg']) ? $_SESSION['slim.flash']['errmsg'] : null;
            $formData = isset($_SESSION['slim.flash']['formdata']) ? $_SESSION['slim.flash']['formdata'] : array();
            if ($app->request()->get('email')){
                $formData['email'] = $app->request()->get('email');
            }

            echo $twig->render('password-reset.html', array(
                'errmsg' => $errmsg,
                'okmsg' => $okmsg,
                'formdata' => $formData,
                'showMode' => 'email',
                'core_path' => Tools::getInstance()->getPath('core')
            ));

        })->name('password-reset-page');

        /**
         * Password reset request.
         * Generate activation code and sent it on email
         */
        $app->post('/password-reset', function () use ($app, $twig) {
            if ($app->request->post('password')) {
                $broker = Tools::getBroker();
                $data = [
                    'password' => $app->request->post('password'),
                    'password_repeat' => $app->request->post('password_repeat'),
                    'code' => $app->request->post('code')
                ];
                $req = new Request(array('password-reset'), null, CURRENT_LOCALE_ISO);
                $res = $req->postRequest($data, array());

                $body = json_decode($res->body, true);
                if ($body['status'] == 'success') {
                    $response = $body['response'];

                    $user = $broker->login(
                        $response['email'],
                        $app->request->post('password')
                    );

                    $requestUrl = $app->request->get('request_url');

                    if (Tools::checkResponse($user)) {
                        if ($requestUrl) {
                            return $app->response->redirect($requestUrl, 303);
                        } else {
                            return $app->response->redirect($app->urlFor('root'));
                        }
                    }

                    $app->flash('errmsg', _('msg_auth_error'));
                    return $app->response->redirect($app->urlFor('login-page'));
                } else {
                    $errmsg = isset($body['response']) && isset($body['response']['message']) ? $body['response']['message'] : null;
                    $formData['code'] = $app->request->post('code');
                    echo $twig->render('password-reset.html', array(
                        'errmsg' => $errmsg,
                        'okmsg' => null,
                        'formdata' => $formData,
                        'showMode' => 'passwords',
                        'core_path' => Tools::getInstance()->getPath('core')
                    ));
                    return;
                }
            }

            $data = array(
                'email' => $app->request->post('email')
            );

            if (self::$session->get('referrer')){
                $data['request_url'] = self::$session->get('referrer');
            }

            $req = new Request(array('password-reset/send'), null, CURRENT_LOCALE_ISO);
            $res = $req->postRequest($data, array());

            $body = json_decode($res->body, true);
            if ($body['status'] == 'success') {
                $app->flash('okmsg', 'An email with a link to reset your password has been sent to your mailbox.');
            } else {
                $app->flash('formdata', $data);
                $app->flash('errmsg', isset($body['response']) && isset($body['response']['message']) ? $body['response']['message'] : null);
            }

            $app->redirect($app->urlFor('password-reset-page'));

        });

        /**
         * Password reset page rendering
         */
        $app->get('/password-reset/:code', function ($code) use ($app, $twig) {
            $options = array();
            $req = new Request(array('password-reset/check'), null, CURRENT_LOCALE_ISO);
            $res = $req->postRequest(['code' => $code], $options);

            $body = json_decode($res->body, true);
            if ($body['status'] != 'success') {
                $app->flash('errmsg', isset($body['response']) && isset($body['response']['message']) ? $body['response']['message'] : null);
                $app->response->redirect($app->urlFor('password-reset-page'));
            }

            $okmsg = isset($_SESSION['slim.flash']['okmsg']) ? $_SESSION['slim.flash']['okmsg'] : null;
            $errmsg = isset($_SESSION['slim.flash']['errmsg']) ? $_SESSION['slim.flash']['errmsg'] : null;
            $formData = isset($_SESSION['slim.flash']['formdata']) ? $_SESSION['slim.flash']['formdata'] : array();
            $formData['code'] = $code;

            echo $twig->render('password-reset.html', array(
                'errmsg' => $errmsg,
                'okmsg' => $okmsg,
                'formdata' => $formData,
                'showMode' => 'passwords',
                'core_path' => Tools::getInstance()->getPath('core')
            ));

        })->conditions(array('code' => '[a-zA-Z0-9]+'))
            ->name('password-reset-page-code');

        $renderPage = function () use ($app, &$token, $renderParams) {
            if (!$token) {
                return App::redirectToLogin();
            }

            $renderParams['userInfo'] = Tools::getUser(true);
            $app->view()->setData($renderParams);
            $app->render('index.html');
        };
        $app->get('.+', $renderPage)->via('GET');
        $app->get('/', $renderPage)->via('GET')->name('root');


        $app->run();
    }

    public static function initTwig()
    {
        $allowedLocales = [
            'en' => 'en_US.utf8',
            'ru' => 'ru_RU.utf8'
        ];

        $refererUrl = false;
        $referrerPattern = '#wbc-any-new-component.fomazov.name#';

        if (isset($_GET['request_url']) && preg_match($referrerPattern, $_GET['request_url'])) {
            $refererUrl = $_GET['request_url'];
            self::$session->set('referrer', $refererUrl);
        } elseif(self::$session->get('referrer')) {
            $refererUrl = self::$session->get('referrer');
            self::$session->set('referrer', null);
        }

        $loader = new \Twig_Loader_Filesystem('twig/templates');
        $twig = new \Twig_Environment($loader, array(
            'cache' => 'twig/cache'
        ));

        $hashParams = $locale == 'ru' ? false : '?lng=' . $locale;

        if ($refererUrl) {
            if (!$hashParams) {
                $hashParams = '?request_url='.$refererUrl;
            } else {
                $hashParams .= '&request_url='.$refererUrl;
            }
        }

        return $twig;
    }

    public static function initSession()
    {
        $session_factory = new \Aura\Session\SessionFactory;
        $session = $session_factory->newInstance($_COOKIE);
        $session->start();
        self::$session = $session->getSegment('profile');
        self::$session->get('init');
    }

    public static function getToken($force = false)
    {
        $token = self::$session->get('token');
        if(!$force && $token && is_string($token)) {
            $broker = Tools::getBroker(true, false);
            $broker->updateCookieTimeToken();
            return $token;
        }

        if (self::$app->request->isAjax() || self::$app->request->isXhr()) {
            return false;
        }

        $token = self::tryGetToken();
        if (!$token) {
            return false;
        }

        self::$session->set('token', $token);
        return $token;
    }

    public static function tryGetToken($iterator = 2)
    {
        $result = false;
        try {
            $result = Tools::getUser();
        } catch (\Exception $e) {
        }

        if (!$result || !is_array($result) || !isset($result['response']) || !isset($result['response']['token'])) {
            self::clearSession();

            if ($iterator > 0) {
                $iterator--;
                return self::tryGetToken($iterator);
            }

            return false;
        }

        return $result['response']['token'];
    }

    public static function clearSession()
    {
        self::$session->set('token', false);
        Tools::clearAllCookie();
    }

    public static function activateRequest(array $request, $app)
    {
        $options = array();

        $req = new Request($request, null, null);
        $res = $req->getRequest(null, $options);

        if (!$res || !property_exists($res, 'body')) {
            $app->flash('errmsg', _('msg_err_account_activate'));
            return $app->response->redirect($app->urlFor('login-page'));
        }

        $body = json_decode($res->body, true);

        if ($body['status'] == 'success' && ($broker = Tools::getBroker())) {
            $response = $body['response'];

            $user = $broker->login(
                $response['email'],
                $response['password']
            );

            $requestUrl = $app->request->get('request_url');

            if (Tools::checkResponse($user)) {
                if ($requestUrl) {
                    return $app->response->redirect($requestUrl, 303);
                } else {
                    return $app->response->redirect($app->urlFor('root'));
                }
            }

            $app->flash('errmsg', _('msg_auth_error'));
            return $app->response->redirect($app->urlFor('login-page'));
        } else {
            $app->flash('errmsg', isset($body['response']) && isset($body['response']['message']) ? $body['response']['message'] : null);
        }

        return $app->response->redirect($app->urlFor('login-page'));
    }

    public static function redirectToLogin()
    {
        $json = SsoHubBroker::getPackageJson();
        $env  = Tools::getInstance()->getEnv();
        $baseUrl = $json['protocol'] . '://' . $json['envData'][$env]['login_url'] . '/login';
        header('Location: ' . $baseUrl);
        exit;
    }

}

require_once './boot.php';
