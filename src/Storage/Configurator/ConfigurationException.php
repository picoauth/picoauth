<?php

namespace PicoAuth\Storage\Configurator;

/**
 * Exception caused by a bad configuration
 */
class ConfigurationException extends \Exception
{

    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Adds a message before the current one
     * @param string $msg
     */
    public function addBeforeMessage($msg)
    {
        $this->message = $msg . " ". $this->message;
    }
    
    /**
     * Converts to string
     * @return string
     */
    public function __toString()
    {
        return $this->message;
    }
}
