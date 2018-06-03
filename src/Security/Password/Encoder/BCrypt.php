<?php

namespace PicoAuth\Security\Password\Encoder;

/**
 * BCrypt
 */
class BCrypt implements PasswordEncoderInterface
{

    /**
     * The maximum allowed length
     *
     * The BCrypt algorithm does not use characters after this length.
     */
    const MAX_LEN = 72;
    
    /**
     * The options array
     *
     * @var array
     */
    protected $options;

    public function __construct(array $options = [])
    {
        if (isset($options['cost'])) {
            if ($options['cost'] < 4 || $options['cost'] > 31) {
                throw new \InvalidArgumentException('Cost must be in the range of 4-31.');
            }
        }
        $defaults = array(
            'cost' => 10
        );
        $options += $defaults;
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
        return \password_hash($rawPassword, PASSWORD_BCRYPT, $this->options);
    }

    /**
     * @inheritdoc
     */
    public function isValid($encodedPassword, $rawPassword)
    {
        if (strlen($rawPassword) > self::MAX_LEN) {
            return false;
        }
        return \password_verify($rawPassword, $encodedPassword);
    }

    /**
     * @inheritdoc
     */
    public function needsRehash($encodedPassword)
    {
        return password_needs_rehash($encodedPassword, PASSWORD_BCRYPT, $this->options);
    }

    /**
     * @inheritdoc
     */
    public function getMaxAllowedLen()
    {
        return self::MAX_LEN;
    }
}
