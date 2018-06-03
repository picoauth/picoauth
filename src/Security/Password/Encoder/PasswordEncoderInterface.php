<?php

namespace PicoAuth\Security\Password\Encoder;

/**
 * Interface for a password representation
 */
interface PasswordEncoderInterface
{

    /**
     * Returns representation of the raw password suitable for storage
     * Generally, a password hashing algorithm is applied
     * @throws EncoderException if the rawPassword cannot be processed
     * @param string $rawPassword
     * @return string Resulting representation
     */
    public function encode($rawPassword);

    /**
     * Returns true if the supplied $rawPassword is valid
     * @param string $encodedPassword
     * @param string $rawPassword
     * @return bool true if validated successfully
     */
    public function isValid($encodedPassword, $rawPassword);

    /**
     * Returns if the password needs rehashing
     *
     * This is usually the case when algorithm options have changed since
     * the creation of the original hash and therefore they may not meet
     * the cost requirements.
     * @param string $encodedPassword
     * @return bool If the password needs to be rehashed
     */
    public function needsRehash($encodedPassword);
    
    /**
     * Returns the maximum length of rawPassword that can be used with the encoder.
     * This can be either algorithm limitation (Bcrypt limits to 72) or a protection
     * against a DoS by allowing time-consuming computation of unnecessarily long
     * raw strings.
     * @return int|null Maximum allowed strlen, null if unlimited
     */
    public function getMaxAllowedLen();
}
