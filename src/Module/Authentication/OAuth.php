<?php
namespace PicoAuth\Module\Authentication;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;
use League\OAuth2\Client\Provider\AbstractProvider;
use PicoAuth\Log\LoggerTrait;
use PicoAuth\Module\AbstractAuthModule;
use PicoAuth\Storage\Interfaces\OAuthStorageInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use PicoAuth\User;
use PicoAuth\Utils;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth 2.0 authentication
 *
 * Uses authorization code grant
 */
class OAuth extends AbstractAuthModule implements LoggerAwareInterface
{

    use LoggerTrait;
    use ArrayAccessorTrait;

    const LOGIN_CSRF_ACTION = 'OAuth';

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
     * @var OAuthStorageInterface
     */
    protected $storage;
    
    /**
     * Configuration array
     *
     * @var array
     */
    protected $config;
    
    /**
     * OAuth 2.0 provider used
     *
     * @var AbstractProvider
     */
    protected $provider;
    
    /**
     * Configuration array for the provider
     *
     * @var array
     */
    protected $providerConfig;

    public function __construct(
        PicoAuthInterface $picoAuth,
        SessionInterface $session,
        OAuthStorageInterface $storage
    ) {
    
        $this->picoAuth = $picoAuth;
        $this->session = $session;
        $this->storage = $storage;
        $this->config = $this->storage->getConfiguration();
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'OAuth';
    }
    
