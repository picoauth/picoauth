<?php

namespace PicoAuth\Storage\Interfaces;

/**
 * Interface for accessing RateLimit data
 */
interface RateLimitStorageInterface
{
    
    /**
     * Close storage transaction
     */
    const TRANSACTION_END = 0;
    
    /**
     * Begin storage transaction
     */
    const TRANSACTION_BEGIN = 1;
    
    /**
     * Get configuration array
     *
     * @return array
     */
    public function getConfiguration();
    
    /**
     * Get limit data for action-type-identifier
     *
     * @param string $action E.g. "login"
     * @param string $type E.g. "account"
     * @param string $key E.g. "user1"
     * @return array|null
     * @throws \Exception On read error
     */
    public function getLimitFor($action, $type, $key);
    
    /**
     * Update limit data
     *
     * @param string $action E.g. "login"
     * @param string $type E.g. "account"
     * @param string $key E.g. "user1"
     * @param array $limitData New limit data array
     */
    public function updateLimitFor($action, $type, $key, $limitData);
    
    /**
     * Clean expired records
     *
     * @param string $action
     * @param string $type
     * @param array $config Limit configuration
     * @return int Number of removed records
     */
    public function cleanup($action, $type, $config);
    
    /**
     * Save datafile for action-type
     *
     * @param string $action
     * @param string $type
     * @throws \Exception On save error
     */
    public function save($action, $type);
    
    /**
     * Transaction control (TCL)
     *
     * Begins or ends an exclusive transaction. After the transaction begins,
     * all subsequent calls to other storage methods must be executed atomically,
     * until the transaction ends.
     *
     * @param string $action
     * @param string $type
     * @param int $state One of the TRANSACTION_ constants
     */
    public function transaction($action, $type, $state);
}
