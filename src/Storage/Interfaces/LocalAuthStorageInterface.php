<?php

namespace PicoAuth\Storage\Interfaces;

/**
 * Interface for accessing data of local users (LocalAuth module)
 */
interface LocalAuthStorageInterface
{
    /**
     * Get configuration array
     *
     * @return array
     */
    public function getConfiguration();
    
    /**
     * Retrieve user data
     *
     * @param string $name user identifier
     * @return array|null Userdata or null if the user doesn't exist
     */
    public function getUserByName($name);
    
    /**
     * Retrieve user data by email
     *
     * The returned userdata array must contain an additional 'name' key.
     *
     * @param string $email email
     * @return array|null Userdata or null if the user doesn't exist
     */
    public function getUserByEmail($email);
    
    /**
     * Save user
     *
     * @param string $id User identifier
     * @param array $userdata Userdata array
     * @throws \Exception On save failure
     */
    public function saveUser($id, $userdata);

    /**
     * Save reset token
     *
     * @param string $id
     * @param array $data
     */
    public function saveResetToken($id, $data);
    
    /**
     * Get reset token by id
     *
     * The token must be retrievable only once. The method
     * deletes the token entry from the storage on retrieval.
     *
     * @param string $id Token ID
     * @return array|null Token array or null if not found
     */
    public function getResetToken($id);

    /**
     * Username validation
     *
     * Check if the name is valid for this storage type
     * If not, an exception is thrown with explanation message
     *
     * @param string $name Name to be checked
     * @throws \RuntimeException On failure
     */
    public function checkValidName($name);

    /**
     * Return the number of registered users
     *
     * @return int Users count
     */
    public function getUsersCount();
}
