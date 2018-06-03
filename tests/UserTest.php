<?php

namespace PicoAuth;

use PHPUnit\Framework\TestCase;

/**
 * PicoAuth plugin tests
 */
class UserTest extends TestCase
{
    
    public function testDefault()
    {
        $u = new User();
        $this->assertFalse($u->getAuthenticated());
    }
    

    public function testSetAuthenticated()
    {
        $u = new User();
        $u->setAuthenticated(true);
        $u->setAuthenticator("test");
        
        $this->assertTrue($u->getAuthenticated());
        $this->assertEquals("test", $u->getAuthenticator());
        
        $u->setAuthenticated(false);
        $this->assertEquals(null, $u->getAuthenticated());
    }
    
    public function testAttributes()
    {
        $u = new User();
        $this->assertNull($u->getAttribute("a"));
        $this->assertNull($u->getAttribute(null));
        
        $u->setAttribute("a", 1);
        $this->assertEquals(1, $u->getAttribute("a"));
    }
    
    public function testId()
    {
        $u = new User();
        $u->setId("u1");
        $this->assertEquals("u1", $u->getId());
    }

    public function testDisplayName()
    {
        $u = new User();
        $this->assertNull($u->getDisplayName());
        $u->setDisplayName("a");
        $this->assertEquals("a", $u->getDisplayName());
    }
    
    public function testGroups()
    {
        $u = new User();
        $u->setGroups(null);
        $this->assertEquals([], $u->getGroups());
        $u->setGroups(0);
        $this->assertEquals([], $u->getGroups());
        $u->setGroups("g0");
        $this->assertEquals([], $u->getGroups());
        $u->addGroup("g1");
        $this->assertEquals(["g1"], $u->getGroups());
        $u->addGroup("g2");
        $this->assertEquals(["g1","g2"], $u->getGroups());
        $u->setGroups(["g3"]);
        $this->assertEquals(["g3"], $u->getGroups());
        $u->addGroup(null);
        $u->addGroup(3);
        $u->addGroup(["g4"]);
        $this->assertEquals(["g3"], $u->getGroups());
    }
}
