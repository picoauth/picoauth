<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class ConfigurationExceptionTest extends TestCase
{
       
    public function testAddBeforeMessage()
    {
        $e = new ConfigurationException("test");
        $e->addBeforeMessage("_");
        $this->assertEquals("_ test", $e->getMessage());
    }

    public function testToString()
    {
        $e = new ConfigurationException("test");
        $this->assertEquals("test", (string)$e);
    }
}
