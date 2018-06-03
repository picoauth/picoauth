<?php

namespace PicoAuth\Security\Password\Policy;

use PHPUnit\Framework\TestCase;
use PicoAuth\Security\Password\Password;

class PasswordPolicyTest extends TestCase
{
    
    public function testMinLength()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("test1");
        $pw3 = new Password("1🐱"); //cat = 4 bytes
        $pw4 = new Password("test🐱");
        
        $policy = new PasswordPolicy();
        $policy->minLength(5);
        $this->assertFalse($policy->check($pw1));
        $this->assertTrue($policy->check($pw2));
        $this->assertFalse($policy->check($pw3));
        $this->assertTrue($policy->check($pw4));
    }

    public function testMaxLength()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("test1");
        $pw3 = new Password("1🐱");
        
        $policy = new PasswordPolicy();
        $policy->maxLength(4);
        $this->assertTrue($policy->check($pw1));
        $this->assertFalse($policy->check($pw2));
        $this->assertTrue($policy->check($pw3));
    }
    
    public function testMinNumbers()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("test1");
        
        $policy = new PasswordPolicy();
        $policy->minNumbers(1);
        $this->assertFalse($policy->check($pw1));
        $this->assertTrue($policy->check($pw2));
    }
    
    public function testMinUppercase()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("testR");
        $pw3 = new Password("testŘ");
        
        $policy = new PasswordPolicy();
        $policy->minUppercase(1);
        $this->assertFalse($policy->check($pw1));
        $this->assertTrue($policy->check($pw2));
        $this->assertTrue($policy->check($pw3));
    }
    
    public function testMinLowercase()
    {
        $pw1 = new Password("TEST");
        $pw2 = new Password("TESTr");
        
        // \p{Ll} regex specifier is unable to match ř as lowercase
        //$pw3 = new Password("TESTř");
        
        $policy = new PasswordPolicy();
        $policy->minLowercase(1);
        $this->assertFalse($policy->check($pw1));
        $this->assertTrue($policy->check($pw2));
    }
    
    public function testMinSpecial()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("test^");
        
        $policy = new PasswordPolicy();
        $policy->minSpecial(1);
        $this->assertFalse($policy->check($pw1));
        $this->assertTrue($policy->check($pw2));
    }
    
    public function testMatches()
    {
        $pw1 = new Password("test");
        $pw2 = new Password("test1");
        
        $policy = new PasswordPolicy();
        $policy->matches('/.*1$/', 'explanation');
        $this->assertFalse($policy->check($pw1));
        $this->assertEquals(array('explanation'), $policy->getErrors());
        $this->assertTrue($policy->check($pw2));
        
        $this->expectException(\InvalidArgumentException::class);
        $policy->matches(null, null);
    }
}
