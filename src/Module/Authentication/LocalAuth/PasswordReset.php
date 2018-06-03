<?php

namespace PicoAuth\Module\Authentication\LocalAuth;

use PicoAuth\Log\LoggerTrait;
use PicoAuth\Mail\MailerInterface;
use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Security\Password\Password;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PasswordReset component for LocalAuth
 */
class PasswordReset implements LoggerAwareInterface
{

    use LoggerTrait;

    /**
     * Mailer instance
     *
     * @see PasswordReset::setsetMailer()
     * @var \PicoAuth\Mail\MailerInterface
     */
    protected $mailer;

    /**
     * The password reser request
     *
     * @var Request
     */
    protected $httpRequest;
    
    /**
     * The password reset configuration array
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
     * Sets a Mailer instance
     *
     * @param MailerInterface $mailer
     * @return $this
     */
    public function setMailer(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
        return $this;
    }

    /**
     * Sets the LocalAuth configuration array
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = $config["passwordReset"];
        return $this;
    }
    
    /**
     * On a password reset request
     *
     * @return void
     */
    public function handlePasswordReset(Request $httpRequest)
    {
        $this->httpRequest = $httpRequest;

        // Check if a valid reset link is present
        $this->checkResetLink();

        // Check if the user already has a password reset session
        $resetData = $this->session->get("pwreset");

        if ($resetData === null) {
            $this->beginPasswordReset();
        } else {
            $this->finishPasswordReset($resetData);
        }
    }

    /**
     * The first stage of a password reset process
     *
     * Shows a form to request a password reset and processes
     * its submission (sends password reset link on success)
     *
     * @return void
     */
    protected function beginPasswordReset()
    {
        // Check if password reset is enabled
        if (!$this->config["enabled"]) {
            return;
        }
        $this->picoAuth->addAllowed("password_reset");
        $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/pwbeginreset.md');

        if (count($this->session->getFlash('_pwresetsent'))) {
            $this->picoAuth->addOutput("resetSent", true);
            return;
        }

        $post = $this->httpRequest->request;
        if ($post->has("reset_email")) {
            // CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"))) {
                $this->picoAuth->redirectToPage("password_reset");
            }

            $email = trim($post->get("reset_email"));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->session->addFlash("error", "Email address does not have a valid format.");
                $this->picoAuth->redirectToPage("password_reset");
            }

            // Check if the action is not rate limited
            if (!$this->limit->action("passwordReset", true, array("email" => $email))) {
                $this->session->addFlash("error", $this->limit->getError());
                $this->picoAuth->redirectToPage("password_reset");
            }

            if ($userData = $this->storage->getUserByEmail($email)) {
                $this->sendResetMail($userData);
            }

