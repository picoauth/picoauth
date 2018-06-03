<?php

namespace PicoAuth\Module\Authentication;

use PicoAuth\Storage\Interfaces\OAuthStorageInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Container\Container;
use PicoAuth\Storage\Configurator\LocalAuthConfigurator;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class OAuthTest extends \PicoAuth\BaseTestCase
{
    
    protected $oAuth;
    
    protected $session;

    protected function setUp()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = new MockArraySessionStorage();
        $this->session = new \PicoAuth\Session\SymfonySession($storage);
        $storage = $this->createMock(OAuthStorageInterface::class);

        $this->oAuth = new OAuth($picoAuth, $this->session, $storage);
    }

    private function getTestProvider()
    {
        return array(
            "provider" => "\League\OAuth2\Client\Provider\GenericProvider",
            "options" => array(
                "clientId" => "t",
                "clientSecret" => "t",
                "urlAuthorize" => "https://test.com/authorize",
                "urlAccessToken" => "https://test.com/oauth/token",
                "urlResourceOwnerDetails" => "https://test.com/user",
            ),
            "attributeMap" => array(
                "userId" => "id",
                "displayName" => "dispName",
                "attr" => "attr",
            ),
            "default" => array(
                "groups" => ["testGroup","g2"],
                "attributes" => ["img"=>"test.png"]
            )
        );
    }
    
    public function testGetName()
    {
        $this->assertEquals('OAuth', $this->oAuth->getName());
    }
    
    public function testGetStorage()
    {
        $this->assertEquals(self::get($this->oAuth, 'storage'), $this->oAuth->getStorage());
    }
    
    public function testOnPicoRequestLoginSubmission()
    {
        $pico = $this->createMock(\Pico::class);
        $pico->expects($this->exactly(1))
            ->method("getPageUrl")
            ->willReturn("callback");
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("isValidCSRF")
            ->willReturn(false);
        $picoAuth->expects($this->once())
            ->method("redirectToLogin") //after the wrong CSRF
            ->withAnyParameters();
        $picoAuth->expects($this->once())
            ->method("getPico")
            ->willReturn($pico);
        $picoAuth->expects($this->once())
            ->method("redirectToPage")
            ->with(
                $this->stringStartsWith($this->getTestProvider()["options"]["urlAuthorize"]),
                null,
                false
            );
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method("getProviderByName")
            ->willReturn($this->getTestProvider());
        $OAuth = new OAuth($picoAuth, $sess, $storage);
        
        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        $request->headers = new ParameterBag(["referer"=>"http://test.com/?afterLogin=index"]);
        
        $OAuth->handleEvent("onPicoRequest", ["login", $request]);
    }
    
    /**
     * Request for provider that doesn't exist
     */
    public function testOnPicoRequestBadProvider()
    {
        $pico = $this->createMock(\Pico::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("isValidCSRF")
            ->willReturn(true);
        
        // Redirect call will abort the script, here simulated with exception
        $picoAuth->expects($this->once())
            ->method("redirectToLogin")
            ->withAnyParameters()
            ->willThrowException(new \LogicException());
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method("getProviderByName")
            ->willReturn(null);
        $OAuth = new OAuth($picoAuth, $sess, $storage);
        
        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        
        $this->expectException(\LogicException::class);
        
        $OAuth->onPicoRequest("login", $request);
    }
    
    public function testOnPicoRequestStateMismatch()
    {
        $pico = $this->createMock(\Pico::class);
        $log = $this->createMock(\Psr\Log\LoggerInterface::class);
        $log->expects($this->once())
            ->method("warning");
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $this->session->set("provider", "testProvider");
        $this->session->set("oauth2state", "testState");
        $storage = $this->createMock(OAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method("getConfiguration")
            ->willReturn([
                "callbackPage" => "callback"
            ]);
        $storage->expects($this->once())
            ->method("getProviderByName")
            ->with("testProvider")
            ->willReturn($this->getTestProvider());
        $picoAuth->expects($this->any())
            ->method("getPico")
            ->willReturn($pico);
        $picoAuth->expects($this->once())
            ->method("redirectToLogin")
            ->withAnyParameters()
            ->willThrowException(new \LogicException());
        $OAuth = new OAuth($picoAuth, $this->session, $storage);
        $OAuth->setLogger($log);
        $_SERVER["REMOTE_ADDR"] = '10.0.0.1';
        
        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        $request->query = new ParameterBag(["state"=>"testStateWrong"]);
        
        $this->expectException(\LogicException::class);
        $OAuth->onPicoRequest("callback", $request);
    }
    
    public function testOnPicoRequestCallbackProviderMissing()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $this->session->set("provider", "testProvider");
        $this->session->set("oauth2state", "testState");
        $storage = $this->createMock(OAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method("getConfiguration")
            ->willReturn([
                "callbackPage" => "callback"
            ]);
        $storage->expects($this->once())
            ->method("getProviderByName")
            ->with("testProvider")
            ->willReturn(null);
        $OAuth = new OAuth($picoAuth, $this->session, $storage);
        
        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        $request->query = new ParameterBag(["state"=>"testState"]);
        
        $this->expectException(\RuntimeException::class);
        $OAuth->onPicoRequest("callback", $request);
    }
    
    public function testInitProviderNonExistent()
    {
        $OAuth = $this->getMockBuilder(OAuth::class)
            ->setMethodsExcept(["initProvider"])
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectException(\RuntimeException::class);
        $method=self::getMethod($OAuth, 'initProvider');
        $method->invokeArgs($OAuth, [[
            "provider" => "\Non\Existing\Provider",
            "options" => ["redirectUri"=>""]
        ]]);
    }

    public function testInitProviderWrongClass()
    {
        $OAuth = $this->getMockBuilder(OAuth::class)
            ->setMethodsExcept(["initProvider"])
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectException(\RuntimeException::class);
        $method=self::getMethod($OAuth, 'initProvider');
        $method->invokeArgs($OAuth, [[
            "provider" => self::class, //random class that is not a League\OAuth2 provider
            "options" => ["redirectUri"=>""]
        ]]);
    }

    public function testLoggingEvents()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $OAuth = new OAuth($picoAuth, $this->session, $storage);
        $idpExc=new IdentityProviderException("test", 0, "");
        self::set($OAuth, new self(), 'provider'); //any class, but must be set
        
        $log = $this->createMock(\Psr\Log\LoggerInterface::class);
        $log->expects($this->at(0))
            ->method("warning");
        $log->expects($this->at(1))
            ->method("notice");
        $log->expects($this->at(2))
            ->method("critical");
        $OAuth->setLogger($log);
        
        self::getMethod($OAuth, 'onStateMismatch')->invokeArgs($OAuth, []);
        self::getMethod($OAuth, 'onOAuthError')->invokeArgs($OAuth, ["errCode"]);
        self::getMethod($OAuth, 'onOauthResourceError')->invokeArgs($OAuth, [$idpExc]);
    }
    
    public function testSaveLoginInfo()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $resourceOwner = $this->createMock(\League\OAuth2\Client\Provider\ResourceOwnerInterface::class);
        $resourceOwner->expects($this->exactly(2))
            ->method("toArray")
            ->willReturnOnConsecutiveCalls(["dispName"=>"test"], ["attr"=>"attr value"]);
        $picoAuth->expects($this->once())
            ->method("afterLogin");
        $user = null;
        $picoAuth->expects($this->once())
            ->method("setUser")
            ->with($this->callback(function ($subject) use (&$user) {
                $user = $subject;
                return true;
            }));
        $OAuth = new OAuth($picoAuth, $this->session, $storage);
        self::set($OAuth, $this->getTestProvider(), 'providerConfig');
        self::getMethod($OAuth, 'saveLoginInfo')->invokeArgs($OAuth, [$resourceOwner]);
        
        $this->assertInstanceOf(\PicoAuth\User::class, $user);
        $this->assertSame($this->getTestProvider()["default"]["groups"], $user->getGroups());
        $this->assertEquals("test.png", $user->getAttribute("img"));
    }

    public function testFinishAuthentication()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $provider = $this->createMock(\League\OAuth2\Client\Provider\GenericProvider::class);
        $provider->expects($this->once())
            ->method("getAccessToken")
            ->willReturn($this->createMock(\League\OAuth2\Client\Token\AccessToken::class));
        $provider->expects($this->once())
            ->method("getResourceOwner")
            ->willReturn($this->createMock(\League\OAuth2\Client\Provider\ResourceOwnerInterface::class));

        $this->session->set("provider", "testProvider");
        $this->session->set("oauth2state", "testState");
        $OAuth = $this->getMockBuilder(OAuth::class)
            ->setMethods(['saveLoginInfo','onOAuthError'])
            ->setConstructorArgs([$picoAuth, $this->session, $storage])
            ->getMock();
        $OAuth->expects($this->once())
            ->method("saveLoginInfo");

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        $request->query = new ParameterBag(["state"=>"testState", "code"=>"testCode"]);

        self::set($OAuth, $provider, 'provider');
        self::getMethod($OAuth, 'finishAuthentication')->invokeArgs($OAuth, [$request]);
    }

    public function testFinishAuthenticationErrors()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = $this->createMock(OAuthStorageInterface::class);
        $provider = $this->createMock(\League\OAuth2\Client\Provider\GenericProvider::class);
        $provider->expects($this->once())
            ->method("getAccessToken")
            ->willThrowException(new IdentityProviderException("idpExc", 0, "'"));

        $this->session->set("provider", "testProvider");
        $this->session->set("oauth2state", "testState");
        $OAuth = $this->getMockBuilder(OAuth::class)
            ->setMethods(['onOauthResourceError','onOAuthError'])
            ->setConstructorArgs([$picoAuth, $this->session, $storage])
            ->getMock();
        $OAuth->expects($this->once())
            ->method("onOauthResourceError");

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["oauth"=>"test"]);
        $request->query = new ParameterBag(["state"=>"testState", "error"=>"err"]);

        self::set($OAuth, $provider, 'provider');
        self::getMethod($OAuth, 'finishAuthentication')->invokeArgs($OAuth, [$request]);
    }
}
