<?php

namespace PicoAuth;

/**
 * PicoAuth public API accessible to authentication/authorization modules.
 * @version 1.0
 */
interface PicoAuthInterface
{
    
    /**
     * PicoAuth API version
     * @var int
     */
    const PICOAUTH_API_VERSION = 1;
    
    /**
     * Get PicoAuth's dependency container
     * @return \League\Container\Container
     */
    public function getContainer();
    
    /**
     * Get a value from the plugin configuration
     * @param string $key The configuration key to return
     * @param mixed $default Default value if the key is not set
     * @return mixed
     */
    public function getAuthConfig($key, $default = null);
    
    /**
     * Get the current user.
     * Returns user instance even if the user is not authenticated.
     * @return \PicoAuth\User
     */
    public function getUser();
    
    /**
     * Return full path of plugin's installation directory.
     * @return string absolute path of plugin location
     */
    public function getPluginPath();

    /**
     * Returns URL of the default plugin theme.
     * Used from the default twig templates to load js/css resources.
     * @return string
     */
    public function getDefaultThemeUrl();
    
    /**
     * Get module by name.
     * If the module does not exist or is not enabled, the method returns null.
     * @param string $name Module name
     * @return null|\PicoAuth\Module\AbstractAuthModule
     */
    public function getModule($name);
    
    /**
     * Get flash messages for this request.
     * @return array
     */
    public function getFlashes();
    
    /**
     * Get Pico instance.
     * @see AbstractPicoPlugin::getPico()
     * @return \Pico
     */
    public function getPico();
    
    /**
     * Set user instance.
     * @param \PicoAuth\User $user User instance
     */
    public function setUser($user);
    
    /**
     * Change requested page id.
     * The change can be propagated to Pico only if the call is made
     * from a place originating from \PicoAuth::onRequestUrl().
     * @see \PicoAuth::onRequestUrl()
     * @param string $url Pico page id to be set
     */
    public function setRequestUrl($url);
    
    /**
     * Set the requested file.
     * @param string $file Path to the loaded .md file
     */
    public function setRequestFile($file);

    /**
     * Adds a new value to plugin's Twig variables.
     * Example: if a key "foo" is registered, it can be later displayed in
     * the template under {{ auth.vars.foo }}
     * @param string $key Output key
     * @param mixed $value Output value
     */
    public function addOutput($key, $value);
    
    /**
     * Adds an always allowed page.
     * This url will always be available no matter which authorization modules
     * are active and how are they configured. Useful for authentication modules
     * to allow access to routes that are needed for authentication
     * (e.g. login pages, registration, password reset pages, etc.)
     * Modules need to register these routes before denyAccessIfRestricted event.
     * @param string $url Pico page url to be always allowed
     */
    public function addAllowed($url);
    
    /**
     * Performs CSRF validation.
     * @see \PicoAuth\Security\CSRF
     * @param string $token Token to be validated
     * @param null|string $action Optional action the token is associated with
     * @return boolean true if the token is valid, false otherwise
     */
    public function isValidCSRF($token, $action = null);
    
    /**
     * After-login procedure.
     * Called by any authentication module after successful login. Migrates session,
     * logs the login, invalidates csrf tokens.
     */
    public function afterLogin();
    
    /**
     * Redirects to page ID.
     * Calls exit(), therefore no further code is executed after the call.
     * Can be used also for a generic redirect by setting picoOnly to false.
     * @param string $url Pico page id
     * @param null|string $query Optional HTTP query params to append
     * @param bool $picoOnly true=local scope redirect only, false=any url
     */
    public function redirectToPage($url, $query = null, $picoOnly = true);
    
    /**
     * Redirects to the login page.
     * If the Request instance is given, the method will try to extract an "afterLogin"
     * query param from the referer header, and if it is a valid Pico page id
     * it will add it to the redirect URL.
     * Same as {@see PicoAuthInterface::redirectToPage()}, this method is terminal.
     * @param null|string $query HTTP query params to append
     * @param null|\Symfony\Component\HttpFoundation\Request $httpRequest The current request
     */
    public function redirectToLogin($query = null, $httpRequest = null);
}
