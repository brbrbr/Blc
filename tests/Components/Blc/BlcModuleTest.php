<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Tests\UnitTestCase;
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
        $messageHandler = BlcModule::getInstance();
        $this->assertInstanceOf(BlcModule::class, $messageHandler);
        return $messageHandler;
    }
    /* code coverage */
    public function testClone()
    {
        $moduleInstance = $this->testCanBoot();
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be cloned.');
        clone $moduleInstance;
    }
    public function testWakeup()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be serialized.');
        $moduleInstance    = $this->testCanBoot();
        $serializeInstance = serialize($moduleInstance);
        $moduleInstance    = unserialize($serializeInstance);
    }
}