            // Always display a message with success
            $this->session->addFlash("_pwresetsent", true);
            $this->session->addFlash("success", "Reset link sent via email.");
            $this->picoAuth->redirectToPage("password_reset");
        }
    }

    /**
     * The second stage of a password reset process
     *
     * Shows a password reset form and processes its submission
     *
     * @param array $resetData The resetData array with a fixed structure defined
     *                         in {@see PasswordReset::startPasswordResetSession()}
     */
    protected function finishPasswordReset(array $resetData)
    {
        if (time() > $resetData['validity']) {
            $this->session->remove("pwreset");
            $this->session->addFlash("error", "Page validity expired, please try again.");
            $this->picoAuth->redirectToLogin();
        }

        $this->picoAuth->addOutput("isReset", true);
        $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/pwreset.md');

        // Check the form submission
        $post = $this->httpRequest->request;
        if ($post->has("new_password")
            && $post->has("new_password_repeat")
        ) {
            $newPassword = new Password($post->get("new_password"));
            $newPasswordRepeat = new Password($post->get("new_password_repeat"));
            $username = $resetData['user'];

            // CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"))) {
                $this->picoAuth->redirectToPage("password_reset");
            }

            if ($newPassword->get() !== $newPasswordRepeat->get()) {
                $this->session->addFlash("error", "The passwords do not match.");
                $this->picoAuth->redirectToPage("password_reset");
            }

            // Check password policy
            $localAuth = $this->picoAuth->getContainer()->get('LocalAuth');
            if (!$localAuth->checkPasswordPolicy($newPassword)) {
                $this->picoAuth->redirectToPage("password_reset");
            }

            // Remove pwreset record on successful submission
            $this->session->remove("pwreset");

            // Save the new userdata
            $userData = $this->storage->getUserByName($username);
            $localAuth->userDataEncodePassword($userData, $newPassword);
            $this->storage->saveUser($username, $userData);
            $this->logPasswordReset($username);

            $localAuth->login($username, $userData);
            $this->picoAuth->afterLogin();
        }
    }

    /**
     * Validates a password reset link
     *
     * If a valid password reset link was provided, starts a password reset
     * session and redirects to a password change form
     *
     * @return void
     */
    protected function checkResetLink()
    {
        // Check if the reset links are enabled, if token is present,
        // if it has a valid format and length
        if (!$this->config["enabled"]
            || !($token = $this->httpRequest->query->get("confirm", false))
            || !preg_match("/^[a-f0-9]+$/", $token)
            || strlen($token) !== 2*($this->config["tokenIdLen"]+$this->config["tokenLen"])) {
            return;
        }

        // Delete the active password reset session, if set
        $this->session->remove("pwreset");
        
        // Split token parts
        $tokenId = substr($token, 0, 2 * $this->config["tokenIdLen"]);
        $verifier = substr($token, 2 * $this->config["tokenIdLen"]);

        // Validate token timeout
        $tokenData = $this->storage->getResetToken($tokenId);

        // Token not found or expired
        if (!$tokenData || time() > $tokenData['valid']) {
            $this->session->addFlash("error", "Reset link has expired.");
            $this->getLogger()->warning("Bad reset token {t} from {addr}", [$token, $_SERVER['REMOTE_ADDR']]);
            $this->picoAuth->redirectToPage("password_reset");
        }

        if (hash_equals($tokenData['token'], hash('sha256', $verifier))) {
            $this->session->addFlash("success", "Please set a new password.");
            $this->startPasswordResetSession($tokenData['user']);
            $this->logResetLinkVisit($tokenData);
            $this->picoAuth->redirectToPage("password_reset");
        }
    }

    /**
     * Starts a password reset session
     *
     * A password reset session is active when session key pwreset is present,
     * which contains user identifier of the user who the reset session is valid
     * for and an expiration date after which the password session cannot be used
     * to change a password.
     *
     * @param string $user The user identifier
     */
    public function startPasswordResetSession($user)
    {
        $this->session->migrate(true);
        $this->session->set("pwreset", array(
            'user' => $user,
            'validity' => time() + $this->config["resetTimeout"]
        ));
    }

    protected function createResetToken($username)
    {
        // Get the reset token
        $tokenId = bin2hex(random_bytes($this->config["tokenIdLen"]));
        $verifier = bin2hex(random_bytes($this->config["tokenLen"]));

        $url = $this->picoAuth->getPico()->getPageUrl("password_reset", array(
            'confirm' => $tokenId . $verifier
        ));

        $tokenData = array(
            'token' => hash('sha256', $verifier),
            'user' => $username,
            'valid' => time() + $this->config["tokenValidity"]
        );

        $this->storage->saveResetToken($tokenId, $tokenData);

        return $url;
    }
            
    /**
     * Sends a password reset link
     *
     * @param array $userData Userdata array of an existing user
     * @return void
     */
    protected function sendResetMail($userData)
    {
        if (!$this->mailer) {
            $this->getLogger()->critical("Sending mail but no mailer is set!");
            return;
        }
        
        $url = $this->createResetToken($userData['name']);
               
        // Replaces Pico-specific placeholders (like %site_title%)
        $message = $this->picoAuth->getPico()->substituteFileContent($this->config["emailMessage"]);
        $subject = $this->picoAuth->getPico()->substituteFileContent($this->config["emailSubject"]);
        
        // Replaces placeholders in the configured email message template
        $message = str_replace("%url%", $url, $message);
        $message = str_replace("%username%", $userData['name'], $message);
        
        $this->mailer->setup();
        $this->mailer->setTo($userData['email']);
        $this->mailer->setSubject($subject);
        $this->mailer->setBody($message);
        
        if (!$this->mailer->send()) {
            $this->getLogger()->critical("Mailer error: {e}", ["e" => $this->mailer->getError()]);
        } else {
            $this->getLogger()->info("PwReset email sent to {email}", ["email" => $userData['email']]);
        }
    }

    /**
     * Logs a valid reset link visit
     *
     * @param array $tokenData Reset token data array
     */
    protected function logResetLinkVisit(array $tokenData)
    {
        $this->getLogger()->info(
            "Valid pwReset link for {name} visited by {addr}",
            array(
                "name" => $tokenData['user'],
                "addr" => $_SERVER['REMOTE_ADDR'],
            )
        );
    }

    /**
     * Logs a completed password reset
     *
     * @param string $username
     */
    protected function logPasswordReset($username)
    {
        $this->getLogger()->info(
            "Completed password reset for {name} by {addr}",
            array(
                "name" => $username,
                "addr" => $_SERVER['REMOTE_ADDR'],
            )
        );
    }
}
