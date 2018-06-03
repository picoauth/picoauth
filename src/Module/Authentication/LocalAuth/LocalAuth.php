<?php
namespace PicoAuth\Module\Authentication\LocalAuth;

use PicoAuth\Module\AbstractAuthModule;
use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Security\Password\Password;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use PicoAuth\User;
use Psr\Log\LoggerAwareInterface;
use PicoAuth\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authentication with local user accounts
 */
class LocalAuth extends AbstractAuthModule implements LoggerAwareInterface
{
    
    use LoggerTrait;

    const LOGIN_CSRF_ACTION = 'login';

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

    /**
     * The Configuration array
     *
     * @var array
     */
    protected $config;
    
    /**
     * The rate limiter
     *
     * @var RateLimitInterface
     */

    protected $limit;

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

        $this->config = $this->storage->getConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'localAuth';
    }

    /**
     * @inheritdoc
     */
    public function onPicoRequest($url, Request $httpRequest)
    {
        switch ($url) {
            case "login":
                $this->handleLogin($httpRequest);
                break;
            case "register":
                $this->handleRegistration($httpRequest);
                break;
            case "account":
                $this->handleAccountPage($httpRequest);
                break;
            case "password_reset":
                $this->handlePasswordReset($httpRequest);
                break;
        }
    }
    
    /**
     * Get LocalAuth configuration
     *
     * Can be used from twig template when dynamically compositing a login page
     * (e.g. to find out which functions are enabled to show appropriate links)
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Handles a login submission
     *
     * @param Request $httpRequest Login request
     * @return void
     */
    protected function handleLogin(Request $httpRequest)
    {
        $post = $httpRequest->request;
        if (!$post->has("username") || !$post->has("password")) {
            return;
        }

        //CSRF validation
        if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"), self::LOGIN_CSRF_ACTION)) {
            $this->picoAuth->redirectToLogin(null, $httpRequest);
            return;
        }

        $username = strtolower(trim($post->get("username")));
        $password = new Password($post->get("password"));

        //Check if the login action is not rate limited
        if (!$this->limit->action("login", false, array("name" => $username))) {
            $this->session->addFlash("error", $this->limit->getError());
            $this->picoAuth->redirectToLogin(null, $httpRequest);
            return;
        }

        if (!$this->loginAttempt($username, $password)) {
            $this->logInvalidLoginAttempt($username);
            $this->limit->action("login", true, array("name" => $username));
            $this->session->addFlash("error", "Invalid username or password");
            $this->picoAuth->redirectToLogin(null, $httpRequest);
            return;
        } else {
            $userData = $this->storage->getUserByName($username);
            if ($this->needsPasswordRehash($userData)) {
                $this->passwordRehash($username, $password);
            }
            $this->login($username, $userData);
            $this->picoAuth->afterLogin();
        }
    }

    /**
     * Attempt a login with the specified credentials
     *
     * @param string $username Username
     * @param Password $password Password
     * @return bool Whether login was successful
     */
    public function loginAttempt($username, Password $password)
    {
        $userData = $this->storage->getUserByName($username);
        $encoder = $this->getPasswordEncoder($userData);

        $dummy = bin2hex(\random_bytes(32));
        $dummyHash = $encoder->encode($dummy);

        if (!$userData) {
            // The user doesn't exist, dummy call is performed to prevent time analysis
            $encoder->isValid($dummyHash, $password);
            return false;
        }
        
        return $encoder->isValid($userData['pwhash'], $password->get());
    }

    /**
     * Logs the user in
     *
     * @param string $id User identifier
     * @param array $userData User data array
     */
    public function login($id, $userData)
    {
        $this->abortIfExpired($id, $userData);

        $u = new User();
        $u->setAuthenticated(true);
        $u->setAuthenticator($this->getName());
        $u->setId($id);

        if (isset($userData['groups'])) {
            $u->setGroups($userData['groups']);
        }
        if (isset($userData['displayName'])) {
            $u->setDisplayName($userData['displayName']);
        }
        if (isset($userData['attributes'])) {
            foreach ($userData['attributes'] as $key => $value) {
                $u->setAttribute($key, $value);
            }
        }
        $this->picoAuth->setUser($u);
    }

    /**
     * Aborts the current request if a password reset is required
     *
     * Starts a password reset session and redirects to the
     * password reset form.
     *
     * @param string $id User identifier
     * @param array $userData User data array
     */
    protected function abortIfExpired($id, $userData)
    {
        if (isset($userData['pwreset']) && $userData['pwreset']) {
            $this->session->addFlash("error", "Please set a new password.");
            $this->picoAuth->getContainer()->get('PasswordReset')->startPasswordResetSession($id);
            $this->picoAuth->redirectToPage("password_reset");
        }
    }

    /**
     * Returns encoder instance for the specified user
     *
     * If the user data array is not specified, returns the default
     * encoder instance.
     *
     * @param null|array $userData User data array
     * @return \PicoAuth\Security\Password\Encoder\PasswordEncoderInterface
     * @throws \RuntimeException If the encoder is not resolvable
     */
    protected function getPasswordEncoder($userData = null)
    {
        if (isset($userData['encoder']) && is_string($userData['encoder'])) {
            $name = $userData['encoder'];
        } else {
            $name = $this->config["encoder"];
        }

        $container = $this->picoAuth->getContainer();
        
        if (!$container->has($name)) {
            throw new \RuntimeException("Specified LocalAuth encoder is not resolvable.");
        }

        return $container->get($name);
    }

    /**
     * Fills user-data array with the encoded password
     *
     * @param array $userData User data array
     * @param Password $newPassword The password to be encoded
     */
    public function userDataEncodePassword(&$userData, Password $newPassword)
    {
        $encoderName = $this->config["encoder"];
        $encoder = $this->picoAuth->getContainer()->get($encoderName);

        $userData['pwhash'] = $encoder->encode($newPassword->get());
        $userData['encoder'] = $encoderName;
        if (isset($userData['pwreset'])) {
            unset($userData['pwreset']);
        }
    }

    /**
     * Validates the password policy constraints against the supplied password
     *
     * Additionally, a maximum length constraint is added based on the limitations
     * of the current password encoder used for storage.
     *
     * @param Password $password Password string to be checked
     * @return boolean true - passed, false otherwise
     */
    public function checkPasswordPolicy(Password $password)
    {
        $result = true;
        $policy = $this->picoAuth->getContainer()->get("PasswordPolicy");
        $maxAllowedLen = $this->getPasswordEncoder()->getMaxAllowedLen();
        if (is_int($maxAllowedLen) && strlen($password)>$maxAllowedLen) {
            $this->session->addFlash("error", "Maximum length is {$maxAllowedLen}.");
            $result = false;
        }
        if (!$policy->check($password)) {
            $errors = $policy->getErrors();
            foreach ($errors as $error) {
                $this->session->addFlash("error", $error);
            }
            return false;
        }
        
        return $result;
    }

    /**
     * Returns whether a rehash is needed
     *
     * @param array $userData User data array
     * @return boolean true if needed, false otherwise
     */
    protected function needsPasswordRehash(array $userData)
    {
        // Return if password rehashing is not enabled
        if ($this->config["login"]["passwordRehash"] !== true) {
            return false;
        }

        // Password hash is created using a different algorithm than default
        if (isset($userData['encoder']) && $userData['encoder'] !== $this->config["encoder"]) {
            return true;
        }
        $encoder = $this->getPasswordEncoder($userData);

        // If password hash algorithm options have changed
        return $encoder->needsRehash($userData['pwhash']);
    }

    /**
     * Performs a password rehash
     *
     * @param string $username User identifier
     * @param Password $password The password to be resaved
     */
    protected function passwordRehash($username, Password $password)
    {
        $userData = $this->storage->getUserByName($username);
        
        try {
            $this->userDataEncodePassword($userData, $password);
        } catch (\PicoAuth\Security\Password\Encoder\EncoderException $e) {
            // The encoder was changed to one that is not able to store this password
            $this->session->addFlash("error", "Please set a new password.");
            $this->picoAuth->getContainer()->get('PasswordReset')->startPasswordResetSession($username);
            $this->picoAuth->redirectToPage("password_reset");
        }
        
        $this->storage->saveUser($username, $userData);
    }

    /**
     * Checks username validity
     *
     * @param string $name Username to be checked
     * @return boolean true if the username is valid, false otherwise
     */
    protected function isValidUsername($name)
    {
        if (!is_string($name)
            || !$this->storage->checkValidName($name)
            || strlen($name) < $this->config["registration"]["nameLenMin"]
            || strlen($name) > $this->config["registration"]["nameLenMax"]
        ) {
            return false;
        }
        return true;
    }
    
    /**
     * Logs an invalid login attempt
     *
     * @param array $name Username the attempt was for
     */
    protected function logInvalidLoginAttempt($name)
    {
        // Trim logged name to the maximum allowed length
        $max = $this->config["registration"]["nameLenMax"];
        if (strlen($name)>$max) {
            $max = substr($name, 0, $max) . " (trimmed)";
        }
        $this->getLogger()->notice(
            "Invalid login attempt for {name} by {addr}",
            array(
                "name" => $name,
                "addr" => $_SERVER['REMOTE_ADDR'],
            )
        );
    }

    /**
     * On account page request
     *
     * @param Request $httpRequest
     * @return void
     */
    protected function handleAccountPage(Request $httpRequest)
    {
        $user = $this->picoAuth->getUser();
        if (!$user->getAuthenticated()) {
            $this->session->addFlash("error", "Login to access this page.");
            $this->picoAuth->redirectToLogin();
            return;
        }

        // Page for password editing available only to local accounts
        if ($user->getAuthenticator() !== $this->getName()) {
            $this->picoAuth->redirectToPage("index");
            return;
        }

        $editAccount = $this->picoAuth->getContainer()->get('EditAccount');
        $editAccount->setConfig($this->config)
            ->handleAccountPage($httpRequest);
    }

    /**
     * On registration request
     *
     * @param Request $httpRequest
     */
    protected function handleRegistration(Request $httpRequest)
    {
        $registration = $this->picoAuth->getContainer()->get('Registration');
        $registration->setConfig($this->config)
            ->handleRegistration($httpRequest);
    }

    /**
     * On password reset request
     *
     * @param Request $httpRequest
     */
    protected function handlePasswordReset(Request $httpRequest)
    {
        $passwordReset = $this->picoAuth->getContainer()->get('PasswordReset');
        $passwordReset->setConfig($this->config)
            ->handlePasswordReset($httpRequest);
    }
}
