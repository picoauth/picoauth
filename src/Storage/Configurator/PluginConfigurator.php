<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;

/**
 * The configurator for the plugin
 *
 * This is the generic part of the plugin's configuration contained in the Pico's
 * configuration file (usually config.yml)
 */
class PluginConfigurator extends AbstractConfigurator
{
    protected $config;
    
    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault(), 0);
        $this->validateGlobalOptions($config);
        return $config;
    }
    
    /**
     * The default configuration contains all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "authModules" => [
                "Installer"
            ],
            "afterLogin" => "index",
            "afterLogout" => "index",
            "alterPageArray" => false,
            "sessionInterval" => 900,
            "sessionTimeout" => 7200,
            "sessionIdle" => 3600,
            "rateLimit" => false,
            "debug" => false,
        );
    }
    
    public function validateGlobalOptions($config)
    {
        $this->assertArrayOfStrings($config, "authModules");
        $this->assertString($config, "afterLogin");
        $this->assertString($config, "afterLogout");
        $this->assertBool($config, "alterPageArray");
        $this->assertBool($config, "rateLimit");
        $this->assertBool($config, "debug");
        $this->assertIntOrFalse($config, "sessionInterval", 0);
        $this->assertIntOrFalse($config, "sessionTimeout", 0);
        $this->assertIntOrFalse($config, "sessionIdle", 0);
    }
}
