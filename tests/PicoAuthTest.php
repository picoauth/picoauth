<?php

namespace PicoAuth;

class PicoAuthTest extends BaseTestCase
{
    
    /**
     * PicoAuth plugin instance
     * @var \PicoAuth
     */
    protected $picoAuth;
    
    protected function setUp()
    {
        $picoMock = $this->getMockBuilder('\Pico')
            ->disableOriginalConstructor()
            ->getMock();
        $this->picoAuth = new \PicoAuth($picoMock);
    }

    public function testGetPlugin()
    {
        $p = $this->picoAuth->getPlugin();
        $this->assertTrue($p instanceof \PicoAuth\PicoAuthPlugin);
    }
    
    public function testHandleEvent()
    {
        $mock = $this->createMock(\PicoAuth\PicoAuthPlugin::class);
        $mock->expects($this->once())
            ->method('handleEvent')
            ->with("onTest", [1,2,3]);
        self::set($this->picoAuth, $mock, 'picoAuthPlugin');
        
        self::set($this->picoAuth, true, 'enabled');
        $this->picoAuth->handleEvent("onTest", [1,2,3]);
        self::set($this->picoAuth, false, 'enabled');
        $this->picoAuth->handleEvent("onSecondTest", [4,5,6]);
    }
}
