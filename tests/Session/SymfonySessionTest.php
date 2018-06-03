<?php

namespace PicoAuth\Session;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SymfonySessionTest extends \PicoAuth\BaseTestCase
{
    
    protected $session;
    
    protected function setUp()
    {
        $storage = new MockArraySessionStorage();
        $this->session = new SymfonySession($storage);
    }
    
    public function testClear()
    {
        $symfonySession = $this->createMock(Session::class);
        $symfonySession->expects($this->once())
            ->method('clear');
        self::set($this->session, $symfonySession, 'session');
        
        $this->session->clear();
    }

    public function testInvalidate()
    {
        $symfonySession = $this->createMock(Session::class);
        $symfonySession->expects($this->once())
            ->method('invalidate')
            ->with(321)
            ->willReturn(123);
        self::set($this->session, $symfonySession, 'session');
        
        $res = $this->session->invalidate(321);
        $this->assertEquals(123, $res);
    }
    
    public function testMigrate()
    {
        $symfonySession = $this->createMock(Session::class);
        $symfonySession->expects($this->once())
            ->method('migrate')
            ->with(true, 123)
            ->willReturn(123);
        self::set($this->session, $symfonySession, 'session');
        
        $res = $this->session->migrate(true, 123);
        $this->assertEquals(123, $res);
    }
 
    public function testGetFlash()
    {
        $parameterBag = $this->createMock(ParameterBag::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->with("key", array(1))
            ->willReturn(array("test"));
        
        $symfonySession = $this->createMock(Session::class);
        $symfonySession->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($parameterBag);
        self::set($this->session, $symfonySession, 'session');
        
        $res = $this->session->getFlash("key", array(1));
        $this->assertEquals(array("test"), $res);
    }
}
