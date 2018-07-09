<?php

namespace PicoAuth;

use Symfony\Component\HttpFoundation\Request;
use PicoAuth\Storage\Configurator\PluginConfigurator;
use PicoAuth\Security\CSRF;
use PicoAuth\PicoAuthInterface;
use PicoAuth\User;
use PicoAuth\Utils;

/**
 * The main PicoAuth plugin class
 *
 * @author  Pavel Tuma
 * @link    https://github.com/picoauth/picoauth
 * @license http://opensource.org/licenses/MIT The MIT License
 * @version 1.0
 */
class PicoAuthPlugin implements PicoAuthInterface
{

    /**
     * PicoAuth plugin version
     * @var string
     */
    const VERSION = '1.0.0';
    
    /**
     * PicoAuth version ID
     * @var int
     */
    const VERSION_ID = 10000;
    
    /**
     * The name of the plugin
     * @var string
     */
    const PLUGIN_NAME = 'PicoAuth';

    /**
     * Action name for the logout action
     * @var string
     */
    const LOGOUT_CSRF_ACTION = 'logout';

    /**
     * Session manager for the plugin
     * @var Session\SessionInterface
     */
    protected $session;

    /**
     * CSRF Token manager
     * @var CSRF
     */
    protected $csrf;

    /**
     * Dependency container of the plugin
     * @var \League\Container\Container
     */
    protected $container;

    /**
     * Optional instance of a logger
     * @see PicoAuth::initLogger()
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The current user
     * @see PicoAuthPlugin::getUser()
     * @see PicoAuthPlugin::getUserFromSession()
     * @var User
     */
    protected $user;

    /**
     * The current request
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * Pico page id of the current request
     * @var string
     */
    protected $requestUrl;

    /**
     * Requested .md file that will be displayed
     * @see PicoAuthPlugin::onRequestFile()
     * @var null|string
     */
    protected $requestFile = null;

    /**
     * Plugin's configuration from Pico's main configuration file
     * @var array
     */
    protected $config;

    /**
     * Loaded PicoAuth modules
     * @var Module\AbstractAuthModule[]
     */
    protected $modules = array();

    /**
     * Output variables that will be accessible in Twig
     * @var array
     */
    protected $output = array();

    /**
     * Error flag, if true the plugin will display an error page
     * @see PicoAuthPlugin::errorHandler()
     * @var bool
     */
    protected $errorOccurred = false;
    
    /**
     * Full path of the location of PicoAuth plugin
     * @var string
     */
    protected $pluginDir;

    /**
     * PicoCMS instance
     * @var \Pico
     */
    protected $pico;
    
    /**
     * Always allowed routes
     * @see PicoAuthPlugin::addAllowed()
     * @var string[]
     */
    protected $alwaysAllowed = ["login", "logout"];
    
    /**
     * Creates the plugin
     * @param \Pico $pico PicoCMS instance
     */
    public function __construct(\Pico $pico)
    {
        $this->pico = $pico;
        $this->pluginDir = dirname(__DIR__);
    }
    
    /**
     * Handles an event that was triggered by Pico
     *
     * @param string $eventName Name of the Pico event
     * @param array $params Event parameters
     */
    public function handleEvent($eventName, array $params)
    {
        if (method_exists($this, $eventName)) {
            call_user_func_array(array($this, $eventName), $params);
        }
    }
    
    /**
     * Triggers an auth event for all enabled Auth modules
     *
     * @param string $eventName
     * @param array $params
     */
    public function triggerEvent($eventName, array $params = array())
    {
        foreach ($this->modules as $module) {
            $module->handleEvent($eventName, $params);
        }
    }
    
    //-- Pico API registered methods  ------------------------------------------
    
    /**
     * Pico API event - onConfigLoaded
     *
     * Validates plugin's configuration from the Pico's configuration file,
     * fills it with default values, saves it to the config property.
     * Initializes the plugin's dependency container.
     *
     * @param array $config CMS configuration
     */
    public function onConfigLoaded(array &$config)
    {
        $config[self::PLUGIN_NAME] = $this->loadDefaultConfig($config);
        $this->config = $config[self::PLUGIN_NAME];
        $this->createContainer();
        $this->initLogger();
    }

