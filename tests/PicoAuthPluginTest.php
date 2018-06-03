<?php

namespace PicoAuth;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * PicoAuth plugin tests
 */
class PicoAuthPluginTest extends BaseTestCase
{
    
    /**
     * PicoAuth plugin instance
     * @var PicoAuthPlugin
     */
    protected $picoAuth;
    
    /**
     * Session
     * @var \PicoAuth\Session\SessionInterface
     */
    protected $session;
    
    protected function setUp()
    {
        $storage = new MockArraySessionStorage();
        $this->session = new Session\SymfonySession($storage);
        
        $picoMock = $this->getMockBuilder('\Pico')
            ->disableOriginalConstructor()
            ->getMock();
        $this->picoAuth = new PicoAuthPlugin($picoMock);
        self::set($this->picoAuth, $this->session, 'session');
    }
    
    public function testIsValidCSRF()
    {
        $csrf = $this->createMock(Security\CSRF::class);
        $csrf->expects($this->once())
            ->method('checkToken')
            ->with('valid')
            ->willReturn(true);
        
        self::set($this->picoAuth, $csrf, 'csrf');
        
        $this->assertTrue($this->picoAuth->isValidCSRF('valid'));
    }
    
    public function testIsInvalidCSRF()
    {
        $csrf = $this->createMock(Security\CSRF::class);
        $csrf->expects($this->once())
            ->method('checkToken')
            ->with('invalid')
            ->willReturn(false);
        
        self::set($this->picoAuth, $csrf, 'csrf');
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('warning')->willReturnSelf();
        self::set($this->picoAuth, $logger, 'logger');
        $_SERVER["REMOTE_ADDR"] = 'localhost';
        
        $this->assertFalse($this->picoAuth->isValidCSRF('invalid'));
    }
    
    public function testAfterLogin()
    {
        $session = $this->createMock(Session\SessionInterface::class);
        $session->expects($this->once())
            ->method("migrate")
            ->with(true);
        $session->expects($this->once())
            ->method("set")
            ->with('user', $this->anything());
        $session->expects($this->once())
            ->method("has")
            ->with('afterLogin')
            ->willReturn(true);
        $picoAuth = $this->getMockBuilder(PicoAuthPlugin::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['afterLogin','setUser'])
            ->getMock();
        
        $csrf = $this->createMock(Security\CSRF::class);
        $csrf->expects($this->once())
            ->method('removeTokens');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')->willReturnSelf();
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('getId');
        $user->expects($this->once())
            ->method('getDisplayName');
        $user->expects($this->once())
            ->method('getAuthenticator');
        $r=\Symfony\Component\HttpFoundation\Request::create('/');

        self::set($picoAuth, $csrf, 'csrf');
        self::set($picoAuth, $session, 'session');
        self::set($picoAuth, $logger, 'logger');
        self::set($picoAuth, $r, 'request');
        $picoAuth->setUser($user);
        $picoAuth->afterLogin();
    }
    
    public function testSetUser()
    {
        $user = new User;
        $this->picoAuth->setUser($user);
        
        $this->assertSame($this->picoAuth->getUser(), $user);
        
        $groups = $user->getGroups();
        
        $this->assertFalse(array_search("default", $groups));
        
        $user = new User;
        $user->setAuthenticated(true);
        $this->picoAuth->setUser($user);
        $groups = $user->getGroups();
        
        // Authenticated user should be gained the default group
        $this->assertNotFalse(array_search("default", $groups));
    }
    
    public function testGetModule()
    {
        self::set($this->picoAuth, array('Name'=>1), 'modules');
        $this->assertEquals(1, $this->picoAuth->getModule('Name'));
        
        self::set($this->picoAuth, array(), 'modules');
        $this->assertNull($this->picoAuth->getModule('Name'));
        $this->assertNull($this->picoAuth->getModule(null));
    }
    
    public function testGetPico()
    {
        $picoMock = $this->getMockBuilder('\Pico')
            ->disableOriginalConstructor()
            ->getMock();
        $picoAuth = new PicoAuthPlugin($picoMock);
        $this->assertSame($picoMock, $picoAuth->getPico());
    }
    
