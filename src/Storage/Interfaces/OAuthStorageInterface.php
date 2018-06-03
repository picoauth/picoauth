<?php

namespace PicoAuth\Storage\Interfaces;

/**
 * Interface for accessing OAuth configuration
 */
interface OAuthStorageInterface
{
    
    /**
     * Get provider configuration
     *
     * @param string $name Provider identifier
     * @return array|null Provider configuration, null if not found
     */
    public function getProviderByName($name);

    /**
     * Get supported providers
     *
     * @return string[]|null
     */
    public function getConfiguration();
    
    /**
     * Get available providers
     *
     * Can be used in the login form to print all available providers
     *
     * @return string[] Array with provider identifiers
     */
    public function getProviderNames();
}
