<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;
use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * The configurator for PageLock
 */
class PageLockConfigurator extends AbstractConfigurator
{
    
    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault(), 0);
        $this->validateGlobalOptions($config);
        $this->validateLocksSection($config);
        $this->validateUrlsSection($config);
        return $config;
    }
    
    /**
     * The default configuration must contain all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "encoder" => "bcrypt",
            "locks" => [],
            "urls" => [],
        );
    }
    
    public function validateGlobalOptions(&$config)
    {
        $this->assertString($config, "encoder");
    }
 
    public function validateLocksSection($config)
    {
        $this->assertArray($config, "locks");
        foreach ($config["locks"] as $lockId => $lockData) {
            if (!is_string($lockId) || $lockId=="") {
                throw new ConfigurationException("Lock identifier must be a string.");
            }
            $this->assertArray($config["locks"], $lockId);
            
            // Mandatory attributes
            $this->assertRequired($lockData, "key");
            $this->assertString($lockData, "key");

            // Optional attributes
            $this->assertString($lockData, "encoder");
            $this->assertString($lockData, "file");
        }
    }
    
    public function validateUrlsSection(&$config)
    {
        $this->assertArray($config, "urls");
        foreach ($config["urls"] as $pageUrl => $ruleData) {
            if (!is_string($pageUrl) || $pageUrl=="") {
                throw new ConfigurationException("Page URL must be a string.");
            }
            $this->assertArray($config["urls"], $pageUrl);
            
            // Add leading /, remove trailing /
            $this->standardizeUrlFormat($config["urls"], $pageUrl);
            
            // Mandatory attributes
            $this->assertRequired($ruleData, "lock");
            $this->assertString($ruleData, "lock");

            // Optional attributes
            $this->assertBool($ruleData, "recursive");
        }
    }
}
