<?php

namespace PicoAuth\Module\Authentication\LocalAuth;

use PicoAuth\Log\LoggerTrait;
use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Security\Password\Password;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Registration component for LocalAuth
 */
class Registration implements LoggerAwareInterface
{

    use LoggerTrait;

    const REGISTER_CSRF_ACTION = 'register';

    /**
     * The password reser request
     *
     * @var Request
     */
    protected $httpRequest;
    
    /**
     * The registration configuration array
     *
     * @var array
     */
    protected $config;
    
    /**
     * Instance of a rate limiter
     *
     * @var RateLimitInterface
     */
    protected $limit;
    
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
        LocalAuthStorageInterface $storage,
        RateLimitInterface $limit
    ) {
    

        $this->picoAuth = $picoAuth;
        $this->session = $session;
        $this->storage = $storage;
        $this->limit = $limit;
    }

    /**
     * Sets the LocalAuth configuration array
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = $config["registration"];
        return $this;
    }

    /**
     * On a registration request
     *
     * @return void
     */
    public function handleRegistration(Request $httpRequest)
    {
        // Abort if disabled
        if (!$this->config["enabled"]) {
            return;
        }

        $user = $this->picoAuth->getUser();
        if ($user->getAuthenticated()) {
            $this->picoAuth->redirectToPage("index");
        }

        $this->picoAuth->addAllowed("register");
        $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/register.md');

        // Check the form submission
        $post = $httpRequest->request;
        if ($post->has("username")
            && $post->has("email")
            && $post->has("password")
            && $post->has("password_repeat")
        ) {
            // CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"), self::REGISTER_CSRF_ACTION)) {
                $this->picoAuth->redirectToPage("register");
            }
            
            // Abort if a limit for maximum users is exceeded
            $this->assertLimits();

            // Registration fields
            $reg = array(
                "username" => strtolower(trim($post->get("username"))),
                "email" => trim($post->get("email")),
                "password" => new Password($post->get("password")),
                "passwordRepeat" => new Password($post->get("password_repeat")),
            );

            $isValid = $this->validateRegistration($reg);

            if ($isValid) {
                // Check if the action is not rate limited
                if (!$this->limit->action("registration")) {
                    $this->session->addFlash("error", $this->limit->getError());
                    $this->picoAuth->redirectToPage("register");
                }

                $this->logSuccessfulRegistration($reg);

                $userData = array('email' => $reg["email"]);

                $localAuth = $this->picoAuth->getContainer()->get('LocalAuth');
                $localAuth->userDataEncodePassword($userData, $reg["password"]);

                $this->storage->saveUser($reg["username"], $userData);

                $this->session->addFlash("success", "Registration completed successfully, you can now log in.");
                $this->picoAuth->redirectToLogin();
            } else {
                // Prefill the old values to the form
                $this->session->addFlash("old", array(
                    'username' => $reg["username"],
                    'email' => $reg["email"]
                ));

                // Redirect back and display errors
                $this->picoAuth->redirectToPage("register");
            }
        }
    }

    /**
     * Validates the submitted registration
     *
     * @param array $reg Registration array
     * @return boolean true if the registration is valid and can be saved, false otherwise
     */
    protected function validateRegistration(array $reg)
    {
        $isValid = true;

        // Username format
        try {
            $this->storage->checkValidName($reg["username"]);
        } catch (\RuntimeException $e) {
            $isValid = false;
            $this->session->addFlash("error", $e->getMessage());
        }

        // Username length
        $min = $this->config["nameLenMin"];
        $max = $this->config["nameLenMax"];
        if (strlen($reg["username"]) < $min || strlen($reg["username"]) > $max) {
            $isValid = false;
            $this->session->addFlash(
                "error",
                sprintf("Length of a username must be between %d-%d characters.", $min, $max)
            );
        }

        // Email format
        if (!filter_var($reg["email"], FILTER_VALIDATE_EMAIL)) {
            $isValid = false;
            $this->session->addFlash("error", "Email address does not have a valid format.");
        }

        // Email unique
        if (null !== $this->storage->getUserByEmail($reg["email"])) {
            $isValid = false;
            $this->session->addFlash("error", "This email is already in use.");
        }

        // Password repeat matches
        if ($reg["password"]->get() !== $reg["passwordRepeat"]->get()) {
            $isValid = false;
            $this->session->addFlash("error", "The passwords do not match.");
        }

        // Check password policy
        $localAuth = $this->picoAuth->getContainer()->get('LocalAuth');
        if (!$localAuth->checkPasswordPolicy($reg["password"])) {
            $isValid = false;
        }

        // Username unique
        if ($this->storage->getUserByName($reg["username"]) !== null) {
            $isValid = false;
            $this->session->addFlash("error", "The username is already taken.");
        }

        return $isValid;
    }

    /**
     * Logs successful registration
     *
     * @param array $reg Registration array
     */
    protected function logSuccessfulRegistration(array $reg)
    {
        $this->getLogger()->info(
            "New registration: {name} ({email}) from {addr}",
            array(
                "name" => $reg["username"],
                "email" => $reg["email"],
                "addr" => $_SERVER['REMOTE_ADDR']
            )
        );
        
        // Log the amount of users on each 10% of the maximum capacity
        $max = $this->config["maxUsers"];
        $count = $this->storage->getUsersCount()+1;
        if ($count % ceil($max/10) === 0) {
            $percent = intval($count/ceil($max/100));
            $this->getLogger()->warning(
                "The amount of users has reached {percent} of the maximum capacity {max}.",
                array(
                    "percent" => $percent,
                    "max" => $max
                )
            );
        }
    }
    
    /**
     * Aborts the registration if the limit of an amount of users is reached
     */
    protected function assertLimits()
    {
        if ($this->storage->getUsersCount() >= $this->config["maxUsers"]) {
            $this->session->addFlash("error", "New registrations are currently disabled.");
            $this->picoAuth->redirectToPage("register");
        }
    }
}