    /**
     * Pico API event - onRequestUrl
     *
     * Runs active authentication and authorization modules.
     *
     * @param string $url Pico page id (e.g. "index" or "sub/page")
     */
    public function onRequestUrl(&$url)
    {
        $this->requestUrl = $url;

        try {
            // Plugin initialization
            $this->init();

            // Check submissions in all modules and apply their routers
            $this->triggerEvent('onPicoRequest', [$url, $this->request]);
        } catch (\Exception $e) {
            $this->errorHandler($e, $url);
        }
        
        if (!$this->errorOccurred) {
            $this->authRoutes();
        }
    }

    /**
     * Pico API event - onRequestFile
     *
     * The plugin will change the requested file if the requested page
     * is one of the plugin's pages or if it has been set by one of the plugin's
     * modules in {@see PicoAuth::setRequestFile()}.
     *
     * @param string $file Reference to the file name Pico will load.
     */
    public function onRequestFile(&$file)
    {
        // A special case for an error state of the plugin
        if ($this->errorOccurred) {
            $file = $this->requestFile;
            return;
        }

        try {
            // Resolve a normalized version of the url
            $realUrl = ($this->requestFile) ? $this->requestUrl : $this->resolveRealUrl($file);
            
            // Authorization
            if (!in_array($realUrl, $this->alwaysAllowed, true)) {
                $this->triggerEvent('denyAccessIfRestricted', [$realUrl]);
            }
        } catch (\Exception $e) {
            $realUrl = (isset($realUrl)) ? $realUrl : "";
            $this->errorHandler($e, $realUrl);
        }
        
        if ($this->requestFile) {
            $file = $this->requestFile;
        } else {
            switch ($this->requestUrl) {
                case 'login':
                    $file = $this->pluginDir . '/content/login.md';
                    break;
                case 'logout':
                    $file = $this->pluginDir . '/content/logout.md';
                    break;
            }
        }
    }

    /**
     * Pico API event - onPagesLoaded
     *
     * Removes pages that should not be displayed in the menus
     * (for example the 403 page) from the Pico's page array.
     * If "alterPageArray" plugin configuration is enabled, the pages with
     * restricted access are also removed from the page array.
     *
     * @param array $pages Pico page array
     */
    public function onPagesLoaded(array &$pages)
    {
        unset($pages["403"]);

        if (!$this->config["alterPageArray"]) {
            return;
        }

        // Erase all pages if an error occurred
        if ($this->errorOccurred) {
            $pages = array();
            return;
        }

        foreach ($pages as $id => $page) {
            try {
                $allowed = $this->checkAccess($id);
            } catch (\Exception $e) {
                $this->errorHandler($e, $this->requestUrl);
                $pages = array();
                return;
            }

            if (!$allowed) {
                unset($pages[$id]);
            }
        }
    }

    /**
     * Pico API event - onTwigRegistered
     *
     * Registers CSRF functions in Pico's Twig environment.
     *
     * @param \Twig_Environment $twig Reference to the twig environment
     */
    public function onTwigRegistered(&$twig)
    {
        // If a theme is not found, it will be searched for in PicoAuth/theme
        $twig->getLoader()->addPath($this->pluginDir . '/theme');

        $this_instance = $this;
        $twig->addFunction(
            new \Twig_SimpleFunction(
                'csrf_token',
                function ($action = null) use (&$this_instance) {
                    return $this_instance->csrf->getToken($action);
                },
                array('is_safe' => array('html'))
            )
        );
        $twig->addFunction(
            new \Twig_SimpleFunction(
                'csrf_field',
                function ($action = null) use (&$this_instance) {
                    return '<input type="hidden" name="csrf_token" value="'
                        . $this_instance->csrf->getToken($action)
                        . '">';
                },
                array('is_safe' => array('html'))
            )
        );
    }

