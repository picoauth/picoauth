<?php

namespace PicoAuth\Module\Authorization;

use PicoAuth\Storage\Interfaces\PageLockStorageInterface;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use League\Container\Container;

class PageLockTest extends \PicoAuth\BaseTestCase
{
    
    protected $pageLock;
    
    protected $testContainer;

    protected function setUp()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageLockStorageInterface::class);
        $storage->expects($this->exactly(1))
            ->method('getConfiguration')
            ->willReturn(null);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $this->pageLock = new PageLock($picoAuth, $sess, $storage, $rateLimit);

        $this->testContainer=new Container;
        $this->testContainer->share('plain', 'PicoAuth\Security\Password\Encoder\Plaintext');
    }
    
    public function testCheckAccess()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $storage = $this->createMock(PageLockStorageInterface::class);
        $storage->expects($this->exactly(3))
            ->method('getLockByURL')
            ->with("url")
            ->willReturnOnConsecutiveCalls("lock1", "lock2", null);
        
        $sess = $this->createMock(SessionInterface::class);
        $sess->expects($this->exactly(2))
            ->method('get')
            ->with("unlocked")
            ->willReturn(["lock1"]);
        
        $pageLock = new PageLock($picoAuth, $sess, $storage, $rateLimit);
        
        $this->assertTrue($pageLock->checkAccess("url"));
        $this->assertFalse($pageLock->checkAccess("url"));
        $this->assertTrue($pageLock->checkAccess("url"));
    }
        
    public function testGetKeyEncoder()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($this->testContainer);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageLockStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn(null);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $this->pageLock = new PageLock($picoAuth, $sess, $storage, $rateLimit);
        
        $method=self::getMethod($this->pageLock, 'getKeyEncoder');
        $method->invokeArgs($this->pageLock, [
            ["encoder" => "plain"]
        ]);
    }

    public function testGetName()
    {
        $this->assertEquals('pageLock', $this->pageLock->getName());
    }
}
