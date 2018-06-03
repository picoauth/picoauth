<?php

namespace PicoAuth\Module\Authentication\LocalAuth;

use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Security\RateLimiting\RateLimitInterface;
use PicoAuth\Session\SessionInterface;
use PicoAuth\PicoAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Container\Container;
use PicoAuth\Storage\Configurator\LocalAuthConfigurator;

class LocalAuthTest extends \PicoAuth\BaseTestCase
{
    
    protected $localAuth;
    
    protected $configurator;
    
    protected $testContainer;

    protected function setUp()
    {
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $this->localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $this->testContainer=new Container;
        $this->initTestContainer($this->testContainer);
        
        $this->configurator = new LocalAuthConfigurator;
    }
    
    protected function initTestContainer(Container $container)
    {
        $shortEncoder = $this->createMock(\PicoAuth\Security\Password\Encoder\PasswordEncoderInterface::class);
        $shortEncoder->expects($this->any())
            ->method('getMaxAllowedLen')
            ->willReturn(4);
        $shortEncoder->expects($this->any())
            ->method('encode')
            ->willThrowException(new \PicoAuth\Security\Password\Encoder\EncoderException("Too long"));
        
        $container->share('plain', 'PicoAuth\Security\Password\Encoder\Plaintext');
        $container->share('plain2', 'PicoAuth\Security\Password\Encoder\Plaintext');
        $container->share('shortPw', $shortEncoder);
    }
    
    public function testHandleLoginNoPostFileds()
    {
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn([]);
        
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $sess = $this->createMock(SessionInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
        
        $request = $this->createMock(Request::class);
        $request->request = $this->createMock(ParameterBag::class);
        $localAuth->onPicoRequest("login", $request);
    }
    
    public function testHandleLoginInvalidCSRF()
    {
        $request = $this->createMock(Request::class);
        $post = new ParameterBag(["username"=>"test","password"=>"test"]);
        $request->request = $post;
        
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn([]);
        
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('isValidCSRF')
            ->withAnyParameters()
            ->willReturn(false);
        $picoAuth->expects($this->once())
            ->method("redirectToLogin")
            ->with($this->isNull(), $this->equalTo($request));
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("login", $request);
    }
    
    public function testHandleLoginRateLimited()
    {
        $request = $this->createMock(Request::class);
        $post = new ParameterBag(["username"=>"test","password"=>"test"]);
        $request->request = $post;
        
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate([]));
        
        // The user is rate limited
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $rateLimit->expects($this->once())
            ->method("action")
            ->withAnyParameters()
            ->willReturn(false);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('isValidCSRF')
            ->withAnyParameters()
            ->willReturn(true);
        $picoAuth->expects($this->once())
            ->method("redirectToLogin")
            ->with($this->isNull(), $this->equalTo($request));
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("login", $request);
    }
    
    public function testHandleLoginInvalid()
    {
        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(["username"=>"testtt","password"=>"test"]);
        
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate(["registration" => ["nameLenMax"=>4]]));
        
        // The user is rate limited
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $rateLimit->expects($this->exactly(2))
            ->method("action")
            ->withAnyParameters()
            ->willReturn(true);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('isValidCSRF')
            ->withAnyParameters()
            ->willReturn(true);
        $picoAuth->expects($this->once())
            ->method("redirectToLogin")
            ->with($this->isNull(), $this->equalTo($request));
        
