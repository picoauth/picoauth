<?php

namespace PicoAuth\Module\Authorization;

use PicoAuth\Storage\Interfaces\PageACLStorageInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use PicoAuth\Module\AbstractAuthModule;

/**
 * Authorization based on pre-defined access rules
 */
class PageACL extends AbstractAuthModule
{

    protected $runtimeRules = [];

    /**
     * Instance of the plugin
     *
     * @var PicoAuthInterface
     */
    protected $picoAuth;
    
    /**
     * Session manager
     *
     * @var SessionInterface
     */
    protected $session;
    
    /**
     * Configuration storage
     *
     * @var PageACLStorageInterface
     */
    protected $storage;
    
    public function __construct(
        PicoAuthInterface $picoAuth,
        SessionInterface $session,
        PageACLStorageInterface $storage
    ) {

        $this->picoAuth = $picoAuth;
        $this->session = $session;
        $this->storage = $storage;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'pageACL';
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($url)
    {
        $user = $this->picoAuth->getUser();
        $id = $user->getId();
        
        $rule = $this->storage->getRuleByURL($url);

        if ($rule === null && count($this->runtimeRules)) {
            $rule = \PicoAuth\Storage\FileStorage::getItemByUrl($this->runtimeRules, $url);
        }

        if ($rule !== null) {
            if (isset($rule['users']) && in_array($id, $rule['users'])) {
                return true;
            }
            if (isset($rule['groups'])) {
                $groups = $user->getGroups();
                foreach ($groups as $group) {
                    if (in_array($group, $rule['groups'])) {
                        return true;
                    }
                }
            }
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function denyAccessIfRestricted($url)
    {
        if (!$this->checkAccess($url)) {
            $user = $this->picoAuth->getUser();
            if ($user->getAuthenticated()) {
                // The user is logged in but does not have permissions to view the page
                $this->picoAuth->setRequestUrl("403");
                header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
            } else {
                // The user is not logged in -> redirect to login
                $this->session->addFlash("error", "Login first to access this page");
                $afterLogin = "afterLogin=" . $url;
                $this->picoAuth->redirectToLogin($afterLogin);
            }
        }
    }

    /**
     * Adds an additional ACL rule
     *
     * Can be used by other plugins to control access to certain pages
     *
     * @param string $url Url for which the rule applies
     * @param array $rule Rule to be added
     */
    public function addRule($url, $rule)
    {
        if (!is_string($url) || !is_array($rule)) {
            throw new \InvalidArgumentException("addRule() expects a string and an array.");
        }
        $this->runtimeRules[$url] = $rule;
    }
}
