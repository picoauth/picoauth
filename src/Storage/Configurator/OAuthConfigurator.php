<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;
use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * The configurator for OAuth
 */
class OAuthConfigurator extends AbstractConfigurator
{
    
    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault(), 1);
        $this->validateGlobalOptions($config);
        $this->validateProvidersSection($config);
        return $config;
    }
    
    /**
     * The default configuration must contain all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "callbackPage" => "oauth_callback",
            "providers" => [],
        );
    }
    
    public function validateGlobalOptions(&$config)
    {
        $this->assertString($config, "callbackPage");
        $config["callbackPage"]=trim($config["callbackPage"], "/");
    }
 
    public function validateProvidersSection(&$config)
    {
        // The 3 mandatory string values have default=0,
        // so they won't be allowed unless they are set.
        $requiredFileds = array(
            "provider" => "\League\OAuth2\Client\Provider\GenericProvider",
            "options" => array(
                "clientId" => 0,
                "clientSecret" => 0,
            ),
            "attributeMap" => array(
                "userId" => "id"
            ),
            "default" => array(
                "groups" => [],
                "attributes" => []
            )
        );
        foreach ($config["providers"] as $name => $rawProviderData) {
            $this->assertProviderName($name);
            $this->assertArray($config["providers"], $name);
            $providerData = $config["providers"][$name] = $this->applyDefaults($rawProviderData, $requiredFileds, 2);
            $this->assertString($providerData, "provider");
            $this->assertString($providerData["options"], "clientId");
            $this->assertString($providerData["options"], "clientSecret");
            foreach ($providerData["attributeMap"] as $attrName => $mappedName) {
                if (!is_string($attrName) || !is_string($mappedName)) {
                    throw new ConfigurationException("Provider attribute map can contain only strings.");
                }
            }
            $this->assertArrayOfStrings($providerData["default"], "groups");
        }
    }
    
    public function assertProviderName($name)
    {
        if (!is_string($name) || $name==="") {
            throw new ConfigurationException("Provider name must be a non-empty string.");
        }
    }
}
