<?php

namespace PicoAuth\Module\Authentication\LocalAuth;

use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Security\Password\Password;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * EditAccount component for LocalAuth
 */
class EditAccount
{

    /**
     * Configuration array for EditAccount
     *
     * @var array
     */
    protected $config;
    
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
     * @var LocalAuthStorageInterface
     */
    protected $storage;

    public function __construct(
        PicoAuthInterface $picoAuth,
        SessionInterface $session,
        LocalAuthStorageInterface $storage
    ) {
    
        $this->picoAuth = $picoAuth;
        $this->session = $session;
        $this->storage = $storage;
    }

    /**
     * Sets the configuration array
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = $config["accountEdit"];
        return $this;
    }

    /**
     * Account page request
     *
     * @param Request $httpRequest
     * @return void
     */
    public function handleAccountPage(Request $httpRequest)
    {
        // Check if the functionality is enabled by the configuration
        if (!$this->config["enabled"]) {
            return;
        }

        $user = $this->picoAuth->getUser();
        $this->picoAuth->addAllowed("account");
        $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/account.md');

        //check form submission
        $post = $httpRequest->request;
        if ($post->has("new_password")
            && $post->has("new_password_repeat")
            && $post->has("old_password")
        ) {
            $newPassword = new Password($post->get("new_password"));
            $newPasswordRepeat = new Password($post->get("new_password_repeat"));
            $oldPassword = new Password($post->get("old_password"));
            $username = $user->getId();

            // CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"))) {
                $this->picoAuth->redirectToPage("account");
            }

            if ($newPassword->get() !== $newPasswordRepeat->get()) {
                $this->session->addFlash("error", "The passwords do not match.");
                $this->picoAuth->redirectToPage("account");
            }

            // The current password check
            $localAuth = $this->picoAuth->getContainer()->get('LocalAuth');
            if (!$localAuth->loginAttempt($username, $oldPassword)) {
                $this->session->addFlash("error", "The current password is incorrect");
                $this->picoAuth->redirectToPage("account");
            }

            // Check password policy
            if (!$localAuth->checkPasswordPolicy($newPassword)) {
                $this->picoAuth->redirectToPage("account");
            }

            // Save user data
            $userData = $this->storage->getUserByName($username);
            $localAuth->userDataEncodePassword($userData, $newPassword);
            $this->storage->saveUser($username, $userData);

            $this->session->addFlash("success", "Password changed successfully.");
            $this->picoAuth->redirectToPage("account");
        }
    }
}