    /**
     * Can be used from twig template when dynamically compositing a login page
     * @return OAuthStorageInterface
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @inheritdoc
     */
    public function onPicoRequest($url, Request $httpRequest)
    {
        $post = $httpRequest->request;

        //SSO login submission
        if ($url === "login" && $post->has("oauth")) {
            //CSRF validation
            if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"), self::LOGIN_CSRF_ACTION)) {
                $this->picoAuth->redirectToLogin(null, $httpRequest);
            }

            $provider = $post->get("oauth");

            // Find the requested provider (case sensitive)
            $providerConfig = $this->storage->getProviderByName($provider);
            if (!$providerConfig) {
                $this->session->addFlash("error", "Requested provider is not available.");
                $this->picoAuth->redirectToLogin(null, $httpRequest);
            }

            $this->initProvider($providerConfig);

            $this->session->set("provider", $provider);

            $this->saveAfterLogin($httpRequest);

            // Starts with retreiving the OAuth authorization code
            $this->startAuthentication();
        } elseif ($url === $this->config["callbackPage"] && $this->isValidCallback($httpRequest)) {
            // Request on the SSO endpoint
            $provider = $this->session->get("provider");
            $this->session->remove("provider");
            $providerConfig = $this->storage->getProviderByName($provider);
            if (!$providerConfig) {
                $this->session->remove("oauth2state");
                throw new \RuntimeException("Provider removed during auth process.");
            }
            $this->initProvider($providerConfig);
            $this->finishAuthentication($httpRequest);
        }
    }

    /**
     * Initializes an instance of the provider from the configuration
     *
     * @param array $providerConfig Configuration array of the selected provider
     * @throws \RuntimeException If the provider is not resolvable
     */
    protected function initProvider($providerConfig)
    {
        $providerClass = $providerConfig['provider'];
        $options = $providerConfig['options'];

        if (!isset($options['redirectUri'])) {
            // Set OAuth 2.0 callback page from the configuration
            $options['redirectUri'] = $this->picoAuth->getPico()->getPageUrl($this->config["callbackPage"]);
        }

        if (!class_exists($providerClass)) {
            throw new \RuntimeException("Provider class $providerClass does not exist.");
        }

        if (!is_subclass_of($providerClass, AbstractProvider::class, true)) {
            throw new \RuntimeException("Class $providerClass is not a League\OAuth2 provider.");
        }

        $this->provider = new $providerClass($options);
        $this->providerConfig = $providerConfig;
    }

    /**
     * Starts the OAuth 2.0 process
     *
     * Redirects to the authorization URL of the selected provider
     */
    protected function startAuthentication()
    {
        $authorizationUrl = $this->provider->getAuthorizationUrl();
        $this->session->migrate(true);
        $this->session->set("oauth2state", $this->provider->getState());
        
        // The final redirect, halts the script
        $this->picoAuth->redirectToPage($authorizationUrl, null, false);
    }

    /**
     * Finishes the OAuth 2.0 process
     *
     * Handles the authorization code response to the authorization request
     * Expects that the Request contains a valid callback, which should
     * be checked by {@see OAuth::isValidCallback()}.
     *
     * @param Request $httpRequest
     */
    protected function finishAuthentication(Request $httpRequest)
    {
        $sessionCode = $this->session->get("oauth2state");
        $this->session->remove("oauth2state");

        // Check that the state from OAuth response matches the one in the session
        if ($httpRequest->query->get("state") !== $sessionCode) {
            $this->onStateMismatch();
        }

        // Returns one of https://tools.ietf.org/html/rfc6749#section-4.1.2.1
        if ($httpRequest->query->has("error")) {
            $this->onOAuthError($httpRequest->query->get("error"));
        }

        // Error not set, but code not present (not an RFC complaint response)
        if (!$httpRequest->query->has("code")) {
            $this->onOAuthError("no_code");
        }

        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $httpRequest->query->get("code"),
            ]);

            $resourceOwner = $this->provider->getResourceOwner($accessToken);

            $this->saveLoginInfo($resourceOwner);
        } catch (IdentityProviderException $e) {
            $this->onOauthResourceError($e);
        }
    }

    /**
     * Gets an attribute from the resource owner
     *
     * @param string $name Attribute name
     * @param \League\OAuth2\Client\Provider\ResourceOwnerInterface $resourceOwner Resource owner instance
     * @return mixed The retrieved value
     */
    protected function getResourceAttribute($name, $resourceOwner)
    {
        // Call resource owner getter first
        $method = "get" . $name;

        if (is_callable(array($resourceOwner, $method))) {
            $res = $resourceOwner->$method();
            return $res;
        } else {
            $resourceArray = $resourceOwner->toArray();
            $res = $this->getValueByKey($resourceArray, $name);
            return $res;
        }
    }

    /**
     * Saves the information from the ResourceOwner
     *
     * @param \League\OAuth2\Client\Provider\ResourceOwnerInterface $resourceOwner
     */
    protected function saveLoginInfo($resourceOwner)
    {
        // Initialize the user
        $u = new User();
        $u->setAuthenticated(true);
        $u->setAuthenticator($this->getName());

        // Get user id from the Resource Owner
        $attrMap = $this->providerConfig['attributeMap'];
        $userIdAttr = $attrMap['userId'];
        $userId = $this->getResourceAttribute($userIdAttr, $resourceOwner);
        $u->setId($userId);
        unset($attrMap['userId']);

        // Get display name from the Resource Owner (if configured)
        if (isset($attrMap['displayName'])) {
            $name = $this->getResourceAttribute($attrMap['displayName'], $resourceOwner);
            $u->setDisplayName($name);
            unset($attrMap['displayName']);
        }

        // Retrieve all other custom attributes from the attributeMap
        foreach ($attrMap as $mapKey => $mapValue) {
            $value = $this->getResourceAttribute($mapValue, $resourceOwner);
            $u->setAttribute($mapKey, $value);
        }

        // Set default droups and default attributes
        $u->setGroups($this->providerConfig['default']['groups']);
        foreach ($this->providerConfig['default']['attributes'] as $key => $value) {
            if (null === $u->getAttribute($key)) {
                $u->setAttribute($key, $value);
            }
        }

        $this->picoAuth->setUser($u);
        $this->picoAuth->afterLogin();
    }

    /**
     * Saves the afterLogin parameter
     *
     * @param Request $httpRequest
     */
    protected function saveAfterLogin(Request $httpRequest)
    {
        $referer = $httpRequest->headers->get("referer", null, true);
        $afterLogin = Utils::getRefererQueryParam($referer, "afterLogin");
        if ($afterLogin && Utils::isValidPageId($afterLogin)) {
            $this->session->set("afterLogin", $afterLogin);
        }
    }

    /**
     * Checks if the request and session have all the required fields for a OAuth 2.0 callback
     *
     * - The session must have "provider" name which is the provider the callback
     *   would be returned from.
     * - The request must have "state" query param as a CSRF prevention.
     * - The session must have "oauth2state" which must be a string
     *   a must be non-empty.
     *
     * @param Request $httpRequest
     * @return bool true if the required fields are present, false otherwise
     */
    protected function isValidCallback(Request $httpRequest)
    {
        return $this->session->has("provider")
            && $httpRequest->query->has("state")
            && $this->session->has("oauth2state")
            && is_string($this->session->get("oauth2state"))
            && (strlen($this->session->get("oauth2state")) > 0);
    }

    /**
     * Logs invalid state in the OAuth 2.0 response
     */
    protected function onStateMismatch()
    {
        $this->logger->warning(
            "OAuth2 response state mismatch: provider: {provider} from {addr}",
            array(
                "provider" => get_class($this->provider),
                "addr" => $_SERVER['REMOTE_ADDR']
            )
        );
        $this->session->remove("oauth2state");
        $this->session->addFlash("error", "Invalid OAuth response.");
        $this->picoAuth->redirectToLogin();
    }

    /**
     * On an OAuth error
     *
     * @param string $errorCode
     */
    protected function onOAuthError($errorCode)
    {
        $errorCode = strlen($errorCode > 100) ? substr($errorCode, 0, 100) : $errorCode;

        $this->logger->notice(
            "OAuth2 error response: code {code}, provider {provider}",
            array(
                "code" => $errorCode,
                "provider" => get_class($this->provider),
            )
        );

        $this->session->addFlash("error", "The provider returned an error ($errorCode)");
        $this->picoAuth->redirectToLogin();
    }

    /**
     * On a Resource owner error
     *
     * @param IdentityProviderException $e
     */
    protected function onOauthResourceError(IdentityProviderException $e)
    {
        $this->logger->critical(
            "OAuth2 IdentityProviderException: {e}, provider {provider}",
            array(
                "e" => $e->getMessage(),
                "provider" => get_class($this->provider),
            )
        );

        $this->session->addFlash("error", "Failed to get an access token or user details.");
        $this->picoAuth->redirectToLogin();
    }
}
