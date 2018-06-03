<?php

namespace PicoAuth\Security\Password\Encoder;

use PHPUnit\Framework\TestCase;

class PlaintextTest extends TestCase
{
    
    public function testConstruct()
    {
        $this->expectException(\InvalidArgumentException::class);
        $p = new Plaintext(['ignoreCase'=>100]);
    }
    
    public function testEncodeDecode()
    {
        $ptext = new Plaintext();
        $res = $ptext->encode("Test");
        $this->assertEquals("Test", $res);
        $this->assertFalse($ptext->isValid($res, "test"));
        $this->assertTrue($ptext->isValid($res, "Test"));
    }
    
    public function testLengthLimit()
    {
        $raw = str_repeat("r", Plaintext::MAX_LEN+1);
        $ptext = new Plaintext();
        $this->assertEquals(Plaintext::MAX_LEN, $ptext->getMaxAllowedLen());
        $this->assertFalse($ptext->isValid("0", $raw));
        $this->expectException(EncoderException::class);
        $ptext->encode($raw);
    }
    
    public function testNeedsRehash()
    {
        $ptext = new Plaintext();
        $this->assertFalse($ptext->needsRehash('a'));
    }
    
    public function testIgnoreCase()
    {
        $ptext = new Plaintext(['ignoreCase'=>true]);
        $res = $ptext->encode("Test");
        $this->assertTrue($ptext->isValid($res, "test"));
        $this->assertTrue($ptext->isValid($res, "TesT"));
    }
}
