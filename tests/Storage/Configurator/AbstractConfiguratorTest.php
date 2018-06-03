<?php

namespace PicoAuth\Storage\Configurator;

use PHPUnit\Framework\TestCase;

class AbstractConfiguratorTest extends TestCase
{
    
    /**
     * The tested class name
     */
    const FQCN = '\PicoAuth\Storage\Configurator\AbstractConfigurator';
    
    public function testApplyDefaultsInvalidArguments()
    {
        $this->expectException(\InvalidArgumentException::class);
        $stub = $this->getMockForAbstractClass(self::FQCN);
        $stub->applyDefaults([], [], -1);
    }
    
    /**
     * @dataProvider applyDefaultsProvider
     */
    public function testApplyDefaults($config, array $defaults, $depth, $expected)
    {
        $stub = $this->getMockForAbstractClass(self::FQCN);
        $this->assertEquals($expected, $stub->applyDefaults($config, $defaults, $depth));
    }
    
    public function applyDefaultsProvider()
    {
        return [
            [null,      ["a"],      0,  ["a"]],
            [["a"=>1],  ["a"=>2],   0,  ["a"=>1]],
            [["b"=>1],  ["a"=>2],   0,  ["a"=>2,"b"=>1]],
            
            [["a"=>["aa"=>0,"ab"=>1]],  ["a"=>1],           0, ["a"=>["aa"=>0,"ab"=>1]]],
            [["a"=>["aa"=>0,"ab"=>1]],  ["a"=>[]],          1, ["a"=>["aa"=>0,"ab"=>1]]],
            [["a"=>["aa"=>0]],          ["a"=>["ab"=>1]],   1, ["a"=>["aa"=>0,"ab"=>1]]],
            
            [["a"=>["b"=>["c"=>1]]],    ["a"=>["b"=>["d"=>1]]], 2, ["a"=>["b"=>["c"=>1,"d"=>1]]]],
        ];
    }
    
    /**
     * @dataProvider invalidApplyDefaultsProvider
     */
    public function testInvalidApplyDefaults($config, array $defaults, $depth)
    {
        $this->expectException(ConfigurationException::class);
        $stub = $this->getMockForAbstractClass(self::FQCN);
        $stub->applyDefaults($config, $defaults, $depth);
    }
    
    public function invalidApplyDefaultsProvider()
    {
        return [
            [["a"=>[]],         ["a"=>1],                   1],
            [["a"=>1],          ["a"=>[]],                  1],
            [["a"=>["aa"=>0,"ab"=>1]], ["a"=>["aa"=>[]]],   2],
        ];
    }
    
    public function testAssertRequired()
    {
        $this->expectException(ConfigurationException::class);
        $stub = $this->getMockForAbstractClass(self::FQCN);
        $stub->assertRequired([], "key");
    }
    
    /**
     * @dataProvider notArrayOfStrings
     */
    public function testAssertArrayOfStrings($val)
    {
        $this->expectException(ConfigurationException::class);
        $stub = $this->getMockForAbstractClass(self::FQCN);
        
        $arr=array(
            "key"=>$val
        );
        $stub->assertArrayOfStrings($arr, "key");
    }
    
    public function notArrayOfStrings()
    {
        return [
            [null],
            [1],
            [["a"=>1]],
            [[1,2,3,4]],
            [["a","b","c",1,"d"]],
        ];
    }
    
    /**
     * @dataProvider notIntOrFalse
     */
    public function testAssertIntOrFalse($val, $min = null, $max = null)
    {
        $this->expectException(ConfigurationException::class);
        $stub = $this->getMockForAbstractClass(self::FQCN);
        
        $arr=array(
            "key"=>$val
        );
        $stub->assertIntOrFalse($arr, "key", $min, $max);
    }
    
    public function notIntOrFalse()
    {
        return [
            [null],
            [true],
            ["false"],
            [["a"=>1]],
            [0,1,2],
            [3,1,2],
            [true,1,2],
        ];
    }
    
    public function testStandardizeUrlFormat()
    {
        $stub = $this->getMockForAbstractClass(self::FQCN);
        
        // Prepend a slash
        $arr=["page"=>1];
        $stub->standardizeUrlFormat($arr, "page");
        $this->assertEquals(["/page"=>1], $arr);
        
        // Remove the additional slash
        $arr=["page/"=>1];
        $stub->standardizeUrlFormat($arr, "page/");
        $this->assertEquals(["/page"=>1], $arr);
        
        $arr=false;
        $stub->standardizeUrlFormat($arr, "page");
        $this->assertEquals(false, $arr);
    }
}
