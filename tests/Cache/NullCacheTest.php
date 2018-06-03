<?php

namespace PicoAuth\Cache;

class NullCacheTest extends \PicoAuth\BaseTestCase
{
    
    protected $nullCache;
    
    protected function setUp()
    {
        $this->nullCache = new NullCache;
    }
    
    public function testClear()
    {
        $this->assertTrue($this->nullCache->clear());
    }

    public function testDelete()
    {
        $this->assertTrue($this->nullCache->delete("test"));
    }

    public function testDeleteMultiple()
    {
        $this->assertTrue($this->nullCache->deleteMultiple(["t1","t2"]));
    }

    public function testGet()
    {
        $this->assertNull($this->nullCache->get("test"));
        $this->assertEquals(1, $this->nullCache->get("test", 1));
    }

    public function testGetMultiple()
    {
        $this->assertEquals(
            ["t1"=>null,"t2"=>null],
            $this->nullCache->getMultiple(["t1","t2"])
        );
        
        $this->assertEquals(
            ["t1"=>1,"t2"=>1],
            $this->nullCache->getMultiple(["t1","t2"], 1)
        );
    }

    public function testHas()
    {
        $this->assertFalse($this->nullCache->has("test"));
    }

    public function testSet()
    {
        $this->assertFalse($this->nullCache->set("test", 1));
    }

    public function testSetMultiple()
    {
        $this->assertFalse($this->nullCache->setMultiple(["a"=>1,"b"=>2]));
    }
}
