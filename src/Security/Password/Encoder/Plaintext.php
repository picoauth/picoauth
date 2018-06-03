<?php

namespace PicoAuth\Security\Password\Encoder;

/**
 * Plaintext encoder
 *
 * Included mainly for unit testing, usage for any password storage
 * is discouraged.
 */
class Plaintext implements PasswordEncoderInterface
{

    /**
     * The maximum length
     */
    const MAX_LEN = 4096;
    
    /**
     * Case sensitivity option
     *
     * @var bool
     */
    protected $ignoreCase = false;

    public function __construct(array $options = [])
    {
        if (isset($options['ignoreCase'])) {
            if (!is_bool($options['ignoreCase'])) {
                throw new \InvalidArgumentException('Parameter ignoreCase must be a boolean.');
            }
            $this->ignoreCase = $options['ignoreCase'];
        }
    }

    /**
     * @inheritdoc
     */
    public function encode($rawPassword)
    {
        if (strlen($rawPassword) > self::MAX_LEN) {
            throw new EncoderException("Invalid length, maximum is ".self::MAX_LEN.".");
        }
        return $rawPassword;
    }

    /**
     * @inheritdoc
     */
    public function isValid($encodedPassword, $rawPassword)
    {
        if (strlen($rawPassword) > self::MAX_LEN) {
            return false;
        }
        if ($this->ignoreCase) {
            return hash_equals(strtolower($encodedPassword), strtolower($rawPassword));
        } else {
            return hash_equals($encodedPassword, $rawPassword);
        }
    }

    /**
     * @inheritdoc
     */
    public function needsRehash($encodedPassword)
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public function getMaxAllowedLen()
    {
        return self::MAX_LEN;
    }
}
