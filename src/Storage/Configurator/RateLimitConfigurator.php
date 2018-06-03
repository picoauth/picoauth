<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;
use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * The configurator for RateLimit
 */
class RateLimitConfigurator extends AbstractConfigurator
{
    
    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault(), 1);
        $this->validateGlobalOptions($config);
        $this->validateActionsSection($config);
        return $config;
    }
    
    /**
     * The default configuration must contain all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "cleanupProbability" => 25,
            "actions" => array(
                "login" => array(
                    "ip" => array(
                        "count" => 50,
                        "counterTimeout" => 43200,
                        "blockDuration" => 900,
                        "errorMsg" => "Amount of failed attempts exceeded, wait %min% minutes.",
                    ),
                    "account" => array(
                        "count" => 10,
                        "counterTimeout" => 43200,
                        "blockDuration" => 900,
                        "errorMsg" => "Amount of failed attempts exceeded, wait %min% minutes.",
                    )
                ),
                "passwordReset" => array(
                    "email" => array(
                        "count" => 2,
                        "counterTimeout" => 86400,
                        "blockDuration" => 86400,
                        "errorMsg" => "Maximum of %cnt% reset emails were sent, check your inbox.",
                    ),
                    "ip" => array(
                        "count" => 10,
                        "counterTimeout" => 86400,
                        "blockDuration" => 86400,
                        "errorMsg" => "Amount of maximum submissions exceeded, wait %min% minutes.",
                    ),
                ),
                "registration" => array(
                    "ip" => array(
                        "count" => 2,
                        "blockDuration" => 86400,
                        "errorMsg" => "Amount of maximum submissions exceeded, wait %min% minutes.",
                    ),
                ),
                "pageLock" => array(
                    "ip" => array(
                        "count" => 10,
                        "blockDuration" => 1800,
                    ),
                ),
            )
        );
    }
    
    public function validateGlobalOptions(&$config)
    {
        $this->assertInteger($config, "cleanupProbability", 0, 100);
    }
 
    public function validateActionsSection($config)
    {
        $this->assertArray($config, "actions");
        foreach ($config["actions"] as $actionName => $limitMethods) {
            if (!is_string($actionName) || $actionName=="") {
                throw new ConfigurationException("Action identifier must be a string.");
            }
            $this->assertArray($config["actions"], $actionName);
            
            try {
                $this->validateLimitMethodsSection($limitMethods);
            } catch (ConfigurationException $e) {
                $e->addBeforeMessage("Invalid rateLimit data for $actionName:");
                throw $e;
            }
        }
    }
    
    public function validateLimitMethodsSection(&$config)
    {
        foreach ($config as $methodName => $limitData) {
            if (!is_string($methodName) || $methodName=="") {
                throw new ConfigurationException("Limit method identifier must be a string.");
            }
            $this->assertArray($config, $methodName);
            
            // Mandatory attributes
            $this->assertRequired($limitData, "count");
            $this->assertInteger($limitData, "count", 0);
            $this->assertRequired($limitData, "blockDuration");
            $this->assertInteger($limitData, "blockDuration", 0);
            
            // Set counterTimeout to blockDuration if not specified
            if (!isset($limitData["counterTimeout"])) {
                $limitData["counterTimeout"] = $limitData["blockDuration"];
            } else {
                $this->assertInteger($limitData, "counterTimeout", 0);
            }
            
            // Optional attributes
            $this->assertString($limitData, "errorMsg");
            $this->assertInteger($limitData, "netmask_IPv4", 0, 32);
            $this->assertInteger($limitData, "netmask_IPv6", 0, 128);
        }
    }
}
