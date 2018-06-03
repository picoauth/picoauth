<?php

namespace PicoAuth\Security\RateLimiting;

use Psr\Log\LoggerInterface;
use PicoAuth\BaseTestCase;
use PicoAuth\Storage\Configurator\RateLimitConfigurator;
use PicoAuth\Storage\Interfaces\RateLimitStorageInterface;

class RateLimitTest extends BaseTestCase
{
    
    protected $limit;
    protected $configurator;
    protected $testConfig;
    
    protected function setUp()
    {
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $this->limit = new RateLimit($sto);
        $this->testConfig = array(
            "count" => 1,
            "blockDuration" => 10000,
            "counterTimeout" =>20000,
            "errorMsg" => "%cnt%"
        );
        $this->configurator = new RateLimitConfigurator;
    }
    
    /**
     * @dataProvider ipProvider
     */
    public function testGetSubnet($ip, $mask, $expected)
    {
        $getSubnet = self::getMethod($this->limit, 'getSubnet');
        $this->assertEquals($expected, $getSubnet->invokeArgs($this->limit, [$ip, $mask]));
    }

    public function ipProvider()
    {
        return [
            ["10.0.0.1",32,"10.0.0.1/32"],
            ["10.0.0.3",31,"10.0.0.2/31"],
            ["10.0.0.7",30,"10.0.0.4/30"],
            ["10.0.0.15",29,"10.0.0.8/29"],
            ["10.0.255.255",17,"10.0.128.0/17"],
            ["10.11.12.13",24,"10.11.12.0/24"],
            ["10.11.12.13",8,"10.0.0.0/8"],
            ["10.11.12.13",8,"10.0.0.0/8"],
            ["ffff::",1,"8000::/1"],
            ["2000:dead:beef:4dad:29:96:cc:191",64,"2000:dead:beef:4dad::/64"],
            ["1::",0,"::/0"],
        ];
    }
    
    /**
     * @dataProvider invalidSubnetArgumentsProvider
     */
    public function testGetSubnetInvalidArguments($ip, $mask)
    {
        $this->expectException(\InvalidArgumentException::class);
        $getSubnet = self::getMethod($this->limit, 'getSubnet');
        $getSubnet->invokeArgs($this->limit, [$ip, $mask]);
    }

    public function invalidSubnetArgumentsProvider()
    {
        return [
            // Invalid netmasks
            ["10.0.0.1","32"],
            ["10.0.0.1",null],
            ["10.0.0.1",33],
            ["10.0.0.1",-1],
            ["2000::",-1],
            ["2000::",129],
            
            // Invalid IP
            ["2000:cg:",129],
            ["2000:::",24],
            ["10.0.0.0.1",24],
            ["10.0.0",24],
            ["10.0.0.",24],
            [null,24],
            [24,24],
        ];
    }
    
    public function testGetLimitFor()
    {
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $sto->expects($this->once())
            ->method("getLimitFor")
            ->with("a", "b", "c")
            ->willReturn(null);
        $this->limit = new RateLimit($sto);
        
        $method = self::getMethod($this->limit, 'getLimitFor');
        $ret = $method->invokeArgs($this->limit, ["a","b","c"]);
        $this->assertEquals(array("ts" => 0, "cnt" => 0), $ret);
    }
    
    public function testAction()
    {
        $this->assertTrue($this->limit->action("undefined-action"));
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $sto->expects($this->once())
            ->method("getConfiguration")
            ->willReturn($this->configurator->validate([]));
        $sto->expects($this->at(1))
            ->method("transaction");
        $sto->expects($this->at(2))
            ->method("getLimitFor")
            ->with("login", "ip", "10.0.0.1/".RateLimit::DEFAULT_NETMASK_IPV4)
            ->willReturn(array("ts" => time(), "cnt" => 1000000));
        $sto->expects($this->at(3))
            ->method("transaction");
        $sto->expects($this->at(4))
            ->method("transaction");
        $sto->expects($this->at(5))
            ->method("getLimitFor")
            ->with("login", "ip", "2000::/".RateLimit::DEFAULT_NETMASK_IPV6)
            ->willReturn(array("ts" => time(), "cnt" => 1000000));
        $sto->expects($this->at(6))
            ->method("transaction");
        $limit = new RateLimit($sto);
        $_SERVER["REMOTE_ADDR"] = '10.0.0.1';
        $this->assertFalse($limit->action("login"));
        $_SERVER["REMOTE_ADDR"] = '2000::';
        $this->assertFalse($limit->action("login"));
    }
    
    public function testIncrementCounter()
    {
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $sto->expects($this->once())
            ->method("getConfiguration")
            ->willReturn($this->configurator->validate([]));
        $sto->expects($this->once())
            ->method("save")
            ->with("login", "ip")
            ->willReturn(true);
        $sto->expects($this->once())
            ->method("updateLimitFor");
        $sto->expects($this->once())
            ->method("getLimitFor")
            ->with("login", "ip", "10.0.0.1/32")
            ->willReturn(array("ts" => time(), "cnt" => 0));

        
        $limit = new RateLimit($sto);
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(1))
               ->method('notice')->willReturnSelf();
        $limit->setLogger($logger);
        
        $method = self::getMethod($limit, 'incrementCounter');
        $method->invokeArgs($limit, ["login","ip","10.0.0.1/32",$this->testConfig]);
    }
    
    public function testIncrementCounterNoIncrement()
    {
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $sto->expects($this->never())
            ->method("save")
            ->with("login", "ip")
            ->willReturn(true);
        $sto->expects($this->once())
            ->method("getLimitFor")
            ->with("login", "ip", "10.0.0.1/32")
            ->willReturn(array("ts" => time(), "cnt" => 1));

        $limit = new RateLimit($sto);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method('notice')->willReturnSelf();
        $limit->setLogger($logger);

        $method = self::getMethod($limit, 'incrementCounter');
        $method->invokeArgs($limit, ["login", "ip", "10.0.0.1/32", $this->testConfig]);
    }
    
    public function testIncrementCounterResetCounter()
    {
        $sto = $this->createMock(RateLimitStorageInterface::class);
        $sto->expects($this->once())
            ->method("save")
            ->with("login", "ip")
            ->willReturn(true);
        $sto->expects($this->once())
            ->method("getLimitFor")
            ->with("login", "ip", "10.0.0.1/32")
            ->willReturn(array("ts" => 0, "cnt" => 10));

        $limit = new RateLimit($sto);
        $method = self::getMethod($limit, 'incrementCounter');
        $method->invokeArgs($limit, ["login", "ip", "10.0.0.1/32", $this->testConfig]);
    }
    
    public function testSetErrorMessage()
    {
        $method = self::getMethod($this->limit, 'setErrorMessage');
        
        $method->invokeArgs($this->limit, [null]);
        $this->assertTrue(is_string($this->limit->getError()));
        
        $method->invokeArgs($this->limit, [$this->testConfig]);
        $this->assertEquals("1", $this->limit->getError());
    }
    
    public function testGetEntityId()
    {
        $method = self::getMethod($this->limit, 'getEntityId');
        $this->assertNull($method->invokeArgs($this->limit, ["test",array()]));
        $this->assertEquals(md5("t1"), $method->invokeArgs(
            $this->limit,
            ["account",[],[
                "name" => "t1"
            ]]
        ));
        $this->assertEquals(md5("t@email.com"), $method->invokeArgs(
            $this->limit,
            ["email",[],[
                "email" => "t@email.com"
            ]]
        ));
    }
}
