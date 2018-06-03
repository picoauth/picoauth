<?php

namespace PicoAuth\Security\RateLimiting;

/**
 * Null object of RateLimitInterface, used when rate limiting is disabled.
 */
class NullRateLimit implements RateLimitInterface
{

    /**
     * {@inheritdoc}
     */
    public function action($actionName, $increment = true, $params = array())
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        return null;
    }
}
