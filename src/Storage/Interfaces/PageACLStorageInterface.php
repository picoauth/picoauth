<?php

namespace PicoAuth\Storage\Interfaces;

/**
 * Interface for accessing PageACL configuration
 */
interface PageACLStorageInterface
{
    
    /**
     * Get configuration array
     *
     * @return array
     */
    public function getConfiguration();
    
    /**
     * Get rule by Pico page URL
     *
     * @param string $url Page url
     * @return array|null Rule data array, null if not found
     */
    public function getRuleByURL($url);
}
