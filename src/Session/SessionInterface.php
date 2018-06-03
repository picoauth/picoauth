<?php

namespace PicoAuth\Session;

/**
 * Simple session manager interface with a direct support for
 * flash values.
 */
interface SessionInterface
{

    /**
     * Checks if a session key is defined.
     * @param string $name Session attribute name
     * @return bool Whether attribute exists
     */
    public function has($name);

    /**
     * Retrieves a value from the session.
     * @param string $name Session attribute name
     * @param mixed $default Default if the attribute is not defined
     * @return mixed Value
     */
    public function get($name, $default = null);

    /**
     * Creates/replaces value of a session attribute with value.
     * @param string $name Session attribute name
     * @param mixed $value Inserted value
     */
    public function set($name, $value);

    /**
     * Removes a session key with a given name.
     * If the key doesn't exist the call does nothing.
     * @param string $name
     */
    public function remove($name);

    /**
     * Clears all session attributes and regenerates the session
     * @param int $lifetime Cookie lifetime in seconds, null = unchanged
     * @return bool Whether the invalidation was successful
     */
    public function invalidate($lifetime = null);

    /**
     * Move the session to a different session id.
     * @param bool $destroy Delete the old session
     * @param int $lifetime Cookie lifetime in seconds, null = unchanged
     */
    public function migrate($destroy = false, $lifetime = null);

    /**
     * Removes all attributes
     */
    public function clear();

    /**
     * Adds a flash value to the session.
     * The flash values should be available only in the next request
     * using the getFlash() call.
     * @param string $type Flash type (category)
     * @param mixed $message Flash value
     */
    public function addFlash($type, $message);

    /**
     * Gets flash values set in the previous request.
     * @param string $type Flash type (category)
     * @param array $default Default response if the category is not defined
     * @return array
     */
    public function getFlash($type, array $default = array());
}
