<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Tests\UnitTestCase;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\TestDox('Test of the Messages Handler')]
#[Attributes\CoversClass(BlcModule::class)]
class BlcModuleTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testCanBoot()
    {
        $moduleInstance = BlcModule::getInstance();
        $this->assertSame(BlcModule::class, $moduleInstance::class);
    }


    public function testConfigOption()
    {
        $moduleInstance = BlcModule::getInstance();
        $self           = $moduleInstance->setConfigOption('test', 'test', true);
        $this->assertSame(BlcModule::class, $self::class);
        $reflection = new \ReflectionClass($moduleInstance);
        $property   = $reflection->getProperty('componentConfig');

        $componentConfig =  $property->getValue($moduleInstance);

        $this->assertEquals('test', $componentConfig->get('test', 'default'));
    }

    public function testParamOption()
    {
        $moduleInstance = BlcModule::getInstance();
        $self           = $moduleInstance->setParamsOption('test', 'test');
        $this->assertSame(BlcModule::class, $self::class);

        $option = $self->getParamsOption('test', 'default');
        $this->assertEquals('test', $option);
        $reflection = new \ReflectionClass($moduleInstance);
        $property   = $reflection->getProperty('params');

        $params =  $property->getValue($moduleInstance);
        $this->assertEquals('test', $params->get('test', 'default'));
    }

    public function testsetParams()
    {
        $moduleInstance = BlcModule::getInstance();
        $self           = $moduleInstance->setParamsOption('test', 'test');
        $self           = $moduleInstance->setParamsOption('test2', 'test');
        $this->assertSame(BlcModule::class, $self::class);
        $self       = $moduleInstance->setParams(new Registry(['test' => 'dummy']));
        $reflection = new \ReflectionClass($moduleInstance);
        $property   = $reflection->getProperty('params');

        $params =  $property->getValue($moduleInstance);
        $this->assertEquals('dummy', $params->get('test', 'default'));
        $this->assertFalse($params->exists('test2'));
        $option = $self->getParamsOption('test2', 'default');
        $this->assertEquals('default', $option);
    }


    public function testParamsOptionsOverwrite()
    {
        $moduleInstance = BlcModule::getInstance();
        $self           = $moduleInstance->setParamsOption('test', 'test');
        $this->assertSame(BlcModule::class, $self::class);
        $self       = $moduleInstance->setConfigOption('test', 'test', true);
        $reflection = new \ReflectionClass($moduleInstance);
        $property   = $reflection->getProperty('params');

        $params =  $property->getValue($moduleInstance);

        $this->assertEquals('test', $params->get('test', 'default'));
    }


    /* code coverage */
    public function testClone()
    {
        $moduleInstance = BlcModule::getInstance();
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be cloned.');
        clone $moduleInstance;
    }
    public function testWakeup()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be serialized.');
        $moduleInstance    = BlcModule::getInstance();
        $serializeInstance = serialize($moduleInstance);
        $moduleInstance    = unserialize($serializeInstance);
    }
    public function testSingeTon()
    {
        $moduleInstance = BlcModule::getInstance();
        $objectHash1    = spl_object_hash($moduleInstance);
        unset($moduleInstance);
        $moduleInstance = BlcModule::getInstance();
        $objectHash2    = spl_object_hash($moduleInstance);
        $this->assertSame($objectHash1, $objectHash2);
    }
    public function testnewInstance()
    {
        $moduleInstance = BlcModule::getInstance();
        $objectHash1    = spl_object_hash($moduleInstance);
        unset($moduleInstance);
        $moduleInstance = BlcModule::getInstance(false);
        $objectHash2    = spl_object_hash($moduleInstance);
        $this->assertNotSame($objectHash1, $objectHash2);
    }
}
