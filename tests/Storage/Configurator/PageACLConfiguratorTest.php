<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class PageACLConfiguratorTest extends TestCase
{
    
    protected $configurator;
    
    protected function setUp()
    {
        $this->configurator = new PageACLConfigurator;
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
            [["access"=>1]],
            [["access"=>[1=>["users"=>"a"]]]],
            [["access"=>[""=>["users"=>"a"]]]],
            [["access"=>["users"=>1]]],
            [["access"=>["groups"=>1]]],
            [["access"=>[["a"=>["users"=>[1,2,3]]]]]],
            [["access"=>[["a"=>["groups"=>[1,2,3]]]]]],
            [["access"=>[["a"=>["recursive"=>1]]]]],
        ];
    }
 
    public function testValidation()
    {
        $res = $this->configurator->validate(null);
        $this->assertArrayHasKey("access", $res);
        
        $res = $this->configurator->validate([
            "access" => [
                "/a" => ["users"=>["a"]],
                "/b" => ["groups"=>["a"]],
                "/c" => ["users"=>[1],"groups"=>["a"]],
                "/c" => ["users"=>["a"],"groups"=>["a"],"recursive"=>true],
            ],
        ]);
        $this->assertArrayHasKey("access", $res);
    }
}
