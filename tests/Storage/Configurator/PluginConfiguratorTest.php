<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class PluginConfiguratorTest extends TestCase
{
    
    protected $configurator;
    
    protected function setUp()
    {
        $this->configurator = new PluginConfigurator;
    }
    
    /**
     * @dataProvider badConfigurations
     */
    public function testValidationErrors($rawConfig)
    {
        $this->expectException(ConfigurationException::class);
        $this->configurator->validate($rawConfig);
    }

    public function badConfigurations()
    {
        return [
            [["authModules"=>1]],
            [["authModules"=>null]],
            [["afterLogin"=>1]],
            [["afterLogout"=>1]],
            [["alterPageArray"=>1]],
            [["sessionInterval"=>-1]],
            [["sessionInterval"=>true]],
            [["sessionTimeout"=>-1]],
            [["sessionTimeout"=>true]],
            [["sessionIdle"=>-1]],
            [["sessionIdle"=>true]],
            [["rateLimit"=>1]],
            [["debug"=>1]],
        ];
    }
    
    /**
     * Tests only the important default values
     */
    public function testDefaults()
    {
        $default = $this->configurator->validate([]);
        $this->assertFalse($default["debug"]);
        $this->assertEquals(
            ["Installer"],
            $default["authModules"]
        );
    }
}
