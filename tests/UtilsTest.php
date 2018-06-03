<?php

namespace PicoAuth;

use PHPUnit\Framework\TestCase;

/**
 * PicoAuth plugin tests
 */
class UtilsTest extends TestCase
{
    
    /**
     * @dataProvider queryProvider
     */
    public function testGetRefererQueryParam($url, $key, $expected)
    {
        $this->assertEquals($expected, Utils::getRefererQueryParam($url, $key));
    }
    
    public function queryProvider()
    {
        return [
            [null, null, null],
            ["https://test.xyz?a=1", "a", "1"],
            ["https://test.xyz?a=1&b=2", "b", "2"],
            ["https://", "b", null],
            ["https://test.xyz?a=1&b=2", null, null],
            ["aaa", "b", null],
        ];
    }
    
    /**
     * @dataProvider pageProvider
     */
    public function testIsValidPageId($url, $expected)
    {
        $this->assertEquals($expected, Utils::isValidPageId($url));
    }
    
    public function pageProvider()
    {
        return [
            [null, false],
            ["", false],
            ["index", true],
            ["pAge-Name0_", true],
            ["a?", false],
            ["@^", false],
        ];
    }
}
