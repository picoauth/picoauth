<?php

namespace PicoAuth\Security\Password\Encoder;

/**
 * Encoder exception
 */
class EncoderException extends \Exception
{

    /**
     * Creates an Encoder exception
     *
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
