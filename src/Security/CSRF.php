<?php

namespace PicoAuth\Security;

/**
 * PicoAuth CSRF token manager
 *
 * The token consists of two concatenated parts:
 * key and HMAC-SHA256(realToken,key)
 *
 * The realToken is stored in the session under the action identifier.
 * Together with realToken there is also a token generation timestamp
 * for evaluating expired tokens.
 *
 * The above method allows for different but still valid output strings,
 * which makes it impossible to reveal using BREACH attack (if HTTP compression
 * is used, and the site reflects user input) if it is not
 * prevented by other means. [1]
 *
 * 1. GLUCK, Yoel; HARRIS, Neal; PRADO, Angelo.
 * BREACH: reviving the CRIME attack. Unpublished manuscript, 2013.
 */
class CSRF
{

    /**
     * Length of the random part of the token in bytes
     */
    const TOKEN_SIZE = 20;

    /**
     * Default action index when generic token is requested
     */
    const DEFAULT_SELECTOR = '_';

    /**
     * Character used to split token parts
     */
    const TOKEN_DELIMTER = '.';
    
    /**
     * Seconds of token validity
     *
     * Used as a default value if not specified explicitely in {@see CSRF::checkToken()}
     */
    const TOKEN_VALIDITY = 3600;
    
    /**
     * Session index for the CSRF manager
     */
    const SESSION_KEY = 'CSRF';
    
    /**
     * Session manager
     * @var \PicoAuth\Session\SessionInterface
     */
    protected $session;

    /**
     * Constructs CSRF manager with the provided session storage
     * @param \PicoAuth\Session\SessionInterface $session
     */
    public function __construct(\PicoAuth\Session\SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Retrieve a token for the specified action
     * @param string $action An action the token is associated with
     * @param bool $reuse Is the token valid for multiple submissions
     * @return string Token string
     */
    public function getToken($action = null, $reuse = true)
    {
        $tokenStorage = $this->session->get(self::SESSION_KEY, []);

        $index = ($action) ? $action : self::DEFAULT_SELECTOR;
        
        if (!isset($tokenStorage[$index])) {
            $token = bin2hex(random_bytes(self::TOKEN_SIZE));
            $tokenStorage[$index] = array(
                'time' => time(),
                'token' => $token
            );
        } else {
            // Token already exists and is not expired
            $token = $tokenStorage[$index]['token'];
            
            // Update token time
            $tokenStorage[$index]['time'] = time();
        }
        
        $tokenStorage[$index]['reuse'] = $reuse;

        $key = bin2hex(random_bytes(self::TOKEN_SIZE));
        $tokenHMAC = $this->tokenHMAC($token, $key);

        $this->session->set(self::SESSION_KEY, $tokenStorage);

        return $key . self::TOKEN_DELIMTER . $tokenHMAC;
    }

    /**
     * Token validation
     * @param string $string token string to be validated
     * @param string $action action the validated token is associated with
     * @return boolean true on successful validation
     */
    public function checkToken($string, $action = null, $tokenValidity = null)
    {
        // Get token data from session
        $index = ($action) ? $action : self::DEFAULT_SELECTOR;
        $tokenStorage = $this->session->get(self::SESSION_KEY, []);
        if (!isset($tokenStorage[$index])) {
            return false;
        }
        $tokenData = $tokenStorage[$index];

        // Check token expiration
        if ($this->isExpired($tokenData, $tokenValidity)) {
            $this->ivalidateToken($index, $tokenStorage);
            return false;
        }
        
        // Check correct format of received token
        $parts = explode(self::TOKEN_DELIMTER, $string);
        if (count($parts) !== 2) {
            return false;
        }

        // Validate the token
        $trueToken = $tokenData['token'];
        $key = $parts[0];           // A key used to create a keyed hash of the real token
        $tokenHMAC = $parts[1];     // Keyed hash of the real token
        $trueTokenHMAC = $this->tokenHMAC($trueToken, $key);
        $isValid = \hash_equals($trueTokenHMAC, $tokenHMAC);
        
        // Remove if it is a one-time token
        if ($isValid && !$tokenData['reuse']) {
            $this->ivalidateToken($index, $tokenStorage);
        }
        
        return $isValid;
    }

    /**
     * Invalidates tokens for all actions
     * Should be called after any authentication
     */
    public function removeTokens()
    {
        $this->session->remove(self::SESSION_KEY);
    }
    
    /**
     * Checks token time validity
     * @param array $tokenData
     * @return bool true if token is expired (is after validity)
     */
    protected function isExpired(array $tokenData, $tokenValidity = null)
    {
        return time() >
            $tokenData['time'] + (($tokenValidity!==null) ? $tokenValidity : self::TOKEN_VALIDITY);
    }

    /**
     * The real token is used as a key in the hash_hmac to create a keyed
     * hash of the $key
     * @param string $token
     * @param string $key
     * @return string
     */
    protected function tokenHMAC($token, $key)
    {
        return hash_hmac('sha256', $key, $token, false);
    }
    
    /**
     * Invalidates the specific token by unsetting it from the tokenStorage
     * array in the session.
     * @param string $index Token index
     * @param array $tokenStorage Token storage array with all tokens
     */
    protected function ivalidateToken($index, array &$tokenStorage)
    {
        unset($tokenStorage[$index]);
        $this->session->set(self::SESSION_KEY, $tokenStorage);
    }
}
