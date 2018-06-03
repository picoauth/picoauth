<?php
namespace PicoAuth\Module;

/**
 * Abstract class for plugin modules
 */
abstract class AbstractAuthModule
{

    /**
     * Handles an event
     *
     * @param string $eventName Event name
     * @param array $params Event parameters
     * @return mixed Call return value or NULL if not called
     */
    public function handleEvent($eventName, array $params)
    {
        if (method_exists($this, $eventName)) {
            return call_user_func_array(array($this, $eventName), $params);
        }
        return null;
    }
    
    /**
     * Get module name
     *
     * @return string Module name
     */
    abstract public function getName();
}
