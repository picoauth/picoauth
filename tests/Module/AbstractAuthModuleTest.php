<?php

namespace PicoAuth\Module;

use PicoAuth\PicoAuthInterface;

class AbstractAuthModuleTest extends \PicoAuth\BaseTestCase
{
    
    protected $abstractModule;

    protected function setUp()
    {
        $this->abstractModule = $this->getMockBuilder(AbstractAuthModule::class)
            ->getMockForAbstractClass();
    }

    public function testHandleEvent()
    {
        $this->assertNull($this->abstractModule->handleEvent("nonExistent", []));
    }
}
