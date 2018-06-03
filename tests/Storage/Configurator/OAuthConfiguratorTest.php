<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class OAuthConfiguratorTest extends TestCase
{
    
    protected $configurator;
    
    protected function setUp()
    {
        $this->configurator = new OAuthConfigurator;
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
            [["callbackPage"=>1]],
            [["providers"=>[1=>1]]],
            [["providers"=>["t"=>1]]],
            [["providers"=>["t"=>[]]]],
            [["providers"=>["t"=>["provider"=>""]]]],
            [["providers"=>["t"=>["options"=>[]]]]],
            [["providers"=>["t"=>["options"=>["clientId"=>1,"clientSecret"=>"a"]]]]],
            [["providers"=>["t"=>[
                "default"=>["groups"=>["a",1]],
                "options"=>["clientId"=>"a","clientSecret"=>"a"]]]]],
            [["providers"=>["t"=>[
                "attributeMap"=>["a",1],
                "options"=>["clientId"=>"a","clientSecret"=>"a"]]]]],
            [["providers"=>["t"=>[
                "attributeMap"=>[1,"a"],
                "options"=>["clientId"=>"a","clientSecret"=>"a"]]]]],
        ];
    }
 
    public function testValidation()
    {
        $res = $this->configurator->validate(null);
        $this->assertArrayHasKey("providers", $res);
        
        $res = $this->configurator->validate(
            ["providers"=>["test"=>["options"=>["clientId"=>"a","clientSecret"=>"a"]]]]
        );
        
        $res = $this->configurator->validate(
            ["providers"=> [
                "test"=>[
                    "provider" => "a",
                    "options"=>[
                        "clientId"=>"a",
                        "clientSecret"=>"a"
                    ]
                ]
            ]]
        );
        $this->assertArrayHasKey("providers", $res);
    }
}
