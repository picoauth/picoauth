<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class LocalAuthConfiguratorTest extends TestCase
{
    
    protected $configurator;
    
    protected function setUp()
    {
        $this->configurator = new LocalAuthConfigurator;
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
            [["login"=>["passwordRehash"=>1]]],
            [["accountEdit"=>["enabled"=>1]]],
            [["passwordReset"=>["enabled"=>1]]],
            [["passwordReset"=>["emailMessage"=>1]]],
            [["passwordReset"=>["emailMessage"=>"noUrlPlaceholder"]]],
            [["passwordReset"=>["emailSubject"=>1]]],
            [["passwordReset"=>["tokenIdLen"=>"a"]]],
            [["passwordReset"=>["tokenIdLen"=>-1]]],
            [["passwordReset"=>["tokenIdLen"=>0]]],
            [["passwordReset"=>["tokenIdLen"=>2000000]]],
            [["passwordReset"=>["tokenLen"=>"a"]]],
            [["passwordReset"=>["tokenLen"=>-1]]],
            [["passwordReset"=>["tokenLen"=>1]]],
            [["passwordReset"=>["tokenLen"=>2000000]]],
            [["passwordReset"=>["tokenValidity"=>"a"]]],
            [["passwordReset"=>["tokenValidity"=>0]]],
            [["passwordReset"=>["resetTimeout"=>"a"]]],
            [["passwordReset"=>["resetTimeout"=>0]]],
            [["registration"=>["maxUsers"=>-1]]],
            [["registration"=>["maxUsers"=>"25"]]],
            [["registration"=>["nameLenMin"=>"a"]]],
            [["registration"=>["nameLenMin"=>0]]],
            [["registration"=>["nameLenMax"=>"a"]]],
            [["registration"=>["nameLenMax"=>0]]],
            [["registration"=>["nameLenMax"=>2000000]]],
            [["registration"=>["nameLenMax"=>8,"nameLenMin"=>9]]],
            [["users"=>1]],
            [["users"=>["a."=>[]]]],
            [["users"=>["aaaaa."=>[]]]],
            [["users"=>[1=>[]]]],
            [["users"=>["tester"=>[]]]],
            [["users"=>["tester"=>["pwhash"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"b","email"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"c","attributes"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"d","encoder"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"e","pwreset"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"f","groups"=>1]]]],
            [["users"=>["tester"=>["pwhash"=>"g","groups"=>[1,"a",3]]]]],
            [["users"=>["tester"=>["pwhash"=>"h"],"TESTER"=>["pwhash"=>"i"]]]],
            [["users"=>["tester"=>["pwhash"=>"i","displayName"=>false]]]],
        ];
    }
    
    public function testValidation()
    {
        $res = $this->configurator->validate(null);
        $this->assertArrayHasKey("encoder", $res);
        
        $res = $this->configurator->validate([
            "users" => [
                "TeStEr" => ["pwhash"=>"i"],
            ],
        ]);
        $this->assertArrayHasKey("users", $res);
        $this->assertArrayHasKey("tester", $res["users"]);
    }
}
