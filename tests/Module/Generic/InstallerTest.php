<?php

namespace PicoAuth\Module\Generic;

use PicoAuth\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use PicoAuth\PicoAuthInterface;

class InstallerTest extends \PicoAuth\BaseTestCase
{
    
    protected $installer;

    protected function setUp()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);

        $this->installer = new Installer($picoAuth);
    }

    public function testOnPicoRequestAtPicoAuth()
    {
        $pico = $this->createMock(\Pico::class);
        $pico->expects($this->exactly(3))
            ->method("getBaseUrl")
            ->willReturn("/");
        $pico->expects($this->exactly(3))
            ->method("getConfig")
            ->willReturn("");
        $pico->expects($this->exactly(2))
            ->method("getConfigDir")
            ->willReturn("/config");
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getPluginPath")
            ->willReturn("");
        $picoAuth->expects($this->once())
            ->method("setRequestFile")
            ->with("/content/install.md");
        $picoAuth->expects($this->exactly(3))
            ->method("getPico")
            ->willReturn($pico);
        $picoAuth->expects($this->exactly(4))
            ->method("addOutput");
        
        //not used
        $request = $this->createMock(Request::class);
        $request->request = $this->createMock(ParameterBag::class);
        
        $installer = new Installer($picoAuth);
        $installer->onPicoRequest("PicoAuth", $request);
    }

    public function testOnPicoRequestAtPicoAuthModules()
    {
        $pico = $this->createMock(\Pico::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getPluginPath")
            ->willReturn("");
        $picoAuth->expects($this->once())
            ->method("setRequestFile")
            ->with("/content/install.md");
        $picoAuth->expects($this->exactly(1))
            ->method("addOutput");
        
        //not used
        $request = $this->createMock(Request::class);
        $request->request = $this->createMock(ParameterBag::class);
        
        $installer = new Installer($picoAuth);
        $installer->onPicoRequest("PicoAuth/modules", $request);
    }
    
    public function testGetName()
    {
        $this->assertEquals('installer', $this->installer->getName());
    }
}