    public function testGetDefaultThemeUrl()
    {
        $picoMock = $this->createMock(\Pico::class);
        $picoMock->expects($this->once())
            ->method("getBaseUrl")
            ->willReturn("/");
        $picoAuth = new PicoAuthPlugin($picoMock);
        $this->assertSame("/plugins/PicoAuth/theme", $picoAuth->getDefaultThemeUrl());
    }
    
    public function testSetRequestFile()
    {
        $picoMock = $this->createMock(\Pico::class);
        $picoAuth = new PicoAuthPlugin($picoMock);
        $picoAuth->setRequestFile("/sub/page.md");
        $reqFile=self::get($picoAuth, "requestFile");
        $this->assertSame("/sub/page.md", $reqFile);
    }
    
    public function testSetRequestUrl()
    {
        $picoMock = $this->createMock(\Pico::class);
        $picoMock->expects($this->once())
            ->method("resolveFilePath")
            ->with("sub/page")
            ->willReturn("/sub/page.md");
        $picoAuth = new PicoAuthPlugin($picoMock);
        $picoAuth->setRequestUrl("sub/page");
        $reqUrl=self::get($picoAuth, "requestUrl");
        $this->assertSame("sub/page", $reqUrl);
        $reqFile=self::get($picoAuth, "requestFile");
        $this->assertSame("/sub/page.md", $reqFile);
    }
    
    public function testGetFlashes()
    {
        $session = $this->createMock(Session\SessionInterface::class);
        $session->expects($this->exactly(2))
            ->method("getFlash")
            ->willReturn(["test"]);
        $picoMock = $this->getMockBuilder('\Pico')
            ->disableOriginalConstructor()
            ->getMock();
        $picoAuth = new PicoAuthPlugin($picoMock);
        self::set($picoAuth, $session, 'session');

        $res=$picoAuth->getFlashes();
        $this->assertTrue(count($res)==2);
    }
    
    public function testOnConfigLoaded()
    {
        $picoAuth = $this->getMockBuilder(PicoAuthPlugin::class)
            ->disableOriginalConstructor()
            ->setMethods(['loadDefaultConfig','createContainer','initLogger'])
            ->getMock();
        $picoAuth->expects($this->once())
            ->method("loadDefaultConfig");
        $picoAuth->expects($this->once())
            ->method("createContainer");
        $picoAuth->expects($this->once())
            ->method("initLogger");
        $config=[];
        $picoAuth->handleEvent("onConfigLoaded", [&$config]);
    }
    
    public function testOnRequestUrl()
    {
        $picoAuth = $this->getMockBuilder(PicoAuthPlugin::class)
            ->disableOriginalConstructor()
            ->setMethods(['init','triggerEvent','errorHandler'])
            ->getMock();
        $picoAuth->expects($this->once())
            ->method("init");
        $picoAuth->expects($this->once())
            ->method("triggerEvent")
            ->willThrowException(new \RuntimeException("e"));
        $picoAuth->expects($this->once())
            ->method("errorHandler");
        $url="index";
        $picoAuth->handleEvent("onRequestUrl", [&$url]);
    }
    
    public function testAllowed()
    {
        $picoAuth = $this->getMockBuilder(PicoAuthPlugin::class)
            ->disableOriginalConstructor()
            ->setMethods(['triggerEvent'])
            ->getMock();
        
        $picoAuth->expects($this->once())
            ->method("triggerEvent")
            ->with('denyAccessIfRestricted', $this->anything());
        
        $file = "test.md";
        self::set($picoAuth, $file, 'requestFile');
        self::set($picoAuth, 'test', 'requestUrl');
        
        // Will trigger denyAccessIfRestricted
        $picoAuth->onRequestFile($file);
        
        $picoAuth->addAllowed("test");
        
        // Won't trigger denyAccessIfRestricted
        $picoAuth->onRequestFile($file);
    }
}
