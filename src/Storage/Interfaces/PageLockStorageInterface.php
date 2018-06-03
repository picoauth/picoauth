<?php

namespace PicoAuth\Storage\Interfaces;

/**
 * Interface for accessing PageLock configuration
 */
interface PageLockStorageInterface
{
    /**
     * Get configuration array
     *
     * @return array
     */
    public function getConfiguration();
    
    /**
     * Get lock by its identifier
     *
     * @param string $lockId
     * @return array|null Lock data array, null if not found
     */
    public function getLockById($lockId);
    
    /**
     * Get lock by Pico page URL
     *
     * @param string $url Page url
     * @return string LockID
     */
    public function getLockByURL($url);
}
