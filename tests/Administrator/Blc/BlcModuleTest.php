<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Tests\UnitTestCase;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for BlcModule
 *
 * @package     Joomla.UnitTest
 * @subpackage  BlcModule
 * @since       4.2.0
 */
#[TestDox('Test of the BlcModule Base Class')]
#[CoversClass(BlcModule::class)]
class BlcModuleTest extends UnitTestCase
{
    /**
     * Concrete implementation for testing abstract BlcModule
     */
    private string $testModuleClass;

    public function setUp(): void
    {
        parent::setUp();


        // Create concrete test class if not already defined
        if (!class_exists(TestBlcModule::class)) {
            eval('
                namespace Blc\Tests\Administrator\Blc;
                use Blc\Component\Blc\Administrator\Blc\BlcModule;
                class TestBlcModule extends BlcModule {}
            ');
        }

        $this->testModuleClass = TestBlcModule::class;

        // Reset singleton before each test
        TestBlcModule::resetInstance();
    }

    public function tearDown(): void
    {
        // Clean up singleton instances after each test
        TestBlcModule::resetInstance();
        parent::tearDown();
    }

    #[Test]
    #[TestDox('Can instantiate module as singleton')]
    public function testCanBootAsSingleton(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        $this->assertInstanceOf(BlcModule::class, $moduleInstance);
        $this->assertInstanceOf(TestBlcModule::class, $moduleInstance);
    }

    #[Test]
    #[TestDox('Can instantiate module as new instance')]
    public function testCanBootAsNewInstance(): void
    {
        $moduleInstance = TestBlcModule::getInstance(false);

        $this->assertInstanceOf(BlcModule::class, $moduleInstance);
        $this->assertInstanceOf(TestBlcModule::class, $moduleInstance);
    }

    #[Test]
    #[TestDox('Singleton returns same instance')]
    public function testSingletonReturnsSameInstance(): void
    {
        $instance1 = TestBlcModule::getInstance();
        $instance2 = TestBlcModule::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertSame(spl_object_hash($instance1), spl_object_hash($instance2));
    }

    #[Test]
    #[TestDox('Non-singleton returns different instances')]
    public function testNonSingletonReturnsDifferentInstances(): void
    {
        $instance1 = TestBlcModule::getInstance(false);
        $instance2 = TestBlcModule::getInstance(false);

        $this->assertNotSame($instance1, $instance2);
        $this->assertNotSame(spl_object_hash($instance1), spl_object_hash($instance2));
    }

    #[Test]
    #[TestDox('Reset instance clears singleton')]
    public function testResetInstanceClearsSingleton(): void
    {
        $instance1   = TestBlcModule::getInstance();
        $objectHash1 = spl_object_hash($instance1);

        TestBlcModule::resetInstance();

        $instance2   = TestBlcModule::getInstance();
        $objectHash2 = spl_object_hash($instance2);

        $this->assertNotSame($objectHash1, $objectHash2);
    }

    #[Test]
    #[TestDox('Can set and get config option')]
    public function testCanSetAndGetConfigOption(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $result         = $moduleInstance->setConfigOption('test_key', 'test_value');

        // Test fluent interface
        $this->assertSame($moduleInstance, $result);

        // Test value retrieval
        $value = $moduleInstance->getConfigOption('test_key');
        $this->assertEquals('test_value', $value);
    }

    #[Test]
    #[TestDox('Get config option returns default when key not found')]
    public function testGetConfigOptionReturnsDefault(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $value          = $moduleInstance->getConfigOption('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    #[Test]
    #[TestDox('Set config option with reinit calls init')]
    public function testSetConfigOptionWithReinitCallsInit(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        // Set initial value
        $moduleInstance->setConfigOption('test', 'initial');

        // Set with reinit - this should preserve the value
        $moduleInstance->setConfigOption('test', 'updated', true);

        $value = $moduleInstance->getConfigOption('test');
        $this->assertEquals('updated', $value);
    }

    #[Test]
    #[TestDox('Can set and get entire config')]
    public function testCanSetAndGetConfig(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $customConfig   = new Registry(['key1' => 'value1', 'key2' => 'value2']);

        $result = $moduleInstance->setConfig($customConfig);

        // Test fluent interface
        $this->assertSame($moduleInstance, $result);

        // Test config retrieval
        $config = $moduleInstance->getConfig();
        $this->assertInstanceOf(Registry::class, $config);
        $this->assertEquals('value1', $config->get('key1'));
        $this->assertEquals('value2', $config->get('key2'));
    }

    #[Test]
    #[TestDox('Can set and get param option')]
    public function testCanSetAndGetParamOption(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $result         = $moduleInstance->setParamsOption('param_key', 'param_value');

        // Test fluent interface
        $this->assertSame($moduleInstance, $result);

        // Test value retrieval
        $value = $moduleInstance->getParamsOption('param_key');
        $this->assertEquals('param_value', $value);
    }

    #[Test]
    #[TestDox('Get param option returns default when key not found')]
    public function testGetParamOptionReturnsDefault(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $value          = $moduleInstance->getParamsOption('nonexistent_param', 'default_param');

        $this->assertEquals('default_param', $value);
    }

    #[Test]
    #[TestDox('Can set and get entire params')]
    public function testCanSetAndGetParams(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        // Set initial params
        $moduleInstance->setParamsOption('old_key', 'old_value');

        // Replace with new params
        $customParams = new Registry(['new_key' => 'new_value']);
        $result       = $moduleInstance->setParams($customParams);

        // Test fluent interface
        $this->assertSame($moduleInstance, $result);

        // Test params retrieval
        $params = $moduleInstance->getParams();
        $this->assertInstanceOf(Registry::class, $params);
        $this->assertEquals('new_value', $params->get('new_key'));

        // Old key should not exist
        $this->assertNull($params->get('old_key'));
    }

    #[Test]
    #[TestDox('Setting params replaces all previous params')]
    public function testSetParamsReplacesAllPreviousParams(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        // Set multiple params
        $moduleInstance->setParamsOption('test1', 'value1');
        $moduleInstance->setParamsOption('test2', 'value2');

        // Replace with new params registry
        $newParams = new Registry(['test3' => 'value3']);
        $moduleInstance->setParams($newParams);

        // Old params should not exist
        $this->assertEquals('default', $moduleInstance->getParamsOption('test1', 'default'));
        $this->assertEquals('default', $moduleInstance->getParamsOption('test2', 'default'));

        // New param should exist
        $this->assertEquals('value3', $moduleInstance->getParamsOption('test3'));
    }

    #[Test]
    #[TestDox('Params contain class name after init')]
    public function testParamsContainClassName(): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $className      = $moduleInstance->getParamsOption('class');

        $this->assertEquals(TestBlcModule::class, $className);
    }

    #[Test]
    #[TestDox('Cannot clone singleton instance')]
    public function testCannotCloneSingleton(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Singleton class cannot be cloned/');

        clone $moduleInstance;
    }

    #[Test]
    #[TestDox('Cannot serialize singleton instance')]
    public function testCannotSerializeSingleton(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Singleton class cannot be serialized/');

        serialize($moduleInstance);
    }


    #[Test]
    #[TestDox('Multiple subclasses maintain separate singletons')]
    public function testMultipleSubclassesMaintainSeparateSingletons(): void
    {
        // Create another test class
        if (!class_exists(AnotherTestBlcModule::class)) {
            eval('
                namespace Blc\Tests\Administrator\Blc;
                use Blc\Component\Blc\Administrator\Blc\BlcModule;
                class AnotherTestBlcModule extends BlcModule {}
            ');
        }

        $instance1 = TestBlcModule::getInstance();
        $instance2 = AnotherTestBlcModule::getInstance();

        // Should be different instances
        $this->assertNotSame($instance1, $instance2);

        // But each should maintain its own singleton
        $instance1Again = TestBlcModule::getInstance();
        $instance2Again = AnotherTestBlcModule::getInstance();

        $this->assertSame($instance1, $instance1Again);
        $this->assertSame($instance2, $instance2Again);
    }

    #[Test]
    #[TestDox('Fluent interface chain works correctly')]
    public function testFluentInterfaceChain(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        $result = $moduleInstance
            ->setConfigOption('config_key', 'config_value')
            ->setParamsOption('param_key', 'param_value')
            ->setConfig(new Registry(['another_key' => 'another_value']));

        $this->assertSame($moduleInstance, $result);
        $this->assertEquals('another_value', $moduleInstance->getConfigOption('another_key'));
        $this->assertEquals('param_value', $moduleInstance->getParamsOption('param_key'));
    }

    #[Test]
    #[TestDox('Config and params are properly initialized')]
    public function testConfigAndParamsAreProperlyInitialized(): void
    {
        $moduleInstance = TestBlcModule::getInstance();

        // Should not throw errors when accessing
        $config = $moduleInstance->getConfig();
        $params = $moduleInstance->getParams();

        $this->assertInstanceOf(Registry::class, $config);
        $this->assertInstanceOf(Registry::class, $params);
    }

    #[Test]
    #[DataProvider('configValueProvider')]
    #[TestDox('Can handle various config value types')]
    public function testCanHandleVariousConfigValueTypes(mixed $value): void
    {
        $moduleInstance = TestBlcModule::getInstance();
        $moduleInstance->setConfigOption('test_key', $value);

        $retrievedValue = $moduleInstance->getConfigOption('test_key');
        $this->assertEquals($value, $retrievedValue);
    }

    public static function configValueProvider(): array
    {
        return [
            'string'        => ['string_value'],
            'integer'       => [42],
            'float'         => [3.14],
            'boolean_true'  => [true],
            'boolean_false' => [false],
            'null'          => [null],
            'array'         => [['key' => 'value']],
            'object'        => [(object)['key' => 'value']],
        ];
    }
}
