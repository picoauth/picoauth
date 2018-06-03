<?php

namespace PicoAuth\Module\Authorization;

use PicoAuth\Storage\Interfaces\PageLockStorageInterface;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use PicoAuth\PicoAuthInterface;
use PicoAuth\Module\AbstractAuthModule;

/**
 * Basic authorization by page locking
 */
class PageLock extends AbstractAuthModule
{

    const UNLOCK_CSRF_ACTION = 'unlock';

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
     * @var PageLockStorageInterface
     */
    protected $storage;
    
    /**
     * The rate limiter
     *
     * @var RateLimitInterface
     */
    protected $limit;

    /**
     * The configuration array
     *
     * @var array
     */
    protected $config;

    public function __construct(
        PicoAuthInterface $picoAuth,
        SessionInterface $session,
        PageLockStorageInterface $storage,
        RateLimitInterface $limit
    ) {

        $this->picoAuth = $picoAuth;
        $this->session = $session;
        $this->storage = $storage;
        $this->limit = $limit;

        $this->config = $this->storage->getConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'pageLock';
    }

    /**
     * @inheritdoc
     */
    public function onPicoRequest($url, Request $httpRequest)
    {
        $post = $httpRequest->request;

        if ($post->has("page-key")) {
            // CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"), self::UNLOCK_CSRF_ACTION)) {
                $this->picoAuth->redirectToPage($url);
            }

            // Check if the action is not rate limited
            if (!$this->limit->action("pageLock", false)) {
                $this->session->addFlash("error", $this->limit->getError());
                $this->picoAuth->redirectToPage($url);
            }

            $pageKey = $post->get("page-key");

            $lockId = $this->storage->getLockByURL($url);
            $lockData = $this->storage->getLockById($lockId);

            $keyEncoder = $this->getKeyEncoder($lockData);
            if ($keyEncoder->isValid($lockData["key"], $pageKey)) {
                $unlocked = $this->session->get("unlocked", []);
                $unlocked[] = $lockId;
                $this->session->migrate(true);
                $this->session->set("unlocked", $unlocked);
            } else {
                $this->session->addFlash("error", "The specified key is invalid");
                $this->limit->action("pageLock", true);
            }
            $this->picoAuth->redirectToPage($url);
        }
        
        // Option to lock the unlocked pages, independent from the plugin's Logout
        // (authenticated user stays in the session and must use Logout to destroy session)
        if ($post->has("logout-locks") && $this->picoAuth->isValidCSRF($post->get("csrf_token"))) {
            $this->session->migrate(true);
            $this->session->set("unlocked", []);
        }
        
        // Add unlocked locks to the output, so the theme can show "Close session"
        // if any locks are currently opened, that is optional
        $this->picoAuth->addOutput("locks", $this->session->get("unlocked", []));
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($url)
    {
        $lockId = $this->storage->getLockByURL($url);
        if ($lockId) {
            return $this->isUnlocked($lockId);
        } else {
            return true;
        }
    }

    /**
     * @inheritdoc
     */
    public function denyAccessIfRestricted($url)
    {
        $lockId = $this->storage->getLockByURL($url);
        if ($lockId && !$this->isUnlocked($lockId)) {
            $lockData = $this->storage->getLockById($lockId);
            $this->picoAuth->addOutput("unlock_action", $this->picoAuth->getPico()->getPageUrl($url));
            if (isset($lockData['file'])) {
                $contentDir = $this->picoAuth->getPico()->getConfig('content_dir');
                $this->picoAuth->setRequestFile($contentDir . $lockData['file']);
            } else {
                $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/locked.md');
            }
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
        }
    }

    /**
     * Returns whether the lock is unlocked
     *
     * @param string $lockId Lock identifier
     * @return boolean true if unlocked, false otherwise
     */
    protected function isUnlocked($lockId)
    {
        $unlocked = $this->session->get("unlocked");
        if ($unlocked && in_array($lockId, $unlocked)) {
            return true;
        }

        return false;
    }

    /**
     * Returns encoder instance for the specified lock
     *
     * @param array $lockData Lock data array
     * @return \PicoAuth\Security\Password\Encoder\PasswordEncoderInterface
     * @throws \RuntimeException If the encoder is not resolvable
     */
    protected function getKeyEncoder($lockData)
    {
        if (isset($lockData['encoder']) && is_string($lockData['encoder'])) {
            $name = $lockData['encoder'];
        } else {
            $name = $this->config["encoder"];
        }

        try {
            $instance = $this->picoAuth->getContainer()->get($name);
        } catch (\Exception $e) {
            throw new \RuntimeException("Specified PageLock encoder not resolvable.");
        }

        return $instance;
    }
}
