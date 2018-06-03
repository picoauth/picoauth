<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;
use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * The configurator for PageACL
 */
class PageACLConfigurator extends AbstractConfigurator
{
    
    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault(), 0);
        $this->validateAccessRules($config);
        return $config;
    }
    
    /**
     * The default configuration must contain all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "access" => [],
        );
    }
 
    protected function validateAccessRules(&$config)
    {
        $this->assertArray($config, "access");
        foreach ($config["access"] as $pageUrl => $ruleData) {
            if (!is_string($pageUrl) || $pageUrl=="") {
                throw new ConfigurationException("Page URL must be a string.");
            }
            $this->assertArray($config["access"], $pageUrl);
            
            // Add leading /, remove trailing /
            $this->standardizeUrlFormat($config["access"], $pageUrl);
            
            // Optional attributes
            $this->assertBool($ruleData, "recursive");
            
            $this->assertArrayOfStrings($ruleData, "users");
            $this->assertArrayOfStrings($ruleData, "groups");
        }
    }
}