    /**
     * Pico API event
     *
     * Makes certain variables accessible in the twig templates
     *
     * @param string $templateName Name of the twig template file
     * @param array $twigVariables Twig variables
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        $twigVariables['auth']['plugin'] = $this;
        $twigVariables['auth']['vars'] = $this->output;

        // Variables useful only in successful execution
        if (!$this->errorOccurred) {
            $twigVariables['auth']['user'] = $this->user;

            // Previous form submission
            $old = $this->session->getFlash('old');
            if (count($old) && isset($old[0])) {
                $twigVariables['auth']['old'] = $old[0];
            }
        }
    }

    //-- PicoAuthInterface public methods avaialble to Auth modules ------------

    /**
     * {@inheritdoc}
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginPath()
    {
        return $this->pluginDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultThemeUrl()
    {
        return $this->pico->getBaseUrl() . 'plugins/PicoAuth/theme';
    }

    /**
     * {@inheritdoc}
     */
    public function getModule($name)
    {
        if (isset($this->modules[$name])) {
            return $this->modules[$name];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFlashes()
    {
        $types = array("error", "success");
        $result = array();
        foreach ($types as $value) {
            $flashesArr = $this->session->getFlash($value);
            if (count($flashesArr)) {
                $result[$value] = $flashesArr;
            }
        }
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPico()
    {
        return $this->pico;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setUser($user)
    {
        if ($user->getAuthenticated()) {
            $user->addGroup("default");
        }
        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     *
     * Will not take effect if invoked after onRequestFile Pico event.
     * After that, the file is already read by Pico.
     */
    public function setRequestUrl($url)
    {
        $this->requestUrl = $url;
        $this->requestFile = $this->pico->resolveFilePath($url);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestFile($file)
    {
        $this->requestFile = $file;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addOutput($key, $value)
    {
        $this->output[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function addAllowed($url)
    {
        $this->alwaysAllowed[] = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function isValidCSRF($token, $action = null)
    {
        if (!$this->csrf->checkToken($token, $action)) {
            $this->logger->warning(
                "CSRFt mismatch: for {action} from {addr}",
                array(
                    "action" => $action,
                    "addr" => $_SERVER['REMOTE_ADDR']
                )
            );
            $this->session->addFlash("error", "Invalid CSRF token, please try again.");
            return false;
        } else {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function afterLogin()
    {
        $this->csrf->removeTokens();

        // Migrate session to a new ID to prevent fixation
        $this->session->migrate(true);
        
        // Set authentication information
        $this->session->set('user', serialize($this->user));

        $this->logger->info(
            "Login: {id} ({name}) via {method} from {addr}",
            array(
                "id" => $this->user->getId(),
                "name" => $this->user->getDisplayName(),
                "method" => $this->user->getAuthenticator(),
                "addr" => $_SERVER['REMOTE_ADDR']
            )
        );
        
        $this->triggerEvent("afterLogin", [$this->user]);

        $afterLogin = Utils::getRefererQueryParam($this->request->headers->get("referer"), "afterLogin");
        if ($afterLogin && Utils::isValidPageId($afterLogin)) {
            $this->redirectToPage($afterLogin);
        } elseif ($this->session->has("afterLogin")) {
            $page = $this->session->get("afterLogin");
            $this->session->remove("afterLogin");
            $this->redirectToPage($page);
        }

        //default redirect after login
        $this->redirectToPage($this->config["afterLogin"]);
    }

    /**
     * {@inheritdoc}
     */
    public function redirectToPage($url, $query = null, $picoOnly = true)
    {
        $finalUrl = "/";
        
        if ($picoOnly) {
            $append = "";

            if ($query) {
                if (!is_string($query)) {
                    throw new \InvalidArgumentException("Query must be a string.");
                }
                $rewrite = $this->getPico()->isUrlRewritingEnabled();
                $urlChar = ($rewrite) ? '?' : '&';
                $append .= $urlChar . $query;
            }
            $finalUrl = $this->pico->getPageUrl($url) . $append;
        } else {
            $finalUrl = $url;
        }
        
        header('Location: ' . $finalUrl);
        exit();
    }

    /**
     * {@inheritdoc}
     */
    public function redirectToLogin($query = null, $httpRequest = null)
    {
        /* Attempt to extract afterLogin param from the request referer.
         * Login form submissions are sent to /login (without any GET query params)
         * So in case of unsuccessful login attempt the afterLogin information would be lost.
         */
        if ($httpRequest && $httpRequest->headers->has("referer")) {
            $referer = $httpRequest->headers->get("referer");
            $page = Utils::getRefererQueryParam($referer, "afterLogin");
            if (Utils::isValidPageId($page)) {
                $query .= ($query ? '&' : '') . "afterLogin=" . $page;
            }
        }

        $this->redirectToPage("login", $query);
    }

    //-- Protected methods to PicoAuth -----------------------------------------

    /**
     * Resolves a given content file path to the pico url
     *
     * Used for obtaining a unique url for a page file. This is required
     * for authorization as the $url pico provides in onRequest is not unique
     * and can have many equivalents (e.g. /pico/?page vs /pico/?./page).
     * Case sensitivity is the same as returned from realpath().
     *
     * The naming rules follow Pico-defined standards:
     * /content/index.md => ""
     * /content/sub/page.md => "sub/page"
     * /content/sub/index.md => "sub"
     *
     * Example: pico content_dir is /var/pico/content
     *   then file name /var/pico/content/sub/page.md returns sub/page
     *
     * @param string $fileName Pico page file path (from onRequestFile event)
     * @return string Resolved page url
     * @throws \RuntimeException If the filepath cannot be resolved to a url
     */
    protected function resolveRealUrl($fileName)
    {
        $fileNameClean = str_replace("\0", '', $fileName);
        $realPath = realpath($fileNameClean);

        if ($realPath === false) {
            // the page doesn't exist or realpath failed
            return $this->requestUrl;
        }
        
        // Get Pico content path and file extension
        $contentPath = realpath($this->pico->getConfig('content_dir'));
        $contentExt = $this->pico->getConfig('content_ext');
        
        if (strpos($realPath, $contentPath) !== 0) {
            // The file is not inside the content path (symbolic link)
            throw new \RuntimeException("The plugin cannot be used with "
                . "symbolic links inside the content directory.");
        }

        // Get a relative path of $realPath from inside the $contentPath and remove an extension
        // len+1 to remove trailing path delimeter, which $contentPath doesn't have
        $name = substr($realPath, strlen($contentPath)+1, -strlen($contentExt));
        
        // Always use forward slashes
        if (DIRECTORY_SEPARATOR !== '/') {
            $name = str_replace(DIRECTORY_SEPARATOR, '/', $name);
        }
        
        // If the name ends with "/index", remove it, for the main page returns ""
        if (strlen($name) >= 5 && 0 === substr_compare($name, "index", -5)) {
            $name= rtrim(substr($name, 0, -5), '/');
        }
        
        return $name;
    }

    /**
     * Initializes the main plugin components
     */
    protected function init()
    {
        $this->loadModules();

        $this->session = $this->container->get('session');
        $this->csrf = new CSRF($this->session);
        $this->user = $this->getUserFromSession();

        $this->request = Request::createFromGlobals();
        
        // Auto regenerate_id on specified intervals
        $this->sessionTimeoutCheck("sessionInterval", "_migT", false);
        // Enforce absolute maximum duration of a session
        $this->sessionTimeoutCheck("sessionTimeout", "_start", true);
        // Invalidate session if it is idle for too long
        $this->sessionTimeoutCheck("sessionIdle", "_idle", true, true);
    }
    
    /**
     * Checks the session timeouts
     *
     * Checks multiple session timeouts and applies the appropriate
     * actions, see the usage in {@see PicoAuthPlugin::init()}
     *
     * @param string $configKey The configuration key containing the timeout value
     * @param string $sessKey The session key with the deciding timestamp
     * @param bool $clear If set to true, the session will be destroyed (invalidate)
     *                    If set to false, the session will be migrated (change sessid)
     * @param bool $alwaysUpdate If set to true, the timestamp in the session (under $sessKey)
     *                    will be updated on every call, otherwise only on timeout
     */
    protected function sessionTimeoutCheck($configKey, $sessKey, $clear, $alwaysUpdate = false)
    {
        if ($this->config[$configKey] !== false) {
            $t = time();
            if ($this->session->has($sessKey)) {
                if ($this->session->get($sessKey) < $t - $this->config[$configKey]) {
                    if ($clear) {
                        $this->session->invalidate();
                    } else {
                        $this->session->migrate(true);
                    }
                    $this->session->set($sessKey, $t);
                } elseif ($alwaysUpdate) {
                    $this->session->set($sessKey, $t);
                }
            } else {
                $this->session->set($sessKey, $t);
            }
        }
    }

    /**
     * Applies a default plugin configuration to the Pico config file.
     *
     * @param array $config Configuration of Pico CMS; plugin's specific
     *                      configuration is under PLUGIN_NAME index.
     */
    protected function loadDefaultConfig(array $config)
    {
        $configurator = new PluginConfigurator;
        $validConfig = $configurator->validate(
            isset($config[self::PLUGIN_NAME]) ? $config[self::PLUGIN_NAME] : null
        );
        
        return $validConfig;
    }

    /**
     * Creates PicoAuth's dependency container instance.
     *
     * The plugin loads a default container definition from src/container.php
     * If there is a container.php in plugin configuration directory it is used
     * instead.
     *
     * @throws \RuntimeException if the user provided invalid container.php
     */
    protected function createContainer()
    {
        $configDir = $this->pico->getConfigDir();
        $userContainer = $configDir . "PicoAuth/container.php";

        // If a user provided own container definiton, it is used
        if (is_file($userContainer) && is_readable($userContainer)) {
            $this->container = include $userContainer;
            if ($this->container === false || !($this->container instanceof \League\Container\Container)) {
                throw new \RuntimeException("The container.php does not return container instance.");
            }
        } else {
            $this->container = include $this->pluginDir . '/src/container.php';
        }

        // Additional container entries
        $this->container->share('configDir', new \League\Container\Argument\RawArgument($configDir));
        $this->container->share('PicoAuth', $this);
        if (!$this->config["rateLimit"]) {
            $this->container->share('RateLimit', \PicoAuth\Security\RateLimiting\NullRateLimit::class);
        }
    }

    /**
     * Logger initialization
     */
    protected function initLogger()
    {
        try {
            $this->logger = $this->container->get("logger");
        } catch (\League\Container\Exception\NotFoundException $e) {
            $this->logger = new \Psr\Log\NullLogger();
        }
    }

    /**
     * Loads plugin's modules defined in the configuration
     *
     * @throws \RuntimeException if one of the modules could not be loaded
     */
    protected function loadModules()
    {
        foreach ($this->config["authModules"] as $name) {
            try {
                $instance = $this->container->get($name);
            } catch (\League\Container\Exception\NotFoundException $e) {
                if (!class_exists($name)) {
                    throw new \RuntimeException("PicoAuth module not found: " . $name);
                }
                $instance = new $name;
            }

            if (!is_subclass_of($instance, Module\AbstractAuthModule::class, false)) {
                throw new \RuntimeException("PicoAuth module class must inherit from AbstractAuthModule.");
            }

            $name = $instance->getName();
            $this->modules[$name] = $instance;
        }
    }

    /**
     * Plugin routes that are always present
     */
    protected function authRoutes()
    {
        switch ($this->requestUrl) {
            case 'login':
                // Redirect already authenticated user visiting login page
                if ($this->user->getAuthenticated()) {
                    $this->redirectToPage($this->config["afterLogin"]);
                }
                break;
            case 'logout':
                // Redirect non authenticated user to login
                if (!$this->user->getAuthenticated()) {
                    $this->redirectToLogin();
                }
                $this->checkLogoutSubmission();
                break;
        }
    }
    
    /**
     * Checks the current request for logout action
     */
    protected function checkLogoutSubmission()
    {
        $post = $this->request->request;
        if ($post->has("logout")) {
            if (!$this->isValidCSRF($post->get("csrf_token"), self::LOGOUT_CSRF_ACTION)) {
                $this->redirectToPage("logout");
            }
            $this->logout();
        }
    }
    
    /**
     * Logs out the current user
     */
    protected function logout()
    {
        $oldUser = $this->user;
        $this->user = new User();

        // Removes all session data (and invalidates all CSRF tokens)
        $this->session->invalidate();

        $this->triggerEvent("afterLogout", [$oldUser]);
        
        // After logout redirect to main page
        // If user was on restricted page, 403 would appear right after logout
        $this->redirectToPage($this->config["afterLogout"]);
    }

    /**
     * Checks access to a given Pico URL
     *
     * @param string $url Pico Page url
     * @return bool True if the url is accessible, false otherwise
     */
    protected function checkAccess($url)
    {
        foreach ($this->modules as $module) {
            if (false === $module->handleEvent('checkAccess', [$url])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the current user
     *
     * If there is no user in the session, a blank User instance is returned
     *
     * @return User
     */
    protected function getUserFromSession()
    {
        if (!$this->session->has('user')) {
            return new User();
        } else {
            return unserialize($this->session->get('user'));
        }
    }

    /**
     * Fatal error handler
     *
     * If an uncaught exception raises from the auth module, an error
     * page will be rendered and HTTP 500 code returned.
     * The error details are logged, and if "debug: true" is set in the plugin
     * configuration, the error details are displayed also on the error page.
     *
     * @param \Exception $e Exception from the module
     * @param string $url Pico page id that was being loaded
     */
    protected function errorHandler(\Exception $e, $url = "")
    {
        $this->errorOccurred = true;
        $this->requestFile = $this->pluginDir . '/content/error.md';
        if ($this->config["debug"] === true) {
            $this->addOutput("_exception", (string)$e);
        }
        $this->logger->critical(
            "Exception on url '{url}': {e}",
            array(
                "url" => $url,
                "e" => $e
            )
        );
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);

        // Change url to prevent other plugins that use url-based routing from
        // changing the request file.
        $this->requestUrl="500";
    }
}
