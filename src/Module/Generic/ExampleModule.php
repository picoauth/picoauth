<?php

namespace PicoAuth\Module\Generic;

use PicoAuth\Module\AbstractAuthModule;
use Symfony\Component\HttpFoundation\Request;

/**
 * An example Auth module
 *
 * This module does nothing, it contains specifications of all
 * available auth event methods triggered by Pico Auth plugin
 */
class ExampleModule extends AbstractAuthModule
{

    /**
     * Called on each request
     *
     * This is the main method of every module - contains
     * form submission checks, custom routing based on the Url or
     * the Request parameters or no operation.
     *
     * @param string|null $url Requested URL in Pico (page ID)
     * @param Request $httpRequest The current HTTP request
     */
    public function onPicoRequest($url, Request $httpRequest)
    {
    }
    
    /**
     * Checks accessibility for a give page URL
     *
     * Used for evaluating accessibility of a page (for example when removing
     * inaccessible links from Pico page array), to perform an access denial,
     * the module should also implement {@see ExampleModule::denyAccessIfRestricted()}
     *
     * @param string|null $url Pico page URL
     * @return bool true is the site is accessible, false otherwise
     */
    public function checkAccess($url)
    {
        return true;
    }

    /**
     * Denies access if restricted
     *
     * Each authorization module is in control of how it will deny the access:
     * It can be done by a redirect to a different
     * page (while aborting execution of the current request)
     * {@see PicoAuthPlugin::redirectToPage()} or {{@see PicoAuthPlugin::redirectToLogin()}},
     * or by setting a different requestFile (the requested URL will be the same,
     * but the original content will not be displayed) {@see PicoAuthPlugin::setRequestFile()}
     * or {@see PicoAuthPlugin::setRequestUrl()}.
     *
     * @param string|null $url Pico page URL
     */
    public function denyAccessIfRestricted($url)
    {
    }
    
    /**
     * Triggered after a login
     *
     * @param \PicoAuth\User $user User instance
     */
    public function afterLogin(\PicoAuth\User $user)
    {
    }
    
    /**
     * Triggered after a logout
     *
     * Module can perform a custom after-logout redirect or perform
     * a single sign out
     *
     * @param \PicoAuth\User $oldUser The previously logged in user
     */
    public function afterLogout(\PicoAuth\User $oldUser)
    {
    }

    /**
     * Required from AbstractAuthModule
     */
    public function getName()
    {
        return 'example';
    }
}
