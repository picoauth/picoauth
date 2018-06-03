<?php

namespace PicoAuth\Security\RateLimiting;

use PicoAuth\Log\LoggerTrait;
use Psr\Log\LoggerAwareInterface;
use PicoAuth\Storage\Interfaces\RateLimitStorageInterface;

/**
 * Rate Limiter
 */
class RateLimit implements RateLimitInterface, LoggerAwareInterface
{

    use LoggerTrait;

    /**
     * Default net mask applied on IPv4 addresses
     */
    const DEFAULT_NETMASK_IPV4 = 32;
    
    /**
     * Default net mask applied on IPv6 addresses
     */
    const DEFAULT_NETMASK_IPV6 = 48;

    /**
     * Configuration of actions for rate limiting
     *
     * @var array
     */
    protected $actions;
    
    /**
     * Rate limit configuration array
     *
     * @var array
     */
    protected $config;
    
    /**
     * Storage instance
     *
     * @var RateLimitStorageInterface
     */
    protected $storage;
    
    /**
     * An error message
     *
     * @see RateLimit::getError()
     * @see RateLimit::setErrorMessage()
     * @var string
     */
    protected $errorMessage;

    public function __construct(RateLimitStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->config = $this->storage->getConfiguration();
        $this->actions = $this->config["actions"];
    }

