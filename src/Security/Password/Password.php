<?php

namespace PicoAuth\Security\Password;

/**
 * The class for Password manipulation
 *
 * Prevents password disclosure in a stack trace if "display_errors"
 * is enabled and an unhandled exception occurs.
 */
class Password
{

    private $value;

    /**
     * Create Password instance from string
     * @param string $string
     */
    public function __construct($string)
    {
        $this->value = $string;
    }

    /**
     * Get Password value
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * Get Password value
     * @return string
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Prevent var_dump() of Password value
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'value' => '*hidden*',
        ];
    }
}
