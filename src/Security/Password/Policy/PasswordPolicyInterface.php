<?php

namespace PicoAuth\Security\Password\Policy;

use PicoAuth\Security\Password\Password;

/**
 * Interface for enforcing password constraints
 */
interface PasswordPolicyInterface
{

    /**
     * Checks validity of the given string against the constraints of the Policy
     * @param Password $password
     * @return bool
     */
    public function check(Password $password);

    /**
     * Returns an array of errors from the last check() call
     * @return string[]
     */
    public function getErrors();
}
