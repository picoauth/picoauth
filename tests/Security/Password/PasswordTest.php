<?php

namespace PicoAuth\Security\Password;

use PHPUnit\Framework\TestCase;

class PasswordTest extends TestCase
{
    
    public function testToString()
    {
        $p = new Password("test");
        $this->assertSame("test", (string)$p);
        $this->assertSame("test", $p->get());
    }
    
    public function testDebugOutput()
    {
        $p = new Password('12345');
        ob_start();
        var_dump($p);
        $result = ob_get_clean();
        
        $this->assertFalse(strpos($result, '12345'));
        $this->assertRegexp('/\*hidden\*/', $result);
    }
}
