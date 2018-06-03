<?php

namespace PicoAuth\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CSRFTest extends TestCase
{

    protected $CSRF;
    protected $session;

    protected function setUp()
    {
        $storage = new MockArraySessionStorage();
        $this->session = new \PicoAuth\Session\SymfonySession($storage);
        $this->CSRF = new CSRF($this->session);
    }

    public function testTokenAttributes()
    {
        // Generic (unnamed) token
        $this->CSRF->getToken();
        
        $this->assertTrue($this->session->has('CSRF'));
        
        $tokenStorage = $this->session->get('CSRF');
        
        $this->assertArrayHasKey(CSRF::DEFAULT_SELECTOR, $tokenStorage);
        
        $tokenData = $tokenStorage[CSRF::DEFAULT_SELECTOR];
        
        // Token attributes
        $this->assertArrayHasKey('time', $tokenData);
        $this->assertArrayHasKey('token', $tokenData);
        
        // Token time
        $this->assertGreaterThanOrEqual($tokenData['time'], time());
    }
    
    public function testTokenFormat()
    {
        $token = $this->CSRF->getToken();
        
        $this->assertEquals(2, count(explode(CSRF::TOKEN_DELIMTER, $token)));
    }

    /**
     * @depends testTokenAttributes
     */
    public function testTokenLength()
    {
        // Generic (unnamed) token
        $this->CSRF->getToken();

        $tokenData = $this->session->get('CSRF')[CSRF::DEFAULT_SELECTOR];
        
        // Assert token length in bytes
        $this->assertEquals(CSRF::TOKEN_SIZE, strlen($tokenData['token'])/2);
    }
    
    public function testGetToken()
    {
        $this->assertTrue(is_string($this->CSRF->getToken()));
        
        // Two tokens are not same
        $this->assertNotEquals($this->CSRF->getToken(), $this->CSRF->getToken());
        $this->assertNotEquals($this->CSRF->getToken("a"), $this->CSRF->getToken("a"));
    }
    
    public function testCheckToken()
    {
        $token = $this->CSRF->getToken(null, false); //not reusable
        $actionToken = $this->CSRF->getToken("action", true); //reusable
        $this->assertFalse($this->CSRF->checkToken("test"));
        $this->assertFalse($this->CSRF->checkToken("test", "123"));
        $this->assertFalse($this->CSRF->checkToken("test", "action"));
        
        // First validation
        $this->assertTrue($this->CSRF->checkToken($token));
        $this->assertTrue($this->CSRF->checkToken($actionToken, "action"));
        
        // Second validation of the same token
        $this->assertFalse($this->CSRF->checkToken($token));
        $this->assertTrue($this->CSRF->checkToken($actionToken, "action"));
    }
    
    /**
     * When a token is requested for the 2nd time, the time should be updated.
     * @depends testTokenAttributes
     */
    public function testTokenTimeUpdate()
    {
        $token = $this->CSRF->getToken('test');
        $tokenStorage = $this->session->get('CSRF');
        $tokenValue = $tokenStorage['test']['token'];
        $newTime = ($tokenStorage['test']['time'] -= CSRF::TOKEN_VALIDITY/2);

        // Save the token with altered time
        $this->session->set('CSRF', $tokenStorage);
        
        // Request the same token
        $token2 = $this->CSRF->getToken('test');
        $tokenStorage = $this->session->get('CSRF');
        $tokenValue2 = $tokenStorage['test']['token'];
        
        // Internal token value should remain unchanged
        $this->assertEquals($tokenValue, $tokenValue2);
        
        // Token time should be updated (2nd > 1st)
        $this->assertGreaterThan($newTime, $tokenStorage['test']['time']);
        
        // First token still valid
        $this->assertTrue($this->CSRF->checkToken($token, 'test'));
    }
    
    public function testTokenExpiration()
    {
        $token = $this->CSRF->getToken('expireAction');
        $tokenStorage = $this->session->get('CSRF');
        
        // expire
        $tokenStorage['expireAction']['time'] = 0;

        // Save the token with altered time
        $this->session->set('CSRF', $tokenStorage);
        
        // Expired token will not validate
        $this->assertFalse($this->CSRF->checkToken($token, 'expireAction'));
    }
    
    public function testRemoveTokens()
    {
        $token = $this->CSRF->getToken();
        $this->CSRF->removeTokens();
        $this->assertFalse($this->CSRF->checkToken($token));
    }
}
