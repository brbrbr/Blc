<?php

namespace Blc\Tests\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(BlcTransientManager::class)]
#[Attributes\TestDox('Test of the BLC - Transient Manager')]
class BlcTransientManagerTest extends UnitTestCase
{
    private BlcTransientManager $manager;

    public function setUp(): void
    {
        parent::initApplication();
        $this->manager =  BlcTransientManager::getInstance();
    }

    public function testSetAndGet()
    {
        $key   = 'test_key';
        $value = ['test' => 'value'];

        $this->manager->set($key, $value, 3600);
        $result = $this->manager->get($key, true);
        $this->assertEquals($value, $result);
    }

    public function testGetNonExistentKey()
    {
        $result = $this->manager->get('nonexistent_key');
        $this->assertFalse($result);
    }

    public function testDelete()
    {
        $key   = 'delete_test';
        $value = 'test_value';

        $this->manager->set($key, $value);
        $this->manager->delete($key);

        $result = $this->manager->get($key);
        $this->assertFalse($result);
    }

    public function testSetWithNullValue()
    {
        $key   = 'null_test';
        $value = 'test_value';

        $this->manager->set($key, $value);
        $this->manager->set($key, null);

        $result = $this->manager->get($key);
        $this->assertFalse($result);
    }

    public function testExpiredTransient()
    {
        $key   = 'expired_test';
        $value = 'test_value';
        $this->manager->set($key, $value, 7200); //settings it twice so we will have code coverage for updateObject
        $this->manager->set($key, $value, -1); // Set expired ( last year)
        $result = $this->manager->get($key);

        $this->assertFalse($result);
    }

    public function testClear()
    {
        $key1 = 'clear_test1';
        $key2 = 'clear_test2';

        $this->manager->set($key1, 'value1', 0); //this will set the life time to last year.
        $this->manager->set($key2, 'value2', 3600);


        $this->manager->clear(true); // clear expired

        $this->assertFalse($this->manager->get($key1));
        $this->assertEquals('value2', $this->manager->get($key2));

        $this->manager->clear(false); // clear all

        $this->assertFalse($this->manager->get($key1));
        $this->assertFalse($this->manager->get($key2));
    }

    public function testLongLifetimeTransient()
    {
        $key   = 'long_test';
        $value = 'test_value';

        $this->manager->set($key, $value, true);
        $result = $this->manager->get($key);

        $this->assertEquals($value, $result);
    }
}