        $localAuth = $this->getMockBuilder(LocalAuth::class)
            ->setConstructorArgs([$picoAuth, $sess, $storage, $rateLimit])
            ->setMethodsExcept(['onPicoRequest','handleLogin'])
            ->getMock();
        $localAuth->expects($this->once())
            ->method('loginAttempt')
            ->willReturn(false);
        $_SERVER["REMOTE_ADDR"] = '10.0.0.1';
        $localAuth->onPicoRequest("login", $request);
    }

    public function testHandleLoginSuccessfulWithRehash()
    {
        $request = $this->createMock(Request::class);
        $post = new ParameterBag(["username"=>"test","password"=>"test"]);
        $request->request = $post;
        
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        
        // User's encoder is plain, but the configuration uses plain2 => rehash
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate(
                ["encoder" => "plain2","login" => ["passwordRehash"=>true]]
            ));
        $storage->expects($this->exactly(3))
            ->method('getUserByName')
            ->with("test")
            ->willReturn(["pwhash"=>"test","encoder"=>"plain"]);
        $storage->expects($this->once())
            ->method('saveUser')
            ->willReturn(true);
        
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $rateLimit->expects($this->once())
            ->method("action")
            ->withAnyParameters()
            ->willReturn(true);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('isValidCSRF')
            ->withAnyParameters()
            ->willReturn(true);
        $picoAuth->expects($this->once())
            ->method("afterLogin");
        $picoAuth->expects($this->exactly(2))
            ->method("getContainer")
            ->willReturn($this->testContainer);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("login", $request);
    }
    
    public function testGetName()
    {
        $this->assertEquals('localAuth', $this->localAuth->getName());
    }
    
    public function testGetConfig()
    {
        $this->assertEquals(self::get($this->localAuth, 'config'), $this->localAuth->getConfig());
    }
    
    public function testHandleAccountPageWhileNotAuthenticated()
    {
        $request = $this->createMock(Request::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);

        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('getUser')
            ->willReturn(new \PicoAuth\User);
        $picoAuth->expects($this->once())
            ->method('redirectToLogin');
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("account", $request);
    }
    
    public function testHandleAccountPageWhileAuthenticatedNotViaLocalAuth()
    {
        $request = $this->createMock(Request::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);

        $user = new \PicoAuth\User;
        $user->setAuthenticated(true)
            ->setAuthenticator('OAuth');
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $picoAuth->expects($this->once())
            ->method('redirectToPage')
            ->with("index");
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("account", $request);
    }
    
    public function testHandleAccountPage()
    {
        $request = $this->createMock(Request::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate([]));
        $rateLimit = $this->createMock(RateLimitInterface::class);

        $user = new \PicoAuth\User;
        $user->setAuthenticated(true)
            ->setAuthenticator($this->localAuth->getName());
        
        $editMock = $this->createMock(EditAccount::class);
        $editMock->expects($this->once())
            ->method('setConfig')
            ->willReturnSelf();
        $editMock->expects($this->once())
            ->method('handleAccountPage');
        
        $container=new Container;
        $container->share('EditAccount', $editMock);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($container);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("account", $request);
    }
    
    public function testHandleRegistration()
    {
        $request = $this->createMock(Request::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate([]));
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $registrationMock = $this->createMock(Registration::class);
        $registrationMock->expects($this->once())
            ->method('setConfig')
            ->willReturnSelf();
        $registrationMock->expects($this->once())
            ->method('handleRegistration');
        
        $container=new Container;
        $container->share('Registration', $registrationMock);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($container);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("register", $request);
    }
    
    public function testHandlePasswordReset()
    {
        $request = $this->createMock(Request::class);
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate([]));
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $pwResetMock = $this->createMock(PasswordReset::class);
        $pwResetMock->expects($this->once())
            ->method('setConfig')
            ->willReturnSelf();
        $pwResetMock->expects($this->once())
            ->method('handlePasswordReset');
        
        $container=new Container;
        $container->share('PasswordReset', $pwResetMock);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($container);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $localAuth->onPicoRequest("password_reset", $request);
    }
    
    public function testUserDataEncodePasswordPwreset()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn(["encoder" => "plain2","login" => ["passwordRehash"=>true]]);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($this->testContainer);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);

        $userData = ["pwreset"=>true];
        $localAuth->userDataEncodePassword($userData, new \PicoAuth\Security\Password\Password("test"));
        
        $this->assertFalse(isset($userData["pwreset"]));
        $this->assertEquals("test", $userData["pwhash"]);
    }
    
    public function testCheckPasswordPolicy()
    {
        $testedPw = new \PicoAuth\Security\Password\Password("test");
        $testedPwTooLong = new \PicoAuth\Security\Password\Password("teest1");
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn(["encoder" => "shortPw"]);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        
        $policy = $this->createMock(\PicoAuth\Security\Password\Policy\PasswordPolicyInterface::class);
        $policy->expects($this->exactly(2))
            ->method("check")
            ->withConsecutive($this->equalTo($testedPw), $this->equalTo($testedPwTooLong))
            ->willReturnOnConsecutiveCalls(false, true);
        $policy->expects($this->once())
            ->method("getErrors")
            ->willReturn(["e1"]);
        
        $this->testContainer->share('PasswordPolicy', $policy);
        
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->exactly(4))
            ->method("getContainer")
            ->willReturn($this->testContainer);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
        $localAuth->checkPasswordPolicy($testedPw);
        $localAuth->checkPasswordPolicy($testedPwTooLong);
    }
    
    public function testNeedsPasswordRehash()
    {
        
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->exactly(2))
            ->method('getConfiguration')
            ->willReturnOnConsecutiveCalls(
                ["encoder" => "plain","login" => ["passwordRehash"=>false]],
                ["encoder" => "plain","login" => ["passwordRehash"=>true]]
            );
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($this->testContainer);
        
        for ($i=0; $i<2; $i++) {
            $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
            $method=self::getMethod($localAuth, 'needsPasswordRehash');
            $method->invokeArgs($localAuth, [
                ["pwhash"=>"test"]
            ]);
        }
    }
    
    public function testAbortIfExpired()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        
        $pwResetMock = $this->createMock(PasswordReset::class);
        $pwResetMock->expects($this->once())
            ->method('startPasswordResetSession')
            ->with("test");
        $container=new Container;
        $container->share('PasswordReset', $pwResetMock);
        
        $picoAuth->expects($this->once())
            ->method("redirectToPage")
            ->with("password_reset");
        $picoAuth->expects($this->once())
            ->method("getContainer")
            ->willReturn($container);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
        $method=self::getMethod($localAuth, 'abortIfExpired');
        $method->invokeArgs($localAuth, [
            "test",
            ["pwreset"=>true]
        ]);
    }
    
    public function testPasswordRehash()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn(["encoder" => "shortPw"]);
        $storage->expects($this->once())
            ->method('getUserByName')
            ->with("testUser")
            ->willReturn(["pwhash"=>"teest1","encoder"=>"plain"]);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        
        $pwResetMock = $this->createMock(PasswordReset::class);
        $pwResetMock->expects($this->once())
            ->method('startPasswordResetSession')
            ->with("testUser");
        $container=new Container;
        $this->initTestContainer($container);
        $container->share('PasswordReset', $pwResetMock);
        
        $picoAuth->expects($this->once())
            ->method("redirectToPage")
            ->with("password_reset");
        $picoAuth->expects($this->exactly(2))
            ->method("getContainer")
            ->willReturn($container);
        
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
        $method=self::getMethod($localAuth, 'passwordRehash');
        $method->invokeArgs($localAuth, [
            "testUser",
            new \PicoAuth\Security\Password\Password("teest1")
        ]);
    }
    
    /**
     * @dataProvider nameProvider
     */
    public function testIsValidUsername($name, $valid)
    {
        $sess = $this->createMock(SessionInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $storage->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configurator->validate(
                ["registration" => ["nameLenMin"=>3,"nameLenMax"=>4]]
            ));
        $storage->expects($this->any())
            ->method('checkValidName')
            ->willReturn(true);
        $localAuth = new LocalAuth($picoAuth, $sess, $storage, $rateLimit);
        $res=self::getMethod($localAuth, 'isValidUsername')->invokeArgs($localAuth, [$name]);
        $this->assertSame($valid, $res);
    }
    
    public function nameProvider()
    {
        return [
            [null,false],
            [0,false],
            ["aa",false],
            ["aaa",true],
            ["aaaa",true],
            ["aaaaa",false]
        ];
    }
    
    public function testLogin()
    {
        $sess = $this->createMock(SessionInterface::class);
        $storage = $this->createMock(LocalAuthStorageInterface::class);
        $rateLimit = $this->createMock(RateLimitInterface::class);
        $picoAuth = $this->createMock(PicoAuthInterface::class);
        $picoAuth->expects($this->once())
            ->method('setUser')
            ->withAnyParameters()
            ->willReturn(true);

        $localAuth = $this->getMockBuilder(LocalAuth::class)
            ->setConstructorArgs([$picoAuth, $sess, $storage, $rateLimit])
            ->setMethods(['abortIfExpired'])
            ->getMock();
        $localAuth->expects($this->once())
            ->method('abortIfExpired');
        
        $localAuth->login("user", [
            "groups"=>["g1"],"displayName"=>"User",
            "attributes"=>["a"=>1]
        ]);
    }
}
