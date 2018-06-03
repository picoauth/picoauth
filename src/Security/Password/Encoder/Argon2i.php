<?php

namespace PicoAuth\Security\Password\Encoder;

/**
 * Argon2i
 *
 * PHP RFC: https://wiki.php.net/rfc/argon2_password_hash
 */
class Argon2i implements PasswordEncoderInterface
{

    /**
     * The maximum length
     *
     * Not a limitation of the algorithm, but allowing very long passwords
     * could lead to DoS using the computation difficulty of the algorithm.
     */
    const MAX_LEN = 4096;
    
    /**
     * The array with options
     *
     * @var array
     */
    protected $options;

    public function __construct(array $options = [])
    {
        if (!defined('PASSWORD_ARGON2I')) {
            throw new \RuntimeException("ARGON2I is not supported.");
        }
        $defaults = [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
        ];
        $options += $defaults;
        
        $this->validateOptions($options);
        
        $this->options = $options;
    }

    /**
     * @inheritdoc
     */
    public function encode($rawPassword)
    {
        if (strlen($rawPassword) > self::MAX_LEN) {
            throw new EncoderException("Invalid length, maximum is ".self::MAX_LEN.".");
        }
        return password_hash($rawPassword, PASSWORD_ARGON2I, $this->options);
    }

    /**
     * @inheritdoc
     */
    public function isValid($encodedPassword, $rawPassword)
    {
        if (strlen($rawPassword) > self::MAX_LEN) {
            return false;
        }
        return password_verify($rawPassword, $encodedPassword);
    }

    /**
     * @inheritdoc
     */
    public function needsRehash($encodedPassword)
    {
        return password_needs_rehash($encodedPassword, PASSWORD_ARGON2I, $this->options);
    }
    
    /**
     * Validates algorithm options
     *
     * From the algorithm's reference:
     * https://password-hashing.net/argon2-specs.pdf
     *
     * @param array $options
     * @throws \InvalidArgumentException
     */
    private function validateOptions($options)
    {
        if ($options['threads'] < 1) {
            throw new \InvalidArgumentException('Number of threads must be positive.');
        }
        
        if ($options['time_cost'] < 1) {
            throw new \InvalidArgumentException('Time cost must be positive.');
        }
        
        if ($options['memory_cost'] < $options['threads']*8) {
            throw new \InvalidArgumentException('Memory cost must be number of kilobytes from 8*threads.');
        }
    }

    /**
     * @inheritdoc
     */
    public function getMaxAllowedLen()
    {
        return self::MAX_LEN;
    }
}
