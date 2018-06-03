<?php

namespace PicoAuth\Security\RateLimiting;

/**
 * Rate Limit Interface
 */
interface RateLimitInterface
{

    /**
     * Registers an action and determines if it is rate limited or not
     *
     * @param string $actionName Identifier for the limited action
     * @param bool $increment True if the action counter should be incremented
     *                        (e.g. after unsuccessful login attempt)
     *                        False if the counter should stay unchanged
     *                        (e.g. before processing the login form)
     * @param array $params Additional parameters specific to the action
     * @return bool true if action is allowed, false if blocked
     */
    public function action($actionName, $increment = true, $params = array());

    /**
     * Get error message for the last action() call that returned false
     * @return string|null Message, null if there were no blocked actions
     */
    public function getError();
}
