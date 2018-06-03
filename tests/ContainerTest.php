<?php

namespace PicoAuth;

class ContainerTest extends \PicoAuth\BaseTestCase
{
    
    protected $container;
    
    protected function setUp()
    {
        $path = __DIR__ . '/../src/container.php';
        $this->container = include $path;
    }
    
    public function testContainer()
    {
        $this->assertNotNull($this->container);
        $this->assertTrue($this->container instanceof \League\Container\Container);
    }
    
    /**
     * @dataProvider dependencyNamesProvider
     */
    public function testContainerHas($name)
    {
        $this->assertTrue($this->container->has($name));
    }
    
    public function dependencyNamesProvider()
    {
        return [
            ["Version"],
            ["cache"],
            ["logger"],
            ["PasswordPolicy"],
            ["session"],
            ["session.storage"],
            ["Version"],
            ["LocalAuth"],
            ["OAuth"],
            ["PageACL"],
            ["PageLock"],
            ["Installer"],
            ["LocalAuth.storage"],
            ["OAuth.storage"],
            ["PageACL.storage"],
            ["PageLock.storage"],
            ["RateLimit.storage"],
            ["bcrypt"],
            ["argon2i"],
            ["plain"],
            ["RateLimit"],
            ["PasswordReset"],
            ["Registration"],
            ["EditAccount"],
        ];
    }
}
