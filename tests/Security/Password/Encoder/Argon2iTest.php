<?php

namespace PicoAuth\Security\Password\Encoder;

use PHPUnit\Framework\TestCase;

class Argon2iTest extends TestCase
{
    
    protected function setUp()
    {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped(
                'ARGON2I algorithm is not available in the test environment.'
            );
        }
    }
    
    public function testEncodeDecode()
    {
        $arg = new Argon2i();
        $res = $arg->encode("Test");
        $this->assertStringStartsWith("$", $res);
        $this->assertTrue($arg->isValid($res, "Test"));
    }
    
    public function testLengthLimit()
    {
        $raw = str_repeat("r", Argon2i::MAX_LEN+1);
        $arg = new Argon2i();
        $this->assertEquals(Argon2i::MAX_LEN, $arg->getMaxAllowedLen());
        $this->assertFalse($arg->isValid("0", $raw));
        $this->expectException(EncoderException::class);
        $arg->encode($raw);
    }
    
    public function testNeedsRehash()
    {
        $arg1 = new Argon2i(['time_cost'=>1]);
        $res1 = $arg1->encode("BcryptTest");
        
        $arg2 = new Argon2i(['cost'=>2]);
        $this->assertTrue($arg2->needsRehash($res1));
    }
}
