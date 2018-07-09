<?php

/**
 * PicoAuth wrapper class
 *
 * The implementation of the plugin itself is in PicoAuthPlugin class.
 * The purpose of this class is only to register the plugin's autoloader
 * if it is not installed in Pico with pico-composer. After that is done,
 * it relies all Pico events to the main class with
 * an overridden {@see PicoAuth::handleEvent()} method.
 *
 * @see \PicoAuth\PicoAuthPlugin
 */
class PicoAuth extends AbstractPicoPlugin
{
   
    /**
     * Pico API version used by this plugin.
     * @var int
     */
    const API_VERSION = 2;

    /**
     * The main class of the plugin
     * @var \PicoAuth\PicoAuthPlugin
     */
    protected $picoAuthPlugin;
    
    /**
     * Constructs a new PicoAuth instance
     *
     * If PicoAuth is not installed as a pico-composer plugin, this will
     * register plugin's own autoloader. This will only work if any shared
     * dependencies between Pico's and PicoAuths's autoloader are
     * are present in the same versions.
     *
     * @param \Pico $pico
     */
    public function __construct(\Pico $pico)
    {
        parent::__construct($pico);
        
        if (!class_exists('\PicoAuth\PicoAuthPlugin', true)) {
            if (is_file(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            } else {
                die("PicoAuth is neither installed as a pico-composer plugin "
                    . "nor it has its own vendor/autoload.php.");
            }
        }
        
        $this->picoAuthPlugin = new \PicoAuth\PicoAuthPlugin($pico);
    }
    
    /**
     * Pico API events pass-through
     *
     * Pico plugin events are sent over to the main PicoAuthPlugin class.
     * {@inheritdoc}
     *
     * @param string $eventName
     * @param array $params
     */
    public function handleEvent($eventName, array $params)
    {
        parent::handleEvent($eventName, $params);
        
        if ($this->isEnabled()) {
            $this->picoAuthPlugin->handleEvent($eventName, $params);
        }
    }

    /**
     * Gets the instance of the main class
     *
     * May be used if other plugins installed in Pico and depending on PicoAuth
     * need to access to the PicoAuthPlugin class.
     *
     * @return \PicoAuth\PicoAuthPlugin
     */
    public function getPlugin()
    {
        return $this->picoAuthPlugin;
    }
}
