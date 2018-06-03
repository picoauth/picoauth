<?php

namespace PicoAuth\Module\Authorization;

use PicoAuth\Storage\Interfaces\PageACLStorageInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use PicoAuth\Module\AuthModule;

class PageACLTest extends \PicoAuth\BaseTestCase
{
    
    protected $pageACL;

    protected function setUp()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageACLStorageInterface::class);

        $this->pageACL = new PageACL($picoAuth, $sess, $storage);
    }

    /**
     * @dataProvider accessTestProvider
     */
    public function testCheckAccess($user, $rule, $expectedAccess)
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageACLStorageInterface::class);
        $storage->expects($this->exactly(1))
            ->method('getRuleByURL')
            ->willReturn($rule);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->exactly(1))
            ->method('getUser')
            ->willReturn($user);
        
        $pageACL = new PageACL($picoAuth, $sess, $storage);
        $res = $pageACL->checkAccess("randomUrl");
        
        $this->assertSame($expectedAccess, $res);
    }
    
    public function accessTestProvider()
    {
        
        $user = new \PicoAuth\User;
        $user->setId("user1");
        $user->addGroup("testGroup");
        
        return [
            [$user,[
                "users" => null,
                "groups" => null,
            ],false],
            
            [$user,[
                "users" => [],
                "groups" => [],
            ],false],
            
            [$user,[
                "users" => ["test","u8","tester"],
                "groups" => ["g1","group"],
            ],false],
            
            // Match on username
            [$user,[
                "users" => ["test","u8","tester", "user1"],
                "groups" => ["g1","group"],
            ],true],
            
            // Match on group
            [$user,[
                "users" => ["test","u8","tester"],
                "groups" => ["g1","group", "testGroup"],
            ],true],
            
            // No rule exists => access allowed
            [$user,null,true],
            
            [$user,[],false],
        ];
    }
    
    public function testRuntimeRules()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageACLStorageInterface::class);
        $storage->expects($this->exactly(1))
            ->method('getRuleByURL')
            ->willReturn(null);
        
        $user = new \PicoAuth\User;
        $user->setId("user1");
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->exactly(1))
            ->method('getUser')
            ->willReturn($user);
        
        $pageACL = new PageACL($picoAuth, $sess, $storage);
        $pageACL->addRule("randomUrl", ["users"=>["user1"]]);
        $res = $pageACL->checkAccess("randomUrl");
        $this->assertTrue($res);
        
        $this->expectException(\InvalidArgumentException::class);
        $pageACL->addRule("randomUrl", 1);
    }
    
    public function testDenyAccessIfRestrictedNotAuthenticated()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageACLStorageInterface::class);
        $storage->expects($this->exactly(1))
            ->method('getRuleByURL')
            ->willReturn([]);
        
        $user = new \PicoAuth\User;
        $user->setId("user1");
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->exactly(2))
            ->method('getUser')
            ->willReturn($user);
        $picoAuth->expects($this->exactly(1))
            ->method('redirectToLogin')
            ->with($this->equalTo("afterLogin=randomUrl"));
        
        $pageACL = new PageACL($picoAuth, $sess, $storage);
        $pageACL->denyAccessIfRestricted("randomUrl");
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testDenyAccessIfRestrictedAuthenticatedButDenied()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(PageACLStorageInterface::class);
        $storage->expects($this->exactly(1))
            ->method('getRuleByURL')
            ->willReturn([]);
        
        $user = new \PicoAuth\User;
        $user->setId("user1");
        $user->setAuthenticated(true);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->exactly(2))
            ->method('getUser')
            ->willReturn($user);
        $picoAuth->expects($this->exactly(1))
            ->method('setRequestUrl')
            ->with("403");
        
        $_SERVER['SERVER_PROTOCOL'] = "HTTP/1.1";
        $pageACL = new PageACL($picoAuth, $sess, $storage);
        $pageACL->denyAccessIfRestricted("randomUrl");
    }
    
    public function testGetName()
    {
        $this->assertEquals('pageACL', $this->pageACL->getName());
    }
}