    /**
     * @inheritdoc
     */
    public function action($actionName, $increment = true, $params = array())
    {
        // the action is not configured for rate limiting
        if (!isset($this->actions[$actionName])) {
            return true;
        }

        $configOptions = $this->actions[$actionName];

        foreach ($configOptions as $blockType => $config) {
            if (!($entityId = $this->getEntityId($blockType, $config, $params))) {
                continue;
            }

            if ($increment) {
                $limit = $this->incrementCounter($actionName, $blockType, $entityId, $config);
            } else {
                $limit = $this->getLimitFor($actionName, $blockType, $entityId);
            }

            // If the action is not allowed, other potential blockTypes are not evaluated
            if (!$this->isAllowed($limit, $config)) {
                $this->setErrorMessage($config);
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getError()
    {
        return $this->errorMessage;
    }
    
    /**
     * Returns whether the entity is currently rate limited
     *
     * @param array $limit Limit data for the entity
     * @param array $config Limit configuration
     * @return boolean false if the entity is rate limited, true otherwise
     */
    protected function isAllowed($limit, $config)
    {
        if ($limit["cnt"] >= $config["count"]) {
            if (time() > $limit["ts"] + $config["blockDuration"]) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Returns IP subnet string for the client
     *
     * The net mask applied to the IP is used from the configuration supplied
     * to the method, if not present default net mask is used.
     *
     * @param array $config Limit configuration
     * @return string IP subnet string
     */
    protected function getIp($config)
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'];
        if (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $netmask = (isset($config["netmask_IPv4"]))
                ? $config["netmask_IPv4"] : self::DEFAULT_NETMASK_IPV4;
        } else {
            $netmask = (isset($config["netmask_IPv6"]))
                ? $config["netmask_IPv6"] : self::DEFAULT_NETMASK_IPV6;
        }

        $ipSubnet = $this->getSubnet($remoteAddr, $netmask);

        return $ipSubnet;
    }

    /**
     * Applies a given netmask to an IP address
     *
     * Both IPv4 and IPv6 are supported and detected automatically (inet_pton).
     *
     * @param string $ip IP address string
     * @param int $netmask IP netmask to be applied
     * @return string IP string in format: IP/netmask
     * @throws \InvalidArgumentException If the IP or netmask is not valid
     */
    protected function getSubnet($ip, $netmask)
    {
        $binString = @inet_pton($ip);
        if ($binString === false) {
            throw new \InvalidArgumentException("Not a valid IP address.");
        }

        // Length of the IP in bytes (4 or 16)
        $byteLen = mb_strlen($binString, "8bit");
        if (!is_int($netmask) || $netmask < 0 || $netmask > $byteLen * 8) {
            throw new \InvalidArgumentException("Not a valid netmask.");
        }

        for ($byte = $byteLen - 1; ($byte + 1) * 8 > $netmask; --$byte) {
            // Bitlength of a mask for the current byte
            $maskLen = min(8, ($byte + 1) * 8 - $netmask);

            // Create the byte mask of maskLen bits
            $mask = (~((1 << $maskLen) - 1)) & 0xff;

            // Export byte as 'unsigned char' and apply the mask
            $maskedByte = $mask & unpack('C', $binString[$byte])[1];
            $binString[$byte] = pack('C', $maskedByte);
        }

        return inet_ntop($binString) . '/' . $netmask;
    }

    /**
     * Gets an entity identifier
     *
     * Returns a string that will identify the entity
     * in the rate limiting process. It can be a hash of a username/email,
     * or IP subnet of a size defined by the configuration.
     *
     * md5 is applied on a user-supplied input (email, username),
     * collisions or reversibility do not present security concerns.
     *
     * @param string $blockType Blocking type (account,email,ip)
     * @param array $config Configuration array for the specific rate limit
     * @param array $params Additional parameters from the action() call
     * @return string Entity id
     */
    protected function getEntityId($blockType, $config, $params = array())
    {
        $entityId = null;
        if ($blockType === "account" && isset($params["name"])) {
            $entityId = md5($params["name"]);
        } elseif ($blockType === "email" && isset($params["email"])) {
            $entityId = md5($params["email"]);
        } elseif ($blockType === "ip") {
            $entityId = $this->getIp($config);
        }
        return $entityId;
    }

    /**
     * Retrieves a limit array for a given action,type,id
     *
     * @param string $actionName Action id (e.g. "login")
     * @param string $blockType Block type (e.g. "ip")
     * @param string $entityId Entity identifier
     * @return array Limit data array
     */
    protected function getLimitFor($actionName, $blockType, $entityId)
    {
        $limit = $this->storage->getLimitFor($actionName, $blockType, $entityId);
        if ($limit === null) {
            $limit = array("ts" => 0, "cnt" => 0);
        }
        return $limit;
    }

    /**
     * Increments a counter for the specified limit
     *
     * @param string $actionName Action id
     * @param string $blockType Block type
     * @param string $entityId Entity identifier
     * @param array $config Limit configuration array
     * @return array Limit data array before the increment
     */
    protected function incrementCounter($actionName, $blockType, $entityId, $config)
    {
        // Begin an exclusive transaction
        $this->storage->transaction($actionName, $blockType, RateLimitStorageInterface::TRANSACTION_BEGIN);
        
        $limit = $this->getLimitFor($actionName, $blockType, $entityId);

        $time = time();

        $resetCounter =
            // Counter reset after specified timeout since the last action
            ($time > $limit["ts"] + $config["counterTimeout"]) ||

            // Counter reset after blockout delay timeout
            ($limit["cnt"] >= $config["count"] && $time > $limit["ts"] + $config["blockDuration"]);

        if ($resetCounter) {
            $limit["cnt"] = 0;
        }

        $limitBeforeIncrement = $limit;

        // The limit will be reached with this increment
        if ($limit["cnt"] === $config["count"] - 1) {
            $this->logRateLimitReached($actionName, $blockType, $entityId, $config);
        }

        ++$limit["cnt"];
        $limit["ts"] = $time;

        // Update the limit if the entity is not blocked
        if ($limit["cnt"] <= $config["count"]) {
            $this->storage->updateLimitFor($actionName, $blockType, $entityId, $limit);
            if (rand(0, 100) <= $this->config["cleanupProbability"]) {
                $this->storage->cleanup($actionName, $blockType, $config);
            }
            $this->storage->save($actionName, $blockType);
        }
        
        // Close the transaction
        $this->storage->transaction($actionName, $blockType, RateLimitStorageInterface::TRANSACTION_END);
        
        // Returns the limit array before the increment
        return $limitBeforeIncrement;
    }

    /**
     * Logs that the rate limit was reached
     *
     * @param string $actionName
     * @param string $blockType
     * @param string $entityId
     * @param array $config
     */
    protected function logRateLimitReached($actionName, $blockType, $entityId, $config)
    {
        $this->getLogger()->notice(
            "Rate limit of {cnt} reached: {action} for {entity} ({type}).",
            array('cnt' => $config["count"], 'action' => $actionName, 'entity' => $entityId, 'type' => $blockType)
        );
    }

    /**
     * Sets the error message that will be retrievable via {@see RateLimit::getError()}
     *
     * @param array $config Limit configuration
     */
    protected function setErrorMessage($config)
    {
        if (isset($config["errorMsg"])) {
            $msg = $config["errorMsg"];
        } else {
            $msg = "Rate limit exceeded, wait %min% minutes.";
        }

        $replace = array(
            "%min%" => intval(ceil($config["blockDuration"] / 60)),
            "%cnt%" => $config["count"],
        );

        $this->errorMessage = str_replace(array_keys($replace), $replace, $msg);
    }
}
