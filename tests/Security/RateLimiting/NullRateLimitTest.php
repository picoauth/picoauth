<?php

namespace PicoAuth\Security\RateLimiting;

use PicoAuth\BaseTestCase;

class NullRateLimitTest extends BaseTestCase
{
    
    public function testAction()
    {
        $limit = new NullRateLimit();
        $this->assertTrue($limit->action("any-action"));
    }
    
    public function testGetError()
    {
        $limit = new NullRateLimit();
        $this->assertNull($limit->getError());
    }
}
