<?php

namespace PicoAuth\Security\Password\Encoder;

use PHPUnit\Framework\TestCase;

class BcryptTest extends TestCase
{
    
    public function testConstruct()
    {
        $b1 = new BCrypt();
        $this->expectException(\InvalidArgumentException::class);
        $b2 = new BCrypt(['cost'=>100]);
    }
    
    public function testEncodeDecode()
    {
        $bcrypt = new BCrypt(['cost'=>4]);
        $res = $bcrypt->encode("BcryptTest");
        $this->assertStringStartsWith("$2", $res);
        $this->assertTrue($bcrypt->isValid($res, "BcryptTest"));
    }
    
    public function testLengthLimit()
    {
        $raw = str_repeat("r", 73);
        $bcrypt = new BCrypt(['cost'=>4]);
        $this->assertEquals(72, $bcrypt->getMaxAllowedLen());
        $this->assertFalse($bcrypt->isValid("0", $raw));
        $this->expectException(EncoderException::class);
        $bcrypt->encode($raw);
    }
    
    public function testNeedsRehash()
    {
        $bcrypt4 = new BCrypt(['cost'=>4]);
        $res4 = $bcrypt4->encode("BcryptTest");
        
        $bcrypt5 = new BCrypt(['cost'=>5]);
        $this->assertTrue($bcrypt5->needsRehash($res4));
    }
}
