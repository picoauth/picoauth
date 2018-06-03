<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class PageLockConfiguratorTest extends TestCase
{
    
    protected $configurator;
    
    protected function setUp()
    {
        $this->configurator = new PageLockConfigurator;
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
            [["encoder"=>1]],
            [["locks"=>[1=>["key"=>"a"]]]],
            [["locks"=>[""=>["key"=>"a"]]]],
            [["locks"=>["a"=>1]]],
            [["locks"=>["a"=>["nokey"=>"a"]]]],
            [["locks"=>["a"=>["key"=>1]]]],
            [["locks"=>["a"=>["key"=>"1","encoder"=>1]]]],
            [["locks"=>["a"=>["key"=>"1","file"=>1]]]],
            
            [["urls"=>1]],
            [["urls"=>[1=>[]]]],
            [["urls"=>[""=>[]]]],
            [["urls"=>["a"=>[]]]],
            [["urls"=>["a"=>["lock"=>1]]]],
            [["urls"=>["a"=>["lock"=>"1","recursive"=>1]]]],
        ];
    }
 
    public function testValidation()
    {
        $res = $this->configurator->validate([
            "locks" => [
                "a" => ["key"=>"a","file"=>"a","encoder"=>"plain"]
            ],
            "urls" => [
                "test" => ["lock"=>"1","recursive"=>true]
            ]
        ]);
        $this->assertArrayHasKey("encoder", $res);
    }
}
